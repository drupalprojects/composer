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

namespace Composer\Test\Package\Dumper;

use Composer\Package\Dumper\ArrayDumper;
use Composer\Package\MemoryPackage;
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;

class ArrayDumperTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->dumper = new ArrayDumper();
    }

    public function testRequiredInformations()
    {
        $package = new MemoryPackage('foo', '1.0.0.0', '1.0');

        $config = $this->dumper->dump($package);
        $this->assertEquals(array('name', 'version', 'version_normalized', 'type'), array_keys($config));
    }

    /**
     * @dataProvider getKeys
     */
    public function testKeys($key, $value, $method = null, $expectedValue = null)
    {
        $package = new MemoryPackage('foo', '1.0.0.0', '1.0');

        $setter = 'set'.ucfirst($method ?: $key);
        $package->$setter($value);

        $config = $this->dumper->dump($package);
        $this->assertArrayHasKey($key, $config);

        $expectedValue = $expectedValue ?: $value;
        $this->assertSame($expectedValue, $config[$key]);
    }

    public function getKeys()
    {
        return array(
            array(
                'time',
                new \DateTime('2012-02-01'),
                'ReleaseDate',
                '2012-02-01 00:00:00',
            ),
            array(
                'authors',
                array('Nils Adermann <naderman@naderman.de>', 'Jordi Boggiano <j.boggiano@seld.be>')
            ),
            array(
                'homepage',
                'http://getcomposer.org'
            ),
            array(
                'description',
                'Package Manager'
            ),
            array(
                'keywords',
                array('package', 'dependency', 'autoload')
            ),
            array(
                'bin',
                array('bin/composer'),
                'binaries'
            ),
            array(
                'license',
                array('MIT')
            ),
            array(
                'autoload',
                array('psr-0' => array('Composer' => 'src/'))
            ),
            array(
                'repositories',
                array('packagist' => false)
            ),
            array(
                'scripts',
                array('post-update-cmd' => 'MyVendor\\MyClass::postUpdate')
            ),
            array(
                'extra',
                array('class' => 'MyVendor\\Installer')
            ),
            array(
                'require',
                array(new Link('foo', 'foo/bar', new VersionConstraint('=', '1.0.0.0'), 'requires', '1.0.0')),
                'requires',
                array('foo/bar' => '1.0.0'),
            ),
            array(
                'require-dev',
                array(new Link('foo', 'foo/bar', new VersionConstraint('=', '1.0.0.0'), 'requires (for development)', '1.0.0')),
                'devRequires',
                array('foo/bar' => '1.0.0'),
            ),
            array(
                'suggest',
                array('foo/bar' => 'very useful package'),
                'suggests'
            ),
        );
    }
}
