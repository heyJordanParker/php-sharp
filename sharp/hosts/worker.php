<?php

declare(strict_types=1);

// FrankenPHP's worker mode serves every request from one PHP request, so each runs in a scope of its own.
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/serve.php';

$container = new \Sharp\Container();
$handle = static function () use ($container): void {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    echo \Sharp\Scope::run(static fn (): string => serve($container, $path));
};

while (frankenphp_handle_request($handle)) {
    gc_collect_cycles();
}
