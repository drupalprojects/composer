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

namespace Composer\Test\Downloader;

use Composer\Downloader\PearPackageExtractor;

class PearPackageExtractorTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldExtractPackage_1_0()
    {
        $extractor = $this->getMockForAbstractClass('Composer\Downloader\PearPackageExtractor', array(), '', false);
        $method = new \ReflectionMethod($extractor, 'buildCopyActions');
        $method->setAccessible(true);

        $fileActions = $method->invoke($extractor, __DIR__ . '/Fixtures/Package_v1.0', 'php');

        $expectedFileActions = array(
            0 => Array(
                'from' => 'PEAR_Frontend_Gtk-0.4.0/Gtk.php',
                'to' => 'PEAR/Frontend/Gtk.php',
            ),
            1 => Array(
                'from' => 'PEAR_Frontend_Gtk-0.4.0/Gtk/Config.php',
                'to' => 'PEAR/Frontend/Gtk/Config.php',
            ),
            2 => Array(
                'from' => 'PEAR_Frontend_Gtk-0.4.0/Gtk/xpm/black_close_icon.xpm',
                'to' => 'PEAR/Frontend/Gtk/xpm/black_close_icon.xpm',
            )
        );
        $this->assertSame($expectedFileActions, $fileActions);
    }

    public function testShouldExtractPackage_2_0()
    {
        $extractor = $this->getMockForAbstractClass('Composer\Downloader\PearPackageExtractor', array(), '', false);
        $method = new \ReflectionMethod($extractor, 'buildCopyActions');
        $method->setAccessible(true);

        $fileActions = $method->invoke($extractor, __DIR__ . '/Fixtures/Package_v2.0', 'php');

        $expectedFileActions = array(
            0 => Array(
                'from' => 'Net_URL-1.0.15/URL.php',
                'to' => 'Net/URL.php',
            )
        );
        $this->assertSame($expectedFileActions, $fileActions);
    }

    public function testShouldExtractPackage_2_1()
    {
        $extractor = $this->getMockForAbstractClass('Composer\Downloader\PearPackageExtractor', array(), '', false);
        $method = new \ReflectionMethod($extractor, 'buildCopyActions');
        $method->setAccessible(true);

        $fileActions = $method->invoke($extractor, __DIR__ . '/Fixtures/Package_v2.1', 'php');

        $expectedFileActions = array(
            0 => Array(
                'from' => 'Zend_Authentication-2.0.0beta4/php/Zend/Authentication/Storage/StorageInterface.php',
                'to' => '/php/Zend/Authentication/Storage/StorageInterface.php',
            ),
            1 => Array(
                'from' => 'Zend_Authentication-2.0.0beta4/php/Zend/Authentication/Result.php',
                'to' => '/php/Zend/Authentication/Result.php',
            )
        );
        $this->assertSame($expectedFileActions, $fileActions);
    }
}
