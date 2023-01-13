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
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use org\bovigo\vfs\vfsStream;
use VCR\VCR;

class VCRCleanerEventSubscriberTest extends \PHPUnit_Framework_TestCase
{
    /** @var MockWebServer */
    private $server;

    /** @var Curl */
    private $curl;

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

        $this->server = new MockWebServer();
        $this->server->start();

        $this->server->setResponseOfPath(
            '/search',
            new Response(
                '{"status": 200}',
                array(
                    'Content-Type' => 'application/json',
                    'X-Cache'      => 'true',
                ),
                200
            )
        );
        $this->server->start();

        $this->curl = new Curl($this->server->getServerRoot());
    }

    public function tearDown()
    {
        // Remove any settings from tests
        VCRCleaner::enable(array());

        $this->server->stop();
    }

    private function getCassetteContent()
    {
        return file_get_contents(vfsStream::url('root/fixtures/cassette.yml'));
    }

    private function getApiUrl()
    {
        return '/search';
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

        $this->curl->get($this->getApiUrl(), array(
            'apiKey' => 'somethingSensitive',
            'q'      => 'keyword',
        ));

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('somethingSensitive', $vcrFile);
        $this->assertContains($this->getApiUrl(), $vcrFile);
    }

    public function testCurlCallWithSensitiveHeaders()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHeaders' => array('X-Api-Key'),
            ),
        ));

        $this->curl->setHeader('X-Api-Key', 'SuperToast');
        $this->curl->setHeader('X-Type', 'application/vcr');
        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('SuperToast', $vcrFile);
        $this->assertContains('X-Api-Key', $vcrFile);
        $this->assertContains('X-Type', $vcrFile);
        $this->assertContains('application/vcr', $vcrFile);
    }

    public function testCurlCallWithWildcardSensitiveHeaders()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHeaders' => array('*'),
            ),
        ));

        $this->curl->setHeader('X-Api-Key', 'SuperToast');
        $this->curl->setHeader('X-Type', 'application/vcr');
        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertContains('X-Api-Key', $vcrFile);
        $this->assertNotContains('SuperToast', $vcrFile);
        $this->assertContains('X-Type', $vcrFile);
        $this->assertNotContains('application/vcr', $vcrFile);
    }

    public function testCurlCallWithSensitiveHeadersThatDontExist()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHeaders' => array('X-Api-Key'),
            ),
            'response' => array(
                'ignoreHeaders' => array('X-Whatever'),
            ),
        ));

        $this->curl->setHeader('X-Type', 'application/vcr');
        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

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

        $this->curl->setHeader('X-Api-Key', 'SuperToast');
        $this->curl->setHeader('X-Type', 'application/vcr');
        $this->curl->get($this->getApiUrl(), array(
            'apiKey' => 'somethingSensitive',
            'q'      => 'keyword',
        ));

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

        $this->curl->post($this->getApiUrl(), 'SomethingPublic SomethingVerySecret');

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('VerySecret', $vcrFile);
        $this->assertContains('REDACTED', $vcrFile);
    }

    public function testCurlCallWithSensitivePostField()
    {
        $cb = function (array $postFields) {
            $postFields['VerySecret'] = 'REDACTED';

            return $postFields;
        };

        VCRCleaner::enable(array(
            'request' => array(
                'postFieldScrubbers' => array(
                    $cb,
                ),
            ),
        ));

        $secret = 'Do not tell anyone this secret';
        $postFields = array(
            'SomethingPublic' => 'Not a secret',
            'VerySecret'      => $secret,
        );
        $this->curl->setOpt(CURLOPT_POSTFIELDS, $postFields);
        $this->curl->post($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains($secret, $vcrFile);
        $this->assertContains('REDACTED', $vcrFile);
    }

    public function testCurlCallWithRedactedHostname()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHostname' => true,
            ),
            'response' => array(
                'ignoreHeaders' => array(
                    'Host',
                ),
            ),
        ));

        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotRegExp(sprintf("/^\s+(?<!local_ip:)\s*%s/", preg_quote($this->server->getHost(), '/')), $vcrFile);
        $this->assertContains(sprintf('http://[]:%d/search', $this->server->getPort()), $vcrFile);
        $this->assertContains("Host: ''", $vcrFile);
    }

    public function testCurlCallWithoutRedactedHostname()
    {
        VCRCleaner::enable(array(
            'request' => array(
                'ignoreHostname' => false,
            ),
        ));

        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains("Host: ''", $vcrFile);
    }

    public function testCurlCallToModifyResponseHeaders()
    {
        VCRCleaner::enable(array(
            'response' => array(
                'ignoreHeaders' => array(
                    'X-Cache',
                ),
            ),
        ));

        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains("X-Cache: 'true'", $vcrFile, '', true);
        $this->assertContains('X-Cache: null', $vcrFile, '', true);
    }

    public function testCurlCallToModifyWildcardResponseHeaders()
    {
        VCRCleaner::enable(array(
            'response' => array(
                'ignoreHeaders' => array(
                    '*',
                ),
            ),
        ));

        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('X-Cache', $vcrFile, '', true);
    }

    public function testCurlCallToModifyResponseBody()
    {
        // Remove the avatar attribute from a response
        $cb = function ($bodyAsString) {
            return preg_replace('/\{"status":/', '', $bodyAsString);
        };

        VCRCleaner::enable(array(
            'response' => array(
                'bodyScrubbers' => array(
                    $cb,
                ),
            ),
        ));

        $this->curl->get($this->getApiUrl());

        $vcrFile = $this->getCassetteContent();

        $this->assertNotContains('{"status":', $vcrFile);
    }
}
