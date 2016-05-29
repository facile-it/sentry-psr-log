# Sentry PSR 3 logger

[![Build Status](https://api.travis-ci.org/facile-it/sentry-psr-log.svg?branch=master)](https://travis-ci.org/facile-it/sentry-psr-log)
[![Code Coverage](https://scrutinizer-ci.com/g/facile-it/sentry-psr-log/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/facile-it/sentry-psr-log/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/facile-it/sentry-psr-log/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/facile-it/sentry-psr-log/?branch=master)

This module provide a PSR 3 log implementation for [Sentry](https://getsentry.com)

## Installation

The only supported way to install this module is trough composer. For composer documentation you can refer to [getcomposer.org](http://getcomposer.org).

```
composer require facile-it/sentry-psr-log
```

## Example

```php
$ravenClient = new Raven_Client('dsn', []);

$logger = new Facile\Sentry\Log\Logger($ravenClient);

// Logging message
$logger->error('message', ['foo' => 'bar']);

// Logging exception
$exception = new \RuntimeException('foo');
$logger->error($exception, ['foo' => 'bar']);
```
