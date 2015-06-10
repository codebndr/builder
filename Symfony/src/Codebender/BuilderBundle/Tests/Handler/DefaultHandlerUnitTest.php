<?php
/**
 * Created by PhpStorm.
 * User: fpapadopou
 * Date: 4/14/15
 * Time: 4:27 PM
 */

namespace Codebender\BuilderBundle\Tests\Handler;

use Codebender\BuilderBundle\Handler\DefaultHandler;

class DefaultHandlerUnitTest extends \PHPUnit_Framework_TestCase
{
    public function testPostRawData() {
        $this->markTestIncomplete('Only functional tests apply to postRawData function');
    }

    public function testDetectHeadersInFile() {
        $handler = new DefaultHandler();

        $code = "/*\nThis is a comment\n*/\n#include <header.h>\n#include \"quotedHeader.h\"\nvoid setup(){\n\n}\n\nvoid loop(){\n\n}\n";

        $this->assertEquals(array('arrows' => array('header'), 'quotes' => array('quotedHeader')), $handler->detectHeadersInFile($code));
    }

    public function testDetectHeadersInFileEmptyInoProvided() {
        $handler = new DefaultHandler();

        $code = '';
        $this->assertEquals(array('arrows' => array(), 'quotes' => array()), $handler->detectHeadersInFile($code));
    }

    public function testReadLibraries() {
        $handler = $this->getMockBuilder('Codebender\BuilderBundle\Handler\DefaultHandler')
        ->setMethods(array('detectHeadersInFile'))
        ->getMock();

        $inoFile = array('filename' => 'project.ino', 'content' => '#include <header.h>\n#include <Ethernet.h>\n#include \"quotedHeader.h\"\nvoid setup(){\n\n}\n\nvoid loop(){\n\n}\n');
        $headerFile = array('filename' => 'quotedHeader.h', 'content' => '#define PIN 5\n#define PIN2 10');
        $projectFiles = array(
            $inoFile,
            $headerFile
        );

        $handler
            ->expects($this->once())
            ->method('detectHeadersInFile')
            ->with($inoFile['content'])
            ->will($this->returnValue(array('arrows' => array('header', 'Ethernet'), 'quotes' => array('quotedHeader'))));

        $this->assertTrue(is_array($handler->readlibraries($projectFiles)));
    }

    public function testReadLibrariesNoIno() {
        $handler = $this->getMockBuilder('Codebender\BuilderBundle\Handler\DefaultHandler')
            ->setMethods(array('detectHeadersInFile'))
            ->getMock();

        $headerFile = array('filename' => 'quotedHeader.h', 'content' => '#define PIN 5\n#define PIN2 10');
        $projectFiles = array(
            $headerFile
        );

        $handler
            ->expects($this->never())
            ->method('detectHeadersInFile');

        $this->assertEquals($handler->readlibraries($projectFiles), array());
    }
}
