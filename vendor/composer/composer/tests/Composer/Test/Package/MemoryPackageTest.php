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

namespace Composer\Test\Package;

use Composer\Package\MemoryPackage;
use Composer\Package\Version\VersionParser;
use Composer\Test\TestCase;

class MemoryPackageTest extends TestCase
{
    /**
     * Memory package naming, versioning, and marshalling semantics provider
     *
     * demonstrates several versioning schemes
     */
    public function providerVersioningSchemes()
    {
        $provider[] = array('foo',              '1-beta');
        $provider[] = array('node',             '0.5.6');
        $provider[] = array('li3',              '0.10');
        $provider[] = array('mongodb_odm',      '1.0.0BETA3');
        $provider[] = array('DoctrineCommon',   '2.2.0-DEV');

        return $provider;
    }

    /**
     * Tests memory package naming semantics
     * @dataProvider providerVersioningSchemes
     */
    public function testMemoryPackageHasExpectedNamingSemantics($name, $version)
    {
        $versionParser = new VersionParser();
        $normVersion = $versionParser->normalize($version);
        $package = new MemoryPackage($name, $normVersion, $version);
        $this->assertEquals(strtolower($name), $package->getName());
    }

    /**
     * Tests memory package versioning semantics
     * @dataProvider providerVersioningSchemes
     */
    public function testMemoryPackageHasExpectedVersioningSemantics($name, $version)
    {
        $versionParser = new VersionParser();
        $normVersion = $versionParser->normalize($version);
        $package = new MemoryPackage($name, $normVersion, $version);
        $this->assertEquals($version, $package->getPrettyVersion());
        $this->assertEquals($normVersion, $package->getVersion());
    }

    /**
     * Tests memory package marshalling/serialization semantics
     * @dataProvider providerVersioningSchemes
     */
    public function testMemoryPackageHasExpectedMarshallingSemantics($name, $version)
    {
        $versionParser = new VersionParser();
        $normVersion = $versionParser->normalize($version);
        $package = new MemoryPackage($name, $normVersion, $version);
        $this->assertEquals(strtolower($name).'-'.$normVersion, (string) $package);
    }

}
