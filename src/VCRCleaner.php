<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

use VCR\VCR;

class VCRCleaner
{
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
