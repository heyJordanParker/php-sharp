--TEST--
A PHP# named argument fills a plain PHP static method's, method's, constructor's, function's and attribute's ... parameter under its name
--FILE--
<?php
require __DIR__ . '/harness/Markup.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('NamedVariadics');

echo NamedVariadics\Page::tagged(), "\n";
echo NamedVariadics\Page::opened(), "\n";
echo NamedVariadics\Page::sized(), "\n";
echo NamedVariadics\Page::called(), "\n";
$generic = (new ReflectionClass(NamedVariadics\Page::class))->getAttributes(Lib\Generic::class)[0]->newInstance();
echo json_encode($generic->types), "\n";
?>
--EXPECT--
a{"href":"\/home","title":"Home"}
<a{"0":"x","href":"\/home"}
box{"width":3,"height":4}
a{"href":"\/home"}
{"Item":"NamedVariadics\\Page"}
