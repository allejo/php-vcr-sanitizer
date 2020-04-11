# php-vcr-sanitizer

[![Packagist](https://img.shields.io/packagist/v/allejo/php-vcr-sanitizer.svg)](https://packagist.org/packages/allejo/php-vcr-sanitizer)
[![Build Status](https://travis-ci.org/allejo/php-vcr-sanitizer.svg?branch=master)](https://travis-ci.org/allejo/php-vcr-sanitizer)
[![GitHub license](https://img.shields.io/github/license/allejo/php-vcr-sanitizer.svg)](https://github.com/allejo/php-vcr-sanitizer/blob/master/LICENSE.md)


[php-vcr](https://php-vcr.github.io/) is a tool for recording and replaying outgoing requests, however it has had ["Privacy aware" marked as "soon"](https://php-vcr.github.io/#page-nav-Features) for quite some time now. Whenever I test my APIs, there will often be some sensitive information such as keys or passwords in the recordings. Up until now, I've had a separate script to always remove sensitive data before getting checked into version control.

I got tired of having to always sanitize the data, so this is a quick and dirty solution until php-vcr officially supports "private" recordings.

[TOC levels=4]: # "## Table of Contents"

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
    - [Configuration](#configuration)
        - [Sanitizing Requests](#sanitizing-requests)
        - [Sanitizing Responses](#sanitizing-responses)
    - [Disabling the Sanitizer](#disabling-the-sanitizer)
- [How Sanitizing Works](#how-sanitizing-works)
    - [Hostnames](#hostnames)
    - [Headers](#headers)
    - [URL Parameters](#url-parameters)
    - [Body Content](#body-content)
- [License](#license)


## Installation

Install the package through [Composer](https://getcomposer.org/).

```bash
composer require --dev allejo/php-vcr-sanitizer
```

## Usage

After your VCR instance has been turned on, call `VCRCleaner::enable()` and pass whatever URL parameters or headers you don't want to be recorded in your fixtures.

```php
VCR::turnOn();
VCR::insertCassette('...');

VCRCleaner::enable(array(
   'request' => array(
       'ignoreHostname' => false,
       'ignoreQueryFields' => array(
           'apiKey',
       ),
       'ignoreHeaders' => array(
           'X-Api-Key',
       ),
       'bodyScrubbers' => array(
           function($body) {
               return preg_replace('/<password.*<\/password>/', 'hunter2', $body);
           }
       ),
       'postFieldScrubbers' => array(
           function(array $postFields) {
               $postFields['Secret'] = 'REDACTED';
               return $postFields;
           }
       ),
   ),
   'response' => array(
       'ignoreHeaders' => array(),
       'bodyScrubbers' => array(),
   ),
));
```

### Configuration

This library allows your sanitize both the Request and Response sections of your recordings so only non-sensitive data is written to your cassettes. You define the behavior for this sanitizer via an array with configuration options explained below.

#### Sanitizing Requests

- `request.ignoreHostname` - When set to true, the hostname in URLs inside of the Request will be replaced with `[]` in the `url` field and the `Host` in the headers will be set to null.
- `request.ignoreQueryFields` - Define which GET parameters in your URL to completely strip out of your recordings.
- `request.ignoreHeaders` - Define the headers in your recording that will automatically be set to null in your recordings
- `request.bodyScrubbers` - An array of callbacks that will have the request body available as a string. Each callback **must** return the modified body. The callbacks are called consecutively in the order they appear in this array and the value from one callback propagates to the next.
- `request.postFieldScrubbers` - An array of callbacks that will have the request post fields available as an array. Each callback **must** return the modified post fields array. The callbacks are called consecutively in the order they appear in this array and the value from one callback propagates to the next.

#### Sanitizing Responses

The php-vcr library does not officially support modifying its responses so this library uses reflection to modify the contents of responses. While this feature is officially supported by *this* project, bear with us if this feature were to break due to the php-vcr changing its internals.

- `response.ignoreHeaders` - The same as `request.ignoreHeaders` but for your response body instead.
- `response.bodyScrubbers` - The same as `request.bodyScrubbers` but for your response body instead.

### Disabling the Sanitizer

Why is there no `VCRCleaner::disable()`? There's no simple and non-hackish way to restore the VCR to its original state. It's probably easier to just configure your VCR differently for a certain batch of unit tests anyways.

## How Sanitizing Works

When VCR is looking for recordings to playback, VCRCleaner uses modified "matchers" to check everything except for the fields you've marked as sensitive.

### Hostnames

If the hostname of the URL endpoint you're hitting is sensitive and shouldn't be recorded, you can have the sanitizer ignore hostnames and they'll be replaced in the `url` field with a `[]` instead and the `host` header will be set to null.

```yaml
-
    request:
        method: GET
        url: 'https://[]/search'
        headers:
            Host: null
            X-Type: application/vcr
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: "...response body..."
```

### Headers

Let's say you set the `X-Api-Key` header to `SuperToast`. In your recording, the header you specified will be saved as null.

```yaml
-
    request:
        method: GET
        url: 'https://www.example.com/search'
        headers:
            Host: www.example.com
            X-Api-Key: null
            X-Type: application/vcr
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: "...response body..."
```

### URL Parameters

Notice how `apiKey=yourSecretApiKey` is stripped away in your recording. During your VCR playback, it'll look for matching requests *without* the `apiKey` parameter.

```yaml
# Your cURL call to: https://www.example.com/search?q=keyword&apiKey=yourSecretApiKey
# gets recorded like so,
-
    request:
        method: GET
        url: 'https://www.example.com/search?q=keyword'
        headers:
            Host: www.example.com
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: "...response body..."
```

### Body Content

Unlike ignoring headers or URL parameters, scrubbing information from both request and response bodies makes use of an array of callbacks. The result of each function is passed on to the next function.

Notice how `password=hunter2` has been stripped away from the request body. The callbacks take the body as a string parameter, the modified result has to be returned.

```php
VCRCleaner::enable(array(
    'request' => array(
        'bodyScrubbers' => array(
            function ($body) {
                $parameters = array();

                parse_str($body, $parameters);
                unset($parameters['password']);

                return http_build_query($parameters);
            },
        ),
    ),
));
```

```yaml
# You POST request to `https://www.example.com/search` with a body of
# `username=AzureDiamond&password=hunter2` gets recorded like so,
-
    request:
        method: POST
        url: 'https://www.example.com/search'
        headers:
            Host: www.example.com
        body: 'username=AzureDiamond'
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: '...response body...'
```

### Post Field Content

When making POST requests, you VCR will sometimes record the data inside of a `post_fields` parameter rather than the `body`. In those cases, this option can be used to sanitize sensitive content. Note that unlike the `body` field, `post_fields` is an array:

```php
VCRCleaner::enable(array(
    'request' => array(
        'postFieldScrubber' => array(
            function (array $postFields) {
                $postFields['Secret_Key'] = '';
                return $postFields;
            },
        ),
    ),
));
```

```yaml
# You POST request to `https://www.example.com/search` with a post field of
# `['data'=> 'hello world', 'Secret_Key' => 'abc']` gets recorded like so,
-
    request:
        method: POST
        url: 'https://www.example.com/search'
        headers:
            Host: www.example.com
        post_fields:
            data: 'hello world'
            Secret_Key: ''
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: '...response body...'
```

## License

[MIT](/LICENSE.md)
