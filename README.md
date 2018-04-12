# php-vcr-sanitzer

[php-vcr has had "Privacy aware" marked as "soon"](https://php-vcr.github.io/#page-nav-Features) for quite some time now. Whenever I test my APIs, chances are that there will be some sensitive information such as keys or passwords for example. Up until now, I've had a separate script to always sanitize my data in a separate file that isn't ignored by version control.

This is a quick and dirty solution until php-vcr officially adds support for this.

## Installation

Install the package through Composer.

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
        body: "...request body..."
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
        body: "...request body..."
```

## License

[MIT](/LICENSE.md)
