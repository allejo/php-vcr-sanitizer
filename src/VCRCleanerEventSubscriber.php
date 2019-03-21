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
use VCR\Response;
use VCR\VCREvents;

/**
 * @internal
 */
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

        $originalRes = $event->getResponse();

        // We use the `toArray()` call here because it's an officially supported
        // method, meaning we can hope the structure for it will respect BC
        // promises especially because it has a counterpart, `fromArray()`.
        $workingRes = $originalRes->toArray();
        $this->sanitizeResponseHeaders($workingRes);
        $this->sanitizeResponseBody($workingRes);

        // There's no way to handle manipulating the Response object in php-vcr
        // so we'll have to get our hands dirty with reflection and hope this
        // doesn't break in the future.
        $ref = new \ReflectionClass($originalRes);
        $headerProp = $ref->getProperty('headers');
        $headerProp->setAccessible(true);
        $bodyProp = $ref->getProperty('body');
        $bodyProp->setAccessible(true);

        $modResponse = Response::fromArray($workingRes);
        $headerProp->setValue($originalRes, $modResponse->getHeaders());
        $bodyProp->setValue($originalRes, $modResponse->getBody());
    }

    private function sanitizeRequestHeaders(Request $request)
    {
        foreach (Config::getReqIgnoredHeaders() as $header) {
            if ($request->hasHeader($header)) {
                $request->setHeader($header, null);
            }
        }
    }

    private function sanitizeRequestHost(Request $request)
    {
        if (!Config::ignoreReqHostname()) {
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
        $url = parse_url($request->getUrl());

        if (!isset($url['query'])) {
            return;
        }

        $queryParts = array();
        parse_str($url['query'], $queryParts);

        foreach (Config::getReqIgnoredQueryFields() as $urlParameter) {
            unset($queryParts[$urlParameter]);
        }

        $url['query'] = http_build_query($queryParts);

        $newUrl = $this->rebuildUrl($url);

        $request->setUrl($newUrl);
    }

    private function sanitizeRequestBody(Request $request)
    {
        $body = $request->getBody();

        foreach (Config::getReqBodyScrubbers() as $scrubber) {
            $body = $scrubber($body);
        }

        $request->setBody($body);
    }

    private function sanitizeResponseHeaders(array &$workspace)
    {
        foreach (Config::getResIgnoredHeaders() as $headerToIgnore) {
            if (isset($workspace['headers'][$headerToIgnore])) {
                $workspace['headers'][$headerToIgnore] = null;
            }
        }
    }

    private function sanitizeResponseBody(array &$workspace)
    {
        if (!isset($workspace['body'])) {
            return;
        }

        foreach (Config::getResBodyScrubbers() as $bodyScrubber) {
            $workspace['body'] = $bodyScrubber($workspace['body']);
        }
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
