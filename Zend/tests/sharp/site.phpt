--TEST--
A PHP# class extends a PHP class, replaces its methods, implements a PHP interface and reads its constants, statics and enum cases
--FILE--
<?php

require __DIR__ . '/harness/SiteLib.inc';
require __DIR__ . '/Site.sharp';

$page = new Site\Page();
var_dump($page instanceof Lib\Resource, $page instanceof Lib\Linkable, $page->id(), $page->link());
var_dump($page->show(), (new ReflectionClass($page))->getAttributes()[0]->newInstance()->class);
foreach (['render', 'id'] as $method) {
    var_dump(count((new ReflectionMethod($page, $method))->getAttributes(Override::class)));
}
var_dump($page->limit(), $page->visit(), $page->visit(), Site\Page::$views);
var_dump($page->live(), $page->draft(), $page->registered());

$square = new Site\Square();
var_dump($square instanceof Site\Shape, $square->area(), $square->sides());
?>
--EXPECT--
bool(true)
bool(true)
int(8)
string(5) "/page"
string(6) "8 page"
string(9) "Site\Page"
int(1)
int(1)
int(3)
int(1)
int(2)
int(2)
bool(true)
enum(Lib\Status::Draft)
string(20) "registered Site\Page"
bool(true)
float(4)
int(4)
