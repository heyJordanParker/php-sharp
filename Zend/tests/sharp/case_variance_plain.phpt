--TEST--
Plain PHP names a class in any case, so a child's `foo` in a signature is its parent's `Foo`: compatible when the class is already loaded, declared in another file, as opcache binds it, and when it autoloads while the child links
--FILE--
<?php

// Foo is loaded, and the child sits in a file of its own, which opcache binds to its parent once both are cached.
require __DIR__ . '/case_variance_plain_parent.inc';
require __DIR__ . '/case_variance_plain_child.inc';
var_dump(get_class((new ChildWithFoo())->take(new Foo())));

// Bar is not loaded until the child links.
spl_autoload_register(static function (string $class): void {
    if (strcasecmp($class, 'Bar') === 0) {
        eval('class Bar {}');
    }
});
eval('class ParentWithBar
{
    public function take(Bar $value): Bar
    {
        return $value;
    }
}

final class ChildWithBar extends ParentWithBar
{
    public function take(bar $value): BAR
    {
        return $value;
    }
}');
var_dump(get_class((new ChildWithBar())->take(new Bar())));
?>
--EXPECT--
string(3) "Foo"
string(3) "Bar"
