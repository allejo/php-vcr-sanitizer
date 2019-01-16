# Upgrade from 0.0 to 1.0

This project went from something that was open sourced on a whim and was adopted by a number of people. It was hoped that being tagged as version `0.0.x` would indicate that things were going to change. Now is the time for that change and with new features too.

## Upgrading the Configuration

Originally, the project only supported sanitizing outgoing requests so the naming of available options made sense. However, with support for sanitizing responses, the structure of the configuration has changed to isolate Request related settings from Response related settings.

The **only** changes you need to make is rename some options and restructure your array.

- `ignoreHostname` has moved to `request.ignoreHostname`
- `ignoreHeaders` has moved to `request.ignoreHeaders`
- `bodyScrubbers` has moved to `request.bodyScrubbers`
- `ignoreUrlParameters` has moved and been renamed to `request.ignoreQueryFields`


```php
// Configuring 0.0.x
VCRCleaner::enable(array(
    'ignoreHostname'      => boolean,
    'ignoreUrlParameters' => array(),
    'ignoreHeaders'       => array(),
    'bodyScrubbers'       => array(),
));
```

```php
// Configuring 1.0.x
VCRCleaner::enable(array(
    'request' => array(
        'ignoreHostname'    => boolean,
        'ignoreQueryFields' => string[],
        'ignoreHeaders'     => string[],
        'bodyScrubbers'     => Array<(string $body): string>
    ),
    'response' => array(
        'ignoreHeaders'     => string[],
        'bodyScrubbers'     => Array<(string $body): string>
    ),
));
```
