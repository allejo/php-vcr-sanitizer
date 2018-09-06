<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

use VCR\Request;

class RelaxedRequestMatcher
{
    private static $options = array();

    public static function configureOptions(array $options)
    {
        self::$options = array_merge_recursive(
            array(
                'ignoreUrlParameters' => array(),
                'ignoreHeaders'       => array(),
                'bodyScrubbers'       => array(),
            ),
            $options
        );
    }

    public static function getConfigurationOptions()
    {
        return self::$options;
    }

    public static function matchQueryString(Request $first, Request $second)
    {
        $firstUrl = parse_url($first->getUrl());
        $secondUrl = parse_url($second->getUrl());

        $domainEqual = ($firstUrl['scheme'] == $secondUrl['scheme']) && ($firstUrl['host'] && $secondUrl['host']);

        if (!$domainEqual) {
            return false;
        }

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

        foreach (self::$options['ignoreUrlParameters'] as $parameter) {
            unset($firstQuery[$parameter]);
            unset($secondQuery[$parameter]);
        }

        return $firstQuery === $secondQuery;
    }

    public static function matchHeaders(Request $first, Request $second)
    {
        $firstHeaders = $first->getHeaders();
        $secondHeaders = $second->getHeaders();

        foreach (self::$options['ignoreHeaders'] as $parameter) {
            unset($firstHeaders[$parameter]);
            unset($secondHeaders[$parameter]);
        }

        return $firstHeaders === $secondHeaders;
    }

    public static function matchBody(Request $first, Request $second)
    {
        $converters = self::$options['bodyScrubbers'];
        $firstBody = $first->getBody();
        $secondBody = $second->getBody();
        foreach ($converters as $converter) {
            $firstBody = $converter($firstBody);
            $secondBody = $converter($secondBody);
        }

        return $firstBody === $secondBody;
    }
}
