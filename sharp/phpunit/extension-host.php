<?php

declare(strict_types=1);

// The fixture's mago extension host, which mago.toml starts. It requires vendor/autoload.php before it serves, as a
// framework's host does to boot the app, so sharp/bin/test --phpunit's mago compile, which runs with nothing compiled,
// shows vendor/autoload.php loading before anything is compiled. The SDK is the one in sharp/mago/composer, which the
// mago sharp/bin/test built speaks.

require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Mago\\')) {
        require dirname(__DIR__) . '/mago/composer/src/' . strtr(substr($class, strlen('Mago\\')), '\\', '/') . '.php';
    }
});

(new Mago\Sdk\Worker(new Mago\Sdk\Extension(
    identifier: 'php-sharp/phpunit-fixture',
    name: 'PHP# PHPUnit fixture',
    version: '0.0.0',
)))->run();
