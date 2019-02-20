<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

use VCR\Request;

/**
 * @internal
 */
abstract class RelaxedRequestMatcher
{
    public static function matchQueryString(Request $first, Request $second)
    {
        $firstUrl = parse_url($first->getUrl());
        $secondUrl = parse_url($second->getUrl());

        $firstQuery = array();
        $secondQuery = array();

        if (!isset($firstUrl['query'])) {
            $firstUrl['query'] = '';
        }

        if (!isset($secondUrl['query'])) {
            $secondUrl['query'] = '';
        }

        parse_str($firstUrl['query'], $firstQuery);
        parse_str($secondUrl['query'], $secondQuery);

        foreach (Config::getReqIgnoredQueryFields() as $parameter) {
            unset($firstQuery[$parameter]);
            unset($secondQuery[$parameter]);
        }

        return $firstQuery === $secondQuery;
    }

    public static function matchHost(Request $first, Request $second)
    {
        if (Config::ignoreReqHostname()) {
            return true;
        }

        return $first->getHost() === $second->getHost();
    }

    public static function matchHeaders(Request $first, Request $second)
    {
        $firstHeaders = $first->getHeaders();
        $secondHeaders = $second->getHeaders();

        foreach (Config::getReqIgnoredHeaders() as $parameter) {
            unset($firstHeaders[$parameter]);
            unset($secondHeaders[$parameter]);
        }

        return $firstHeaders === $secondHeaders;
    }

    public static function matchBody(Request $first, Request $second)
    {
        $bodyScrubbers = Config::getReqBodyScrubbers();
        $firstBody = $first->getBody();
        $secondBody = $second->getBody();

        foreach ($bodyScrubbers as $bodyScrubber) {
            $firstBody = $bodyScrubber($firstBody);
            $secondBody = $bodyScrubber($secondBody);
        }

        return $firstBody === $secondBody;
    }
}
