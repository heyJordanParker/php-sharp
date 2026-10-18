--TEST--
Preloaded PHP# writes through `?.` run as a request with no preloading runs them
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/null_safe_writes_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(NullSafeWrites\Writes::class, false));
require __DIR__ . '/null_safe_writes.inc';
?>
--EXPECT--
bool(true)
the block of spec section 14.4: [{"finds":1,"fees":0,"order":null},{"finds":1,"fees":1,"order":{"note":"gift","gift":null,"total":15,"ratio":8,"bits":12,"items":1,"counts":[1,2],"tags":[],"stock":[],"customer":"Ada","label":""}}]
every compound assignment: [null,{"note":"!","gift":"card","total":9,"ratio":2,"bits":14,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}]
++ and --: [null,{"note":"","gift":null,"total":12,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}]
index writes: [null,{"note":"","gift":null,"total":10,"ratio":8,"bits":12,"items":0,"counts":[6,3],"tags":{"vip":true},"stock":{"a":1,"b":2},"customer":"","label":""}]
a receiver with a side effect runs once: [{"finds":2,"fees":0,"order":null},{"finds":2,"fees":1,"order":{"note":"found","gift":null,"total":15,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}}]
a local receiver is written as it was tested: [null,{"note":"cleared","gift":null,"total":10,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}]
a write past ?. through an index: [null,{"sku":"renamed"}]
a set body runs only when the write happens: [{"sets":0,"order":null},{"sets":2,"order":{"note":"","gift":null,"total":10,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":"L!"}}]
the value of a write: [[null,null],["kept",10]]
