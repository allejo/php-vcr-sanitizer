<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR\Tests;

use allejo\VCR\Config;
use allejo\VCR\RelaxedRequestMatcher;
use VCR\Request;
use VCR\RequestMatcher;

class RelaxedRequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testRelaxedRequestMatcherQueryHost()
    {
        $actualRequest = new Request('GET', 'http://example.com/api/v1?query=users');
        $cleanRequest = new Request('GET', 'http://[]/api/v1?query=users');

        Config::configureOptions(array(
            'request' => array(
                'ignoreHostname' => true,
            ),
        ));

        $this->assertFalse(RequestMatcher::matchHost($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchHost($actualRequest, $cleanRequest));
    }

    public function testRelaxedRequestMatcherQueryString()
    {
        $actualRequest = new Request('GET', 'http://example.com/api/v1?query=users&apiKey=SomethingSensitive');
        $cleanRequest = new Request('GET', 'http://example.com/api/v1?query=users');

        Config::configureOptions(array(
            'request' => array(
                'ignoreQueryFields' => array('apiKey'),
            ),
        ));

        $this->assertFalse(RequestMatcher::matchQueryString($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchQueryString($actualRequest, $cleanRequest));
    }

    public function testRelaxedRequestMatcherHeaders()
    {
        $actualRequest = new Request('GET', 'http://example.com/api/v1', array(
            'X-API-KEY' => 'SomethingSensitive',
            'X-Header'  => 'something-not-secret',
        ));
        $cleanRequest = new Request('GET', 'http://example.com/api/v1', array(
            'X-Header' => 'something-not-secret',
        ));

        Config::configureOptions(array(
            'request' => array(
                'ignoreHeaders' => array('X-API-KEY'),
            ),
        ));

        $this->assertFalse(RequestMatcher::matchHeaders($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchHeaders($actualRequest, $cleanRequest));
    }

    public function testRelaxedRequestMatcherBody()
    {
        $actualRequest = Request::fromArray(
            array(
                'method' => 'POST',
                'url'    => 'http://example.com/api/v2',
                'body'   => 'This is not secret, but this is SuperSecret',
            )
        );

        $cleanRequest = Request::fromArray(
            array(
                'method' => 'POST',
                'url'    => 'http://example.com/api/v2',
                'body'   => 'This is not secret, but this is ',
            )
        );

        Config::configureOptions(array(
            'request' => array(
                'bodyScrubbers' => array(
                    function ($body) {
                        return str_replace('SuperSecret', '', $body);
                    },
                ),
            ),
        ));

        $this->assertFalse(RequestMatcher::matchBody($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchBody($actualRequest, $cleanRequest));
    }
}
