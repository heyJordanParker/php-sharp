--TEST--
A preloaded PHP# named argument fills a plain PHP static method's, method's, constructor's, function's and attribute's ... parameter under its name
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/named_variadics_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(NamedVariadics\Page::class, false));
echo NamedVariadics\Page::tagged(), "\n";
echo NamedVariadics\Page::opened(), "\n";
echo NamedVariadics\Page::sized(), "\n";
echo NamedVariadics\Page::called(), "\n";
$generic = (new ReflectionClass(NamedVariadics\Page::class))->getAttributes(Lib\Generic::class)[0]->newInstance();
echo json_encode($generic->types), "\n";
?>
--EXPECT--
bool(true)
a{"href":"\/home","title":"Home"}
<a{"0":"x","href":"\/home"}
box{"width":3,"height":4}
a{"href":"\/home"}
{"Item":"NamedVariadics\\Page"}
