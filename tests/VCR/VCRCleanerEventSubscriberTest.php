<?php

namespace allejo\VCR\Tests;

use allejo\VCR\VCRCleaner;
use Curl\Curl;
use org\bovigo\vfs\vfsStream;
use VCR\VCR;

class VCRCleanerEventSubscriberTest extends \PHPUnit_Framework_TestCase
{
    private $root;

    public function setUp()
    {
        $this->root = vfsStream::setup('root');

        vfsStream::create([
            'fixtures' => [],
        ], $this->root);

        $vURL = vfsStream::url('root/fixtures/');

        VCR::configure()
            ->setCassettePath($vURL)
            ->setStorage('yaml')
        ;

        VCR::turnOn();
        VCR::insertCassette('cassette.yml');
    }

    public function tearDown()
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function testCurlCallWithSensitiveUrlParameter()
    {
        $newFile = $this->getCassetteContent();

        $this->assertEmpty($newFile);

        VCRCleaner::enable(array(
            'ignoreUrlParameters' => 'apiKey',
        ));

        $curl = new Curl();
        $curl->get('https://www.example.com/search', array(
            'apiKey' => 'somethingSensitive',
            'q' => 'keyword',
        ));

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('somethingSensitive', $vcrFile);
        $this->assertContains('www.example.com/search', $vcrFile);
    }

    private function getCassetteContent()
    {
        return file_get_contents(vfsStream::url('root/fixtures/cassette.yml'));
    }
}
