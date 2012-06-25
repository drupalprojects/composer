<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Autoload;

use Composer\Installer\InstallationManager;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Util\Filesystem;

/**
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class AutoloadGenerator
{
    public function dump(RepositoryInterface $localRepo, PackageInterface $mainPackage, InstallationManager $installationManager, $targetDir)
    {
        $filesystem = new Filesystem();
        $filesystem->ensureDirectoryExists($installationManager->getVendorPath());
        $filesystem->ensureDirectoryExists($targetDir);
        $vendorPath = strtr(realpath($installationManager->getVendorPath()), '\\', '/');
        $relVendorPath = $filesystem->findShortestPath(getcwd(), $vendorPath, true);
        $vendorPathCode = $filesystem->findShortestPathCode(realpath($targetDir), $vendorPath, true);
        $vendorPathToTargetDirCode = $filesystem->findShortestPathCode($vendorPath, realpath($targetDir), true);

        $appBaseDirCode = $filesystem->findShortestPathCode($vendorPath, getcwd(), true);
        $appBaseDirCode = str_replace('__DIR__', '$vendorDir', $appBaseDirCode);

        $namespacesFile = <<<EOF
<?php

// autoload_namespace.php generated by Composer

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        $packageMap = $this->buildPackageMap($installationManager, $mainPackage, $localRepo->getPackages());
        $autoloads = $this->parseAutoloads($packageMap);

        foreach ($autoloads['psr-0'] as $namespace => $paths) {
            $exportedPaths = array();
            foreach ($paths as $path) {
                $exportedPaths[] = $this->getPathCode($filesystem, $relVendorPath, $vendorPath, $path);
            }
            $exportedPrefix = var_export($namespace, true);
            $namespacesFile .= "    $exportedPrefix => ";
            if (count($exportedPaths) > 1) {
                $namespacesFile .= "array(".implode(', ', $exportedPaths)."),\n";
            } else {
                $namespacesFile .= $exportedPaths[0].",\n";
            }
        }
        $namespacesFile .= ");\n";

        $classmapFile = <<<EOF
<?php

// autoload_classmap.php generated by Composer

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        // add custom psr-0 autoloading if the root package has a target dir
        $targetDirLoader = null;
        $mainAutoload = $mainPackage->getAutoload();
        if ($mainPackage->getTargetDir() && $mainAutoload['psr-0']) {
            $levels = count(explode('/', trim(strtr($mainPackage->getTargetDir(), '\\', '/'), '/')));
            $prefixes = implode(', ', array_map(function ($prefix) {
                return var_export($prefix, true);
            }, array_keys($mainAutoload['psr-0'])));
            $baseDirFromVendorDirCode = $filesystem->findShortestPathCode($vendorPath, getcwd(), true);

            $targetDirLoader = <<<EOF
    spl_autoload_register(function(\$class) {
        \$dir = $baseDirFromVendorDirCode . '/';
        \$prefixes = array($prefixes);
        foreach (\$prefixes as \$prefix) {
            if (0 !== strpos(\$class, \$prefix)) {
                continue;
            }
            \$path = \$dir . implode('/', array_slice(explode('\\\\', \$class), $levels)).'.php';
            if (!\$path = stream_resolve_include_path(\$path)) {
                return false;
            }
            require \$path;

            return true;
        }
    });


EOF;
        }

        // flatten array
        $autoloads['classmap'] = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($autoloads['classmap']));
        foreach ($autoloads['classmap'] as $dir) {
            foreach (ClassMapGenerator::createMap($dir) as $class => $path) {
                $path = '/'.$filesystem->findShortestPath(getcwd(), $path, true);
                $classmapFile .= '    '.var_export($class, true).' => $baseDir . '.var_export($path, true).",\n";
            }
        }
        $classmapFile .= ");\n";

        $filesCode = "";
        $autoloads['files'] = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($autoloads['files']));
        foreach ($autoloads['files'] as $functionFile) {
            $filesCode .= '    require __DIR__ . '. var_export('/'.$filesystem->findShortestPath($vendorPath, $functionFile), true).";\n";
        }

        file_put_contents($targetDir.'/autoload_namespaces.php', $namespacesFile);
        file_put_contents($targetDir.'/autoload_classmap.php', $classmapFile);
        if ($includePathFile = $this->getIncludePathsFile($packageMap, $filesystem, $relVendorPath, $vendorPath, $vendorPathCode, $appBaseDirCode)) {
            file_put_contents($targetDir.'/include_paths.php', $includePathFile);
        }
        file_put_contents($vendorPath.'/autoload.php', $this->getAutoloadFile($vendorPathToTargetDirCode, true, true, (bool) $includePathFile, $targetDirLoader, $filesCode));
        copy(__DIR__.'/ClassLoader.php', $targetDir.'/ClassLoader.php');
    }

    public function buildPackageMap(InstallationManager $installationManager, PackageInterface $mainPackage, array $packages)
    {
        // build package => install path map
        $packageMap = array();

        // add main package
        $packageMap[] = array($mainPackage, '');

        foreach ($packages as $package) {
            if ($package instanceof AliasPackage) {
                continue;
            }
            $packageMap[] = array(
                $package,
                $installationManager->getInstallPath($package)
            );
        }

        return $packageMap;
    }

    /**
     * Compiles an ordered list of namespace => path mappings
     *
     * @param  array $packageMap array of array(package, installDir-relative-to-composer.json)
     * @return array array('psr-0' => array('Ns\\Foo' => array('installDir')))
     */
    public function parseAutoloads(array $packageMap)
    {
        $autoloads = array('classmap' => array(), 'psr-0' => array(), 'files' => array());
        foreach ($packageMap as $item) {
            list($package, $installPath) = $item;

            if (null !== $package->getTargetDir()) {
                $installPath = substr($installPath, 0, -strlen('/'.$package->getTargetDir()));
            }

            foreach ($package->getAutoload() as $type => $mapping) {
                // skip misconfigured packages
                if (!is_array($mapping)) {
                    continue;
                }
                foreach ($mapping as $namespace => $paths) {
                    foreach ((array) $paths as $path) {
                        $autoloads[$type][$namespace][] = empty($installPath) ? $path : $installPath.'/'.$path;
                    }
                }
            }
        }

        foreach ($autoloads as $type => $maps) {
            krsort($autoloads[$type]);
        }

        return $autoloads;
    }

    /**
     * Registers an autoloader based on an autoload map returned by parseAutoloads
     *
     * @param  array       $autoloads see parseAutoloads return value
     * @return ClassLoader
     */
    public function createLoader(array $autoloads)
    {
        $loader = new ClassLoader();

        if (isset($autoloads['psr-0'])) {
            foreach ($autoloads['psr-0'] as $namespace => $path) {
                $loader->add($namespace, $path);
            }
        }

        return $loader;
    }

    protected function getIncludePathsFile(array $packageMap, Filesystem $filesystem, $relVendorPath, $vendorPath, $vendorPathCode, $appBaseDirCode)
    {
        $includePaths = array();

        foreach ($packageMap as $item) {
            list($package, $installPath) = $item;

            if (null !== $package->getTargetDir() && strlen($package->getTargetDir()) > 0) {
                $installPath = substr($installPath, 0, -strlen('/'.$package->getTargetDir()));
            }

            foreach ($package->getIncludePaths() as $includePath) {
                $includePath = trim($includePath, '/');
                $includePaths[] = empty($installPath) ? $includePath : $installPath.'/'.$includePath;
            }
        }

        if (!$includePaths) {
            return;
        }

        $includePathsFile = <<<EOF
<?php

// include_paths.php generated by Composer

\$vendorDir = $vendorPathCode;
\$baseDir = $appBaseDirCode;

return array(

EOF;

        foreach ($includePaths as $path) {
            $includePathsFile .= "    " . $this->getPathCode($filesystem, $relVendorPath, $vendorPath, $path) . ",\n";
        }

        return $includePathsFile . ");\n";
    }

    protected function getPathCode(Filesystem $filesystem, $relVendorPath, $vendorPath, $path)
    {
        $path = strtr($path, '\\', '/');
        $baseDir = '';
        if (!$filesystem->isAbsolutePath($path)) {
            if (strpos($path, $relVendorPath) === 0) {
                // path starts with vendor dir
                $path = substr($path, strlen($relVendorPath));
                $baseDir = '$vendorDir . ';
            } else {
                $path = '/'.$path;
                $baseDir = '$baseDir . ';
            }
        } elseif (strpos($path, $vendorPath) === 0) {
            $path = substr($path, strlen($vendorPath));
            $baseDir = '$vendorDir . ';
        }

        return $baseDir.var_export($path, true);
    }

    protected function getAutoloadFile($vendorPathToTargetDirCode, $usePSR0, $useClassMap, $useIncludePath, $targetDirLoader, $filesCode)
    {
        if ($filesCode) {
            $filesCode = "\n".$filesCode;
        }

        $file = <<<HEADER
<?php

// autoload.php generated by Composer
if (!class_exists('Composer\\\\Autoload\\\\ClassLoader', false)) {
    require $vendorPathToTargetDirCode . '/ClassLoader.php';
}

return call_user_func(function() {
    \$loader = new \\Composer\\Autoload\\ClassLoader();
    \$composerDir = $vendorPathToTargetDirCode;


HEADER;

        if ($useIncludePath) {
            $file .= <<<'INCLUDE_PATH'
    $includePaths = require $composerDir . '/include_paths.php';
    array_unshift($includePaths, get_include_path());
    set_include_path(join(PATH_SEPARATOR, $includePaths));


INCLUDE_PATH;
        }

        if ($usePSR0) {
            $file .= <<<'PSR0'
    $map = require $composerDir . '/autoload_namespaces.php';
    foreach ($map as $namespace => $path) {
        $loader->add($namespace, $path);
    }


PSR0;
        }

        if ($useClassMap) {
            $file .= <<<'CLASSMAP'
    $classMap = require $composerDir . '/autoload_classmap.php';
    if ($classMap) {
        $loader->addClassMap($classMap);
    }


CLASSMAP;
        }

        $file .= $targetDirLoader;

        return $file . <<<FOOTER
    \$loader->register();
$filesCode
    return \$loader;
});

FOOTER;
    }
}
