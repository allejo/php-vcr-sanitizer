<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

/**
 * @internal
 */
abstract class Config
{
    private static $options = array();

    public static function configureOptions(array $options)
    {
        self::$options = array_replace_recursive(self::defaultConfig(), $options);
    }

    public static function ignoreReqHostname()
    {
        return self::$options['request']['ignoreHostname'];
    }

    public static function getReqIgnoredQueryFields()
    {
        return self::$options['request']['ignoreQueryFields'];
    }

    public static function getReqIgnoredHeaders()
    {
        return self::$options['request']['ignoreHeaders'];
    }

    public static function getReqBodyScrubbers()
    {
        return self::$options['request']['bodyScrubbers'];
    }

    public static function getReqPostFieldScrubbers()
    {
        return self::$options['request']['postFieldScrubbers'];
    }

    public static function getResIgnoredHeaders()
    {
        return self::$options['response']['ignoreHeaders'];
    }

    public static function getResBodyScrubbers()
    {
        return self::$options['response']['bodyScrubbers'];
    }

    private static function defaultConfig()
    {
        return array(
            'request' => array(
                'ignoreHostname'    => false,
                'ignoreQueryFields' => array(),
                'ignoreHeaders'     => array(),
                'bodyScrubbers'     => array(),
            ),
            'response' => array(
                'ignoreHeaders'     => array(),
                'bodyScrubbers'     => array(),
            ),
        );
    }
}
