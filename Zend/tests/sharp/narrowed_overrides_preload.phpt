--TEST--
A preloaded PHP# override that narrows a typed plain PHP property links with the parent's PHP type and checks its own on each write
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/narrowed_overrides_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(NarrowedOverrides\Meter::class, false));
$meter = new NarrowedOverrides\Meter();
echo $meter->read(), "\n";
try {
    $meter->relabel(null);
} catch (TypeError $error) {
    echo $error->getMessage(), "\n";
}
echo (new ReflectionProperty(NarrowedOverrides\Meter::class, 'label'))->getType(), "\n";
?>
--EXPECT--
bool(true)
meter 3
Cannot assign null to property NarrowedOverrides\Meter::$label of type string
?string
