--TEST--
A preloaded PHP# attribute argument creates plain PHP and PHP# objects, one inside another, in a list and in a Map, each time Reflection reads it
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/attribute_objects_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(AttributeObjects\Orders::class, false));
$route = fn (Reflector $reflector) => $reflector->getAttributes(Lib\Route::class)[0]->newInstance();
$class = $route(new ReflectionClass(AttributeObjects\Orders::class));
$windowed = $route(new ReflectionMethod(AttributeObjects\Orders::class, 'windowed'));
$nested = $route(new ReflectionMethod(AttributeObjects\Orders::class, 'nested'));
$mapped = $route(new ReflectionMethod(AttributeObjects\Orders::class, 'mapped'));
var_dump($class->middleware[0]->perMinute);
var_dump($windowed->middleware[0] instanceof AttributeObjects\Window, $windowed->middleware[0]->seconds);
var_dump($nested->middleware[0]->perMinute, $nested->middleware[0]->window->seconds);
var_dump($mapped->options['limit']->perMinute);
// PHP builds the object each time it reads the attribute.
var_dump($route(new ReflectionClass(AttributeObjects\Orders::class))->middleware[0] !== $class->middleware[0]);
?>
--EXPECT--
bool(true)
int(60)
bool(true)
int(30)
int(10)
int(5)
int(5)
bool(true)
