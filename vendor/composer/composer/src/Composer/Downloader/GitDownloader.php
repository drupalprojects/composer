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

namespace Composer\Downloader;

use Composer\Package\PackageInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class GitDownloader extends VcsDownloader
{
    /**
     * {@inheritDoc}
     */
    public function doDownload(PackageInterface $package, $path)
    {
        $ref = $package->getSourceReference();
        $command = 'git clone %s %s && cd %2$s && git checkout %3$s && git reset --hard %3$s && git remote add composer %1$s';
        $this->io->write("    Cloning ".$package->getSourceReference());

        $commandCallable = function($url) use ($ref, $path, $command) {
            return sprintf($command, escapeshellarg($url), escapeshellarg($path), escapeshellarg($ref));
        };

        $this->runCommand($commandCallable, $package->getSourceUrl(), $path);
        $this->setPushUrl($package, $path);
    }

    /**
     * {@inheritDoc}
     */
    public function doUpdate(PackageInterface $initial, PackageInterface $target, $path)
    {
        $ref = $target->getSourceReference();
        $this->io->write("    Checking out ".$target->getSourceReference());
        $command = 'cd %s && git remote set-url composer %s && git fetch composer && git fetch --tags composer && git checkout %3$s && git reset --hard %3$s';

        // capture username/password from github URL if there is one
        $this->process->execute(sprintf('cd %s && git remote -v', escapeshellarg($path)), $output);
        if (preg_match('{^composer\s+https://(.+):(.+)@github.com/}im', $output, $match)) {
            $this->io->setAuthorization('github.com', $match[1], $match[2]);
        }

        // TODO: BC for the composer remote that didn't exist, to be remove after May 18th.
        $this->process->execute(sprintf('cd %s && git remote add composer %s', escapeshellarg($path), escapeshellarg($initial->getSourceUrl())), $ignoredOutput);

        $commandCallable = function($url) use ($ref, $path, $command) {
            return sprintf($command, escapeshellarg($path), escapeshellarg($url), escapeshellarg($ref));
        };

        $this->runCommand($commandCallable, $target->getSourceUrl());
    }

    /**
     * {@inheritDoc}
     */
    protected function enforceCleanDirectory($path)
    {
        $command = sprintf('cd %s && git status --porcelain --untracked-files=no', escapeshellarg($path));
        if (0 !== $this->process->execute($command, $output)) {
            throw new \RuntimeException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput());
        }

        if (trim($output)) {
            throw new \RuntimeException('Source directory ' . $path . ' has uncommitted changes');
        }
    }

    /**
     * Runs a command doing attempts for each protocol supported by github.
     *
     * @param  callable          $commandCallable A callable building the command for the given url
     * @param  string            $url
     * @param  string            $path            The directory to remove for each attempt (null if not needed)
     * @throws \RuntimeException
     */
    protected function runCommand($commandCallable, $url, $path = null)
    {
        $handler = array($this, 'outputHandler');

        // github, autoswitch protocols
        if (preg_match('{^(?:https?|git)(://github.com/.*)}', $url, $match)) {
            $protocols = array('git', 'https', 'http');
            $messages = array();
            foreach ($protocols as $protocol) {
                $url = $protocol . $match[1];
                if (0 === $this->process->execute(call_user_func($commandCallable, $url), $handler)) {
                    return;
                }
                $messages[] = '- ' . $url . "\n" . preg_replace('#^#m', '  ', $this->process->getErrorOutput());
                if (null !== $path) {
                    $this->filesystem->removeDirectory($path);
                }
            }

            // failed to checkout, first check git accessibility
            $this->throwException('Failed to clone ' . $url .' via git, https and http protocols, aborting.' . "\n\n" . implode("\n", $messages), $url);
        }

        $command = call_user_func($commandCallable, $url);
        if (0 !== $this->process->execute($command, $handler)) {
            if (preg_match('{^git@github.com:(.+?)\.git$}i', $url, $match) && $this->io->isInteractive()) {
                // private repository without git access, try https with auth
                $retries = 3;
                $retrying = false;
                do {
                    if ($retrying) {
                        $this->io->write('Invalid credentials');
                    }
                    if (!$this->io->hasAuthorization('github.com') || $retrying) {
                        $username = $this->io->ask('Username: ');
                        $password = $this->io->askAndHideAnswer('Password: ');
                        $this->io->setAuthorization('github.com', $username, $password);
                    }

                    $auth = $this->io->getAuthorization('github.com');
                    $url = 'https://'.$auth['username'] . ':' . $auth['password'] . '@github.com/'.$match[1].'.git';

                    $command = call_user_func($commandCallable, $url);
                    if (0 === $this->process->execute($command, $handler)) {
                        return;
                    }
                    if (null !== $path) {
                        $this->filesystem->removeDirectory($path);
                    }
                    $retrying = true;
                } while (--$retries);
            }

            if (null !== $path) {
                $this->filesystem->removeDirectory($path);
            }
            $this->throwException('Failed to execute ' . $command . "\n\n" . $this->process->getErrorOutput(), $url);
        }
    }

    public function outputHandler($type, $buffer)
    {
        if ($type !== 'out') {
            return;
        }
        if ($this->io->isVerbose()) {
            $this->io->write($buffer, false);
        }
    }

    protected function throwException($message, $url)
    {
        if (0 !== $this->process->execute('git --version', $ignoredOutput)) {
            throw new \RuntimeException('Failed to clone '.$url.', git was not found, check that it is installed and in your PATH env.' . "\n\n" . $this->process->getErrorOutput());
        }

        throw new \RuntimeException($message);
    }

    protected function setPushUrl(PackageInterface $package, $path)
    {
        // set push url for github projects
        if (preg_match('{^(?:https?|git)://github.com/([^/]+)/([^/]+?)(?:\.git)?$}', $package->getSourceUrl(), $match)) {
            $pushUrl = 'git@github.com:'.$match[1].'/'.$match[2].'.git';
            $cmd = sprintf('git remote set-url --push origin %s', escapeshellarg($pushUrl));
            $this->process->execute($cmd, $ignoredOutput, $path);
        }
    }
}
