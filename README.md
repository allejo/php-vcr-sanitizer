# php-vcr-sanitizer

[![Packagist](https://img.shields.io/packagist/v/allejo/php-vcr-sanitizer.svg)](https://packagist.org/packages/allejo/php-vcr-sanitizer)
[![Build Status](https://travis-ci.org/allejo/php-vcr-sanitizer.svg?branch=master)](https://travis-ci.org/allejo/php-vcr-sanitizer)
[![GitHub license](https://img.shields.io/github/license/allejo/php-vcr-sanitizer.svg)](https://github.com/allejo/php-vcr-sanitizer/blob/master/LICENSE.md)


[php-vcr](https://php-vcr.github.io/) is a tool for recording and replaying outgoing requests, however it has had ["Privacy aware" marked as "soon"](https://php-vcr.github.io/#page-nav-Features) for quite some time now. Whenever I test my APIs, there will often be some sensitive information such as keys or passwords in the recordings. Up until now, I've had a separate script to always remove sensitive data before getting checked into version control.

I got tired of having to always sanitize the data, so this is a quick and dirty solution until php-vcr officially supports "private" recordings.

## Installation

Install the package through [Composer](https://getcomposer.org/).

```bash
composer require allejo/php-vcr-sanitizer
```

## Usage

After your VCR instance has been turned on, call `VCRCleaner::enable()` and pass whatever URL parameters or headers you don't want to be recorded in your fixtures.

```php
VCR::turnOn();
VCR::insertCassette('...');

VCRCleaner::enable(array(
    'ignoreUrlParameters' => array(
        'apiKey',
    ),
    'ignoreHeaders' => array(
        'X-Api-Key',
    ),
    'bodyScrubbers' => array(function($body) {
        return preg_replace('/<password.*<\/password>/', 'REDACTED', $body);
    }),
));
```

## How it Works

When VCR is looking for recordings to playback, VCRCleaner uses modified "matchers" to check everything except for the fields you've marked as sensitive.

### Hiding Headers

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

### Hiding URL Parameters

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

## Hiding body contents

Notice how `<password>Hunter2</password>` has been stripped away from the request body. The callbacks take the body as a string parameter, the modified result has to be returned.

```yaml
# Your cURL call to: https://www.example.com/search
# gets recorded like so,
-
    request:
        method: POST
        url: 'https://www.example.com/search
        headers:
            Host: www.example.com
        body: "<login><username></username>REDACTED</login>"
    response:
        status:
            http_version: '1.1'
            code: '404'
            message: 'Not Found'
        headers: ~
        body: "...response body..."
```

## License

[MIT](/LICENSE.md)
