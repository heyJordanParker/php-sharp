<?php

declare(strict_types=1);

// The fixture's mago extension host, which mago.toml names and mago runs on its own engine, with its own SDK. It
// requires vendor/autoload.php before it registers its worker, as a framework's host does to boot the app, so
// sharp/bin/test --phpunit's mago compile, which runs with nothing compiled, shows vendor/autoload.php loading before
// anything is compiled.

require __DIR__ . '/vendor/autoload.php';

(new Mago\Sdk\Worker(new Mago\Sdk\Extension(
    identifier: 'php-sharp/phpunit-fixture',
    name: 'PHP# PHPUnit fixture',
    version: '0.0.0',
)))->run();
