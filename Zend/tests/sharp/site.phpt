--TEST--
A PHP# class extends a PHP class, implements a PHP interface and reads its constants, statics and enum cases
--FILE--
<?php

require __DIR__ . '/SiteLib.inc';
require __DIR__ . '/Site.sharp';

$page = new Site\Page();
var_dump($page instanceof Lib\Entity, $page instanceof Lib\Linkable, $page->id(), $page->link());
var_dump($page->limit(), $page->visit(), $page->visit(), Site\Page::$views);
var_dump($page->live(), $page->draft(), $page->registered());

$square = new Site\Square();
var_dump($square instanceof Site\Shape, $square->area(), $square->sides());
?>
--EXPECT--
bool(true)
bool(true)
int(7)
string(5) "/page"
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
