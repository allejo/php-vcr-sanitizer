<?php

namespace allejo\VCR\Tests;

use allejo\VCR\RelaxedRequestMatcher;
use VCR\Request;
use VCR\RequestMatcher;

class RelaxedRequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testRelaxedRequestMatcherUrl()
    {
        $actualRequest = new Request('GET', 'http://example.com/api/v1?query=users&apiKey=SomethingSensitive');
        $cleanRequest = new Request('GET', 'http://example.com/api/v1?query=users');

        RelaxedRequestMatcher::configureOptions([
            'ignoreUrlParameters' => ['apiKey']
        ]);

        $this->assertFalse(RequestMatcher::matchQueryString($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchQueryString($actualRequest, $cleanRequest));
    }

    public function testRelaxedRequestMatcherHost()
    {
        $actualRequest = new Request('GET', 'http://example.com/api/v1', array(
            'X-API-KEY' => 'SomethingSensitive',
            'X-Header' => 'something-not-secret',
        ));
        $cleanRequest = new Request('GET', 'http://example.com/api/v1', array(
            'X-Header' => 'something-not-secret',
        ));

        RelaxedRequestMatcher::configureOptions([
            'ignoreHeaders' => ['X-API-KEY']
        ]);

        $this->assertFalse(RequestMatcher::matchHeaders($actualRequest, $cleanRequest));
        $this->assertTrue(RelaxedRequestMatcher::matchHeaders($actualRequest, $cleanRequest));
    }
}
