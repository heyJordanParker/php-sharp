--TEST--
An attribute on a machine case's field takes an object, as one on a constructor's parameter does, and Reflection reads it on the case's field
--FILE--
<?php
require __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

$field = new ReflectionProperty(AttributeObjects\LinkStatus\Forward::class, 'redirectUrl');
$route = $field->getAttributes(Lib\Route::class)[0]->newInstance();
var_dump($route->path, $route->middleware[0]->perMinute);
?>
--EXPECT--
string(8) "/forward"
int(20)
