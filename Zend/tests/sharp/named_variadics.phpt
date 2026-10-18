--TEST--
A PHP# named argument fills a plain PHP static method's, method's, constructor's, function's and attribute's ... parameter under its name, and plain PHP that passes names to a PHP# ... parameter throws
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
// Plain PHP that passes names to a PHP# `...` parameter throws on entry.
$wrapper = new NamedVariadics\Wrapper();
echo $wrapper->wrap('p', 'x', 'y'), "\n";
foreach ([fn () => $wrapper->render(), fn () => NamedVariadics\Wrapper::sum(1, b: 2)] as $call) {
    try {
        $call();
    } catch (Error $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
a{"href":"\/home","title":"Home"}
<a{"0":"x","href":"\/home"}
box{"width":3,"height":4}
a{"href":"\/home"}
{"Item":"NamedVariadics\\Page"}
p2
Error: `Wrapper.wrap` takes `...attributes` by position, and was given `class:` and `id:`.
Error: `Wrapper.sum` takes `...values` by position, and was given `b:`.
