<?php

declare(strict_types=1);

// php-fpm serves each request in a PHP request of its own, which is the default scope.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/serve.php';

echo serve(new \Sharp\Container(), parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
