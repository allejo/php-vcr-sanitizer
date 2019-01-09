<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace allejo\VCR;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use VCR\Event\BeforeRecordEvent;
use VCR\Request;
use VCR\VCREvents;

class VCRCleanerEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            // This event is dispatched right before the response is recorded in our storage
            VCREvents::VCR_BEFORE_RECORD => 'onBeforeRecord',
        );
    }

    public function onBeforeRecord(BeforeRecordEvent $event)
    {
        $this->sanitizeRequestHost($event->getRequest());
        $this->sanitizeRequestUrl($event->getRequest());
        $this->sanitizeRequestHeaders($event->getRequest());
        $this->sanitizeRequestBody($event->getRequest());
    }

    private function sanitizeRequestHeaders(Request $request)
    {
        $options = RelaxedRequestMatcher::getConfigurationOptions();

        foreach ($options['ignoreHeaders'] as $header) {
            if ($request->hasHeader($header)) {
                $request->setHeader($header, null);
            }
        }
    }

    private function sanitizeRequestHost(Request $request)
    {
        $options = RelaxedRequestMatcher::getConfigurationOptions();

        if (!$options['ignoreHostname']) {
            return;
        }

        $url = parse_url($request->getUrl());
        $url['host'] = '[]';

        $newUrl = $this->rebuildUrl($url);
        $request->setUrl($newUrl);
        $request->setHeader('Host', null);
    }

    private function sanitizeRequestUrl(Request $request)
    {
        $options = RelaxedRequestMatcher::getConfigurationOptions();

        $url = parse_url($request->getUrl());

        if (!isset($url['query'])) {
            return;
        }

        $queryParts = array();
        parse_str($url['query'], $queryParts);

        foreach ($options['ignoreUrlParameters'] as $urlParameter) {
            unset($queryParts[$urlParameter]);
        }

        $url['query'] = http_build_query($queryParts);

        $newUrl = $this->rebuildUrl($url);

        $request->setUrl($newUrl);
    }

    private function sanitizeRequestBody(Request $request)
    {
        $body = $request->getBody();
        $options = RelaxedRequestMatcher::getConfigurationOptions();

        foreach ($options['bodyScrubbers'] as $scrubber) {
            $body = $scrubber($body);
        }

        $request->setBody($body);
    }

    /**
     * Takes the chunks of parse_url() and builds URL from it.
     *
     * @param array $parts The same structure as parse_url()
     *
     * @see https://stackoverflow.com/a/35207936
     * @see parse_url()
     *
     * @return string
     */
    private function rebuildUrl(array $parts)
    {
        return (isset($parts['scheme']) ? "{$parts['scheme']}:" : '') .
            ((isset($parts['user']) || isset($parts['host'])) ? '//' : '') .
            (isset($parts['user']) ? "{$parts['user']}" : '') .
            (isset($parts['pass']) ? ":{$parts['pass']}" : '') .
            (isset($parts['user']) ? '@' : '') .
            (isset($parts['host']) ? "{$parts['host']}" : '') .
            (isset($parts['port']) ? ":{$parts['port']}" : '') .
            (isset($parts['path']) ? "{$parts['path']}" : '') .
            (isset($parts['query']) ? "?{$parts['query']}" : '') .
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
    }
}
