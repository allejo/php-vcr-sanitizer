<?php

/*
 * (c) Copyright 2018 Vladimir Jimenez
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

require __DIR__ . '/../vendor/autoload.php';

if (PHP_VERSION_ID >= 70100) {
    if (!class_exists('\PHPUnit_Framework_TestCase')) {
        class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase
        {
        }
    }
}
