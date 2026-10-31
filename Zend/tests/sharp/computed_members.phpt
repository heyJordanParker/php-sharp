--TEST--
PHP# runs expression-bodied methods and reads computed properties on each read
--FILE--
<?php

require __DIR__ . '/Page.sharp';

$page = new Demo\Page('About Us');
echo $page->slug, "\n";
echo $page->link(), "\n";
echo Demo\Page::home(), "\n";
$page->visit();
$page->visit();
echo $page->seen(), "\n";
echo $page->heading(), "\n";
var_dump((new ReflectionProperty(Demo\Page::class, 'slug'))->isVirtual());

try {
    $page->slug = 'contact';
} catch (Error $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
?>
--EXPECT--
about us
/page/about us
/
4
About Us (2)
bool(true)
Error: Property Demo\Page::$slug is read-only
