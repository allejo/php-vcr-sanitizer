<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR\Tests;

use allejo\VCR\VCRCleaner;
use Curl\Curl;
use org\bovigo\vfs\vfsStream;
use VCR\VCR;

class VCRCleanerEventSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        $root = vfsStream::setup('root');

        vfsStream::create(array(
            'fixtures' => array(
                'cassette.yml' => '',
            ),
        ), $root);

        $vURL = vfsStream::url('root/fixtures/');

        VCR::configure()
            ->setCassettePath($vURL)
            ->setStorage('yaml')
        ;

        VCR::turnOn();
        VCR::insertCassette('cassette.yml');
    }

    public static function tearDownAfterClass()
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function setUp()
    {
        // Clear the file
        file_put_contents(vfsStream::url('root/fixtures/cassette.yml'), '');

        $newFile = $this->getCassetteContent();
        $this->assertEmpty($newFile);
    }

    public function tearDown()
    {
        // Remove any settings from tests
        VCRCleaner::enable(array());
    }

    private function getCassetteContent()
    {
        return file_get_contents(vfsStream::url('root/fixtures/cassette.yml'));
    }

    public function testCurlCallWithSensitiveUrlParameter()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreQueryFields' => array(
                    'apiKey',
                ),
            ),
        ));

        $curl = new Curl();
        $curl->get('https://www.example.com/search', array(
            'apiKey' => 'somethingSensitive',
            'q'      => 'keyword',
        ));
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('somethingSensitive', $vcrFile);
        $this->assertContains('www.example.com/search', $vcrFile);
    }

    public function testCurlCallWithSensitiveHeaders()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHeaders' => array('X-Api-Key'),
            ),
        ));

        $curl = new Curl();
        $curl->setHeader('X-Api-Key', 'SuperToast');
        $curl->setHeader('X-Type', 'application/vcr');
        $curl->get('https://www.example.com/search');
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('SuperToast', $vcrFile);
        $this->assertContains('X-Api-Key', $vcrFile);
        $this->assertContains('X-Type', $vcrFile);
        $this->assertContains('application/vcr', $vcrFile);
    }

    public function testCurlCallWithSensitiveUrlParametersAndHeaders()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreQueryFields' => array('apiKey'),
                'ignoreHeaders'     => array('X-Api-Key'),
            ),
        ));

        $curl = new Curl();
        $curl->setHeader('X-Api-Key', 'SuperToast');
        $curl->setHeader('X-Type', 'application/vcr');
        $curl->get('https://www.example.com/search', array(
            'apiKey' => 'somethingSensitive',
            'q'      => 'keyword',
        ));
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('SuperToast', $vcrFile);
        $this->assertNotContains('somethingSensitive', $vcrFile);
        $this->assertNotContains('apiKey', $vcrFile);
        $this->assertContains('q=keyword', $vcrFile);
        $this->assertContains('X-Api-Key', $vcrFile);
        $this->assertContains('X-Type', $vcrFile);
        $this->assertContains('application/vcr', $vcrFile);
    }

    public function testCurlCallWithSensitiveBody()
    {
        $cb = function ($body) {
            return str_replace('VerySecret', 'REDACTED', $body);
        };

        VCRCleaner::enable(array(
            'request' => array(
                'bodyScrubbers' => array(
                    $cb,
                ),
            ),
        ));

        $curl = new Curl();
        $curl->post('https://www.example.com/search', 'SomethingPublic SomethingVerySecret');
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('VerySecret', $vcrFile);
        $this->assertContains('REDACTED', $vcrFile);
    }

    public function testCurlCallWithRedactedHostname()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHostname' => true,
            ),
        ));

        $curl = new Curl();
        $curl->get('https://www.example.com/search');
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('www.example.com', $vcrFile);
        $this->assertContains('https://[]/search', $vcrFile);
    }

    public function testCurlCallToModifyResponseHeaders()
    {
        VCRCleaner::enable(array(
            'response' => array(
                'ignoreHeaders' => array(
                    'X-Powered-By',
                ),
            ),
        ));

        $curl = new Curl();
        $curl->get('https://reqres.in/api/users/2');
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('X-Powered-By: Express', $vcrFile);
        $this->assertContains('X-Powered-By: null', $vcrFile);
    }

    public function testCurlCallToModifyResponseBody()
    {
        // Remove the avatar attribute from a response
        $cb = function ($bodyAsString) {
            $ws = json_decode($bodyAsString, true);
            unset($ws['data']['avatar']);

            return json_encode($ws);
        };

        VCRCleaner::enable(array(
            'response' => array(
                'bodyScrubbers' => array(
                    $cb,
                ),
            ),
        ));

        $curl = new Curl();
        $curl->get('https://reqres.in/api/users/2');
        $curl->close();

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('avatar', $vcrFile);
    }
}
