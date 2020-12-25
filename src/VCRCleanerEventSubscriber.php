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
 * The event subscriber that this library registers to manipulate the cassette
 * recordings before they're written to the file.
 *
 * Headers are case-insensitive. So all header manipulation happens in a
 * case-insensitive manner.
 *
 * @internal
 *
 * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
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
        $this->sanitizeRequestPostFields($event->getRequest());

        $originalRes = $event->getResponse();

        // We use the `toArray()` call here because it's an officially supported
        // method, meaning we can hope the structure for it will respect BC
        // promises especially because it has a counterpart, `fromArray()`.
        $workingRes = $originalRes->toArray();
        $this->sanitizeResponseHeaders($workingRes);
        $this->sanitizeResponseBody($workingRes);
        $this->sanitizeResponseCurlInfo($workingRes);

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

        // curlInfo was introduced in php-vcr 1.5+
        if ($ref->hasProperty('curlInfo')) {
            $curlInfoProp = $ref->getProperty('curlInfo');
            $curlInfoProp->setAccessible(true);
            $curlInfoProp->setValue($originalRes, $modResponse->getCurlInfo());
        }
    }

    private function sanitizeRequestHeaders(Request $request)
    {
        $caseInsensitiveKeys = array();

        foreach ($request->getHeaders() as $key => $value) {
            $caseInsensitiveKeys[strtolower($key)] = $key;
        }

        if (Config::ignoreAllReqHeaders()) {
            foreach ($request->getHeaders() as $targetHeader => $value) {
                $request->setHeader($targetHeader, '');
            }
        } else {
            foreach (Config::getReqIgnoredHeaders() as $header) {
                $caseInsensitiveHeader = strtolower($header);

                if (!isset($caseInsensitiveKeys[$caseInsensitiveHeader])) {
                    continue;
                }

                $targetHeader = $caseInsensitiveKeys[$caseInsensitiveHeader];

                if ($request->hasHeader($targetHeader)) {
                    $request->setHeader($targetHeader, '');
                }
            }
        }
    }

    private function sanitizeRequestHost(Request $request)
    {
        $newUrl = $this->deleteHostFromURL($request->getUrl());

        $request->setUrl($newUrl);
        $request->setHeader('Host', '');
    }

    private function sanitizeRequestUrl(Request $request)
    {
        $newUrl = $this->delQueryFieldsFromURL($request->getUrl(), Config::getReqIgnoredQueryFields());
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

    private function sanitizeRequestPostFields(Request $request)
    {
        $postFields = $request->getPostFields();

        foreach (Config::getReqPostFieldScrubbers() as $scrubber) {
            $postFields = $scrubber($postFields);
        }

        $request->setPostFields($postFields);
    }

    private function sanitizeResponseHeaders(array &$workspace)
    {
        if (!isset($workspace['headers'])) {
            return;
        }

        // To avoid breaking case-sensitivity in cassettes, keep a record of the
        // mapping between lowercase to original casing.
        $caseInsensitiveKeys = array();

        foreach ($workspace['headers'] as $key => $value) {
            $caseInsensitiveKeys[strtolower($key)] = $key;
        }

        if (Config::ignoreAllResHeaders()) {
            $workspace['headers'] = null;
        } else {
            foreach (Config::getResIgnoredHeaders() as $headerToIgnore) {
                $caseInsensitiveHeader = strtolower($headerToIgnore);

                if (!isset($caseInsensitiveKeys[$caseInsensitiveHeader])) {
                    continue;
                }

                $targetHeader = $caseInsensitiveKeys[$caseInsensitiveHeader];

                if (isset($workspace['headers'][$targetHeader])) {
                    $workspace['headers'][$targetHeader] = null;
                }
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

    private function sanitizeResponseCurlInfo(array &$workspace)
    {
        if (!isset($workspace['curl_info'])) {
            return;
        }

        $this->sanitizeCurlInfoURL($workspace);

        // Check if this field is set, because it may not be
        if (array_key_exists('request_header', $workspace['curl_info'])) {
            // `curl_info` has a duplicate of Request headers too in the `request_header` field
            $splitHeaders = preg_split('/(\r\n)|(\n)/', $workspace['curl_info']['request_header']);

            $this->sanitizeCurlInfoRequestHeaderURL($splitHeaders);
            $this->sanitizeCurlInfoRequestHeaders($splitHeaders);

            $workspace['curl_info']['request_header'] = implode('\r\n', $splitHeaders);
        }
    }

    /**
     * Remove the host from a given URL.
     *
     * @param string $url
     *
     * @return string
     */
    private function deleteHostFromURL($url)
    {
        if (!Config::ignoreReqHostname()) {
            return $url;
        }

        $urlParts = parse_url($url);
        $urlParts['host'] = '[]';

        return $this->rebuildUrl($urlParts);
    }

    /**
     * Remove the specified query parameters from a URL.
     *
     * @param string $url
     *
     * @return string
     */
    private function delQueryFieldsFromURL($url, array $fields)
    {
        $urlParts = parse_url($url);

        if (!isset($urlParts['query'])) {
            return $url;
        }

        $queryParts = array();
        parse_str($urlParts['query'], $queryParts);

        foreach ($fields as $urlParameter) {
            unset($queryParts[$urlParameter]);
        }

        $urlParts['query'] = http_build_query($queryParts);

        return $this->rebuildUrl($urlParts);
    }

    private function sanitizeCurlInfoURL(array &$workspace)
    {
        $requestUrl = $workspace['curl_info']['url'];

        // Delete the host from `url` in the duplicate `curl_info` field
        $requestUrl = $this->deleteHostFromURL($requestUrl);

        // Delete any query parameters
        $workspace['curl_info']['url'] = $this->delQueryFieldsFromURL($requestUrl, Config::getReqIgnoredQueryFields());

        // `curl_info` have the IP of the target host located in `primary_ip`; clear that out
        if (Config::ignoreReqHostname()) {
            $workspace['curl_info']['primary_ip'] = '';
        }
    }

    /**
     * Sanitize the target URL in the `request_header` field inside of `curl_info`.
     *
     * @param string[] $headersByLine
     *
     * @return void
     */
    private function sanitizeCurlInfoRequestHeaderURL(array &$headersByLine)
    {
        $regexMatches = array();

        // This RegEx matches: `GET /path?queryParam1=foobar&apiKey=hunter2 HTTP/1.1`
        preg_match('/[A-Z]+ (.+) HTTP/', $headersByLine[0], $regexMatches);

        // Remove any sensitive query parameters that are in this URL
        if (isset($regexMatches[1])) {
            $headersByLine[0] = str_replace(
                $regexMatches[1],
                $this->delQueryFieldsFromURL($regexMatches[1], Config::getReqIgnoredQueryFields()),
                $headersByLine[0]
            );
        }
    }

    /**
     * Sanitize the headers in the `request_header` field inside of `curl_info`.
     *
     * @param string[] $headersByLine
     *
     * @return void
     */
    private function sanitizeCurlInfoRequestHeaders(array &$headersByLine)
    {
        foreach ($headersByLine as &$line) {
            $regexMatches = array();

            // This RegEx matches: `Some-Header: Hunter2`. If it's not in this pattern, it's not a header so we can
            // ignore it.
            if (preg_match('/^([\w\-]+): ?(.+)/', $line, $regexMatches) !== 1) {
                continue;
            }

            // We somehow didn't extract the expected amount
            if (count($regexMatches) !== 3) {
                continue;
            }

            list($_, $headerKey, $headerValue) = $regexMatches;

            if ($headerKey === 'Host') {
                // Remove the `//` that's automatically prepended to the host after it's rebuilt
                $line = sprintf('%s: %s', $headerKey, str_replace('//', '', $this->deleteHostFromURL($headerValue)));
            }

            // If we are configured to ignore all headers or the header we've found, we need to ignore
            if (Config::ignoreAllReqHeaders() || in_array($headerKey, Config::getReqIgnoredHeaders(), true)) {
                $line = sprintf('%s: ""', $headerKey);
                continue;
            }
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
