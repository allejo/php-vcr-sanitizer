<?php

namespace allejo\VCR;

use VCR\VCR;

class VCRCleaner
{
    public static function enable(array $options)
    {
        RelaxedRequestMatcher::configureOptions($options);

        VCR::configure()
            ->addRequestMatcher('headers', array('allejo\VCR\RelaxedRequestMatcher', 'matchHeaders'))
            ->addRequestMatcher('query_string', array('allejo\VCR\RelaxedRequestMatcher', 'matchQueryString'))
        ;

        VCR::getEventDispatcher()
            ->addSubscriber(new VCRCleanerEventSubscriber())
        ;
    }
}
