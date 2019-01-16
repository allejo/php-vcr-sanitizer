<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

use VCR\VCR;

abstract class VCRCleaner
{
    /**
     * Enable the VCR cleaner to sanitize recordings before they are recorded
     * and enable sanitized recordings to be used in cassettes.
     *
     * ```
     * $options = [
     *   'request' => [
     *     'ignoreHostname'    => boolean
     *     'ignoreQueryFields' => string[]
     *     'ignoreHeaders'     => string[]
     *     'bodyScrubbers'     => Array<(string $body): string>
     *   ],
     *   'response' => [
     *     'ignoreHeaders'     => string[]
     *     'bodyScrubbers'     => Array<(string $body): string>
     *   ],
     * ];
     * ```
     *
     * @param array $options An array with the defined structure. All settings
     *                       have default values.
     */
    public static function enable(array $options)
    {
        Config::configureOptions($options);

        VCR::configure()
            ->addRequestMatcher('headers', array('allejo\VCR\RelaxedRequestMatcher', 'matchHeaders'))
            ->addRequestMatcher('host', array('allejo\VCR\RelaxedRequestMatcher', 'matchHost'))
            ->addRequestMatcher('query_string', array('allejo\VCR\RelaxedRequestMatcher', 'matchQueryString'))
            ->addRequestMatcher('body', array('allejo\VCR\RelaxedRequestMatcher', 'matchBody'))
        ;

        VCR::getEventDispatcher()
            ->addSubscriber(new VCRCleanerEventSubscriber())
        ;
    }
}
