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

    /** @var bool|null */
    private static $ignoreAllReqHeaders;

    /** @var bool|null */
    private static $ignoreAllResHeaders;

    /**
     * @return void
     */
    public static function configureOptions(array $options)
    {
        self::$options = array_replace_recursive(self::defaultConfig(), $options);

        self::$ignoreAllReqHeaders = in_array('*', self::getReqIgnoredHeaders(), true);
        self::$ignoreAllResHeaders = in_array('*', self::getResIgnoredHeaders(), true);

        if (self::$ignoreAllReqHeaders) {
            self::$options['request']['ignoreHeaders'] = array('*');
        }

        if (self::$ignoreAllResHeaders) {
            self::$options['response']['ignoreHeaders'] = array('*');
        }
    }

    /**
     * @return bool
     */
    public static function ignoreReqHostname()
    {
        return self::$options['request']['ignoreHostname'];
    }

    /**
     * @return bool
     */
    public static function ignoreAllReqHeaders()
    {
        return self::$ignoreAllReqHeaders === true;
    }

    /**
     * @return bool
     */
    public static function ignoreAllResHeaders()
    {
        return self::$ignoreAllResHeaders === true;
    }

    /**
     * @return string[]
     */
    public static function getReqIgnoredQueryFields()
    {
        return self::$options['request']['ignoreQueryFields'];
    }

    /**
     * @return string[]
     */
    public static function getReqIgnoredHeaders()
    {
        return self::$options['request']['ignoreHeaders'];
    }

    /**
     * @return array<callable(string): string>
     */
    public static function getReqBodyScrubbers()
    {
        return self::$options['request']['bodyScrubbers'];
    }

    /**
     * @return array<callable(string): string>
     */
    public static function getReqPostFieldScrubbers()
    {
        return self::$options['request']['postFieldScrubbers'];
    }

    /**
     * @return string[]
     */
    public static function getResIgnoredHeaders()
    {
        return self::$options['response']['ignoreHeaders'];
    }

    /**
     * @return array<callable(string): string>
     */
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
                'postFieldScrubbers'=> array(),
            ),
            'response' => array(
                'ignoreHeaders'     => array(),
                'bodyScrubbers'     => array(),
            ),
        );
    }
}
