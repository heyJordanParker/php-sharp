--TEST--
A preloaded PHP# class writes through a plain PHP property it reads as a List, and the property holds the List renumbered
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/plain_writes_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(PlainWrites\Editor::class, false));
$editor = new PlainWrites\Editor();
$roster = new Lib\Roster();
$editor->add($roster);
$editor->set($roster);
echo $editor->bump($roster), ' ', json_encode($roster->names), ' ', json_encode($roster->scores), "\n";
$editor->addShared();
$editor->addTag();
echo json_encode(Lib\Roster::shared()->names), ' ', json_encode(Lib\Roster::$tags), "\n";
?>
--EXPECT--
bool(true)
9 ["zed","bo","cy"] [5,10]
["ada","bo","dee"] ["x","y","z"]
