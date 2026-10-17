--TEST--
A PHP# attribute argument creates plain PHP and PHP# objects, one inside another, in a list and in a Map, each time Reflection reads it
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('AttributeObjects');

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
int(60)
bool(true)
int(30)
int(10)
int(5)
int(5)
bool(true)
