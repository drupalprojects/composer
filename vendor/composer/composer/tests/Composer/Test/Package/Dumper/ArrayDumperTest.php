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
use Composer\Package\Link;
use Composer\Package\LinkConstraint\VersionConstraint;

class ArrayDumperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ArrayDumper
     */
    private $dumper;
    /**
     * @var \Composer\Package\PackageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $package;

    public function setUp()
    {
        $this->dumper = new ArrayDumper();
        $this->package = $this->getMock('Composer\Package\PackageInterface');
    }

    public function testRequiredInformation()
    {
        $this
            ->packageExpects('getPrettyName', 'foo')
            ->packageExpects('getPrettyVersion', '1.0')
            ->packageExpects('getVersion', '1.0.0.0');

        $config = $this->dumper->dump($this->package);
        $this->assertEquals(
            array(
                'name' => 'foo',
                'version' => '1.0',
                'version_normalized' => '1.0.0.0'
            ),
            $config
        );
    }

    /**
     * @dataProvider getKeys
     */
    public function testKeys($key, $value, $method = null, $expectedValue = null)
    {
        $this->packageExpects('get'.ucfirst($method ?: $key), $value);

        $config = $this->dumper->dump($this->package);

        $this->assertSame($expectedValue ?: $value, $config[$key]);
    }

    public function getKeys()
    {
        return array(
            array(
                'type',
                'library'
            ),
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
            array(
                'support',
                array('foo' => 'bar'),
            )
        );
    }

    private function packageExpects($method, $value)
    {
        $this->package
            ->expects($this->any())
            ->method($method)
            ->will($this->returnValue($value));

        return $this;
    }
}
