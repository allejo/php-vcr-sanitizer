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
use PHPUnit\Framework\TestCase;
use VCR\VCR;

class VCRCleanerEventSubscriberTest extends TestCase
{
    /** @var MockWebServer */
    private static $server;

    /** @var Curl */
    private $curl;

    public static function setUpBeforeClass(): void
    {
        // It's important to configure and start the mock web server first before anything else. I have no idea why,
        // but in PHP 8.0+ I get the following error if I don't:
        //
        // > PHP Warning:  proc_open(): Unable to copy file descriptor 12 (for pipe) into file descriptor 1: Bad file descriptor
        //
        // This warning is the root cause of getting another exception:
        //
        // > donatj\MockWebServer\Exceptions\ServerException: Failed to start server. Is something already running on port 56067?
        self::$server = new MockWebServer();
        self::$server->start();
        self::$server->setResponseOfPath(
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

    public static function tearDownAfterClass(): void
    {
        VCR::eject();
        VCR::turnOff();

        self::$server->stop();
    }

    public function setUp(): void
    {
        // Clear the file
        file_put_contents(vfsStream::url('root/fixtures/cassette.yml'), '');

        $newFile = $this->getCassetteContent();
        $this->assertEmpty($newFile);

        $this->curl = new Curl(self::$server->getServerRoot());
    }

    public function tearDown(): void
    {
        // Remove any settings from tests
        VCRCleaner::enable(array());
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

        $this->assertStringNotContainsString('somethingSensitive', $vcrFile);
        $this->assertStringContainsString($this->getApiUrl(), $vcrFile);
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

        $this->assertStringNotContainsString('SuperToast', $vcrFile);
        $this->assertStringContainsString('X-Api-Key', $vcrFile);
        $this->assertStringContainsString('X-Type', $vcrFile);
        $this->assertStringContainsString('application/vcr', $vcrFile);
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

        $this->assertStringContainsString('X-Api-Key', $vcrFile);
        $this->assertStringNotContainsString('SuperToast', $vcrFile);
        $this->assertStringContainsString('X-Type', $vcrFile);
        $this->assertStringNotContainsString('application/vcr', $vcrFile);
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

        $this->assertStringContainsString('X-Type', $vcrFile);
        $this->assertStringContainsString('application/vcr', $vcrFile);
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

        $this->assertStringNotContainsString('SuperToast', $vcrFile);
        $this->assertStringNotContainsString('somethingSensitive', $vcrFile);
        $this->assertStringNotContainsString('apiKey', $vcrFile);
        $this->assertStringContainsString('q=keyword', $vcrFile);
        $this->assertStringContainsString('X-Api-Key', $vcrFile);
        $this->assertStringContainsString('X-Type', $vcrFile);
        $this->assertStringContainsString('application/vcr', $vcrFile);
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

        $this->assertStringNotContainsString('VerySecret', $vcrFile);
        $this->assertStringContainsString('REDACTED', $vcrFile);
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

        $this->assertStringNotContainsString($secret, $vcrFile);
        $this->assertStringContainsString('REDACTED', $vcrFile);
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

        $this->assertDoesNotMatchRegularExpression(sprintf("/^\s+(?<!local_ip:)\s*%s/", preg_quote(self::$server->getHost(), '/')), $vcrFile);
        $this->assertStringContainsString(sprintf('http://[]:%d/search', self::$server->getPort()), $vcrFile);
        $this->assertStringContainsString("Host: ''", $vcrFile);
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

        $this->assertStringNotContainsString("Host: ''", $vcrFile);
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

        $this->assertStringNotContainsString("X-Cache: 'true'", $vcrFile, '', true);
        $this->assertStringContainsString('X-Cache: null', $vcrFile, '', true);
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

        $this->assertStringNotContainsString('X-Cache', $vcrFile, '', true);
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

        $this->assertStringNotContainsString('{"status":', $vcrFile);
    }
}
