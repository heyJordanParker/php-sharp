--TEST--
PHP# writes through `?.` run as without the JIT under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--SKIPIF--
<?php
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
require __DIR__ . '/harness/fixture.inc';
fixture('NullSafeWrites');
require __DIR__ . '/null_safe_writes.inc';
?>
--EXPECT--
jit on
the block of spec section 14.4: [{"finds":1,"fees":0,"order":null},{"finds":1,"fees":1,"order":{"note":"gift","gift":null,"total":15,"ratio":8,"bits":12,"items":1,"counts":[1,2],"tags":[],"stock":[],"customer":"Ada","label":""}}]
every compound assignment: [null,{"note":"!","gift":"card","total":9,"ratio":2,"bits":14,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}]
++ and --: [null,{"note":"","gift":null,"total":12,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}]
index writes: [null,{"note":"","gift":null,"total":10,"ratio":8,"bits":12,"items":0,"counts":[6,3],"tags":{"vip":true},"stock":{"a":1,"b":2},"customer":"","label":""}]
a receiver with a side effect runs once: [{"finds":2,"fees":0,"order":null},{"finds":2,"fees":1,"order":{"note":"found","gift":null,"total":15,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":""}}]
a set body runs only when the write happens: [{"sets":0,"order":null},{"sets":2,"order":{"note":"","gift":null,"total":10,"ratio":8,"bits":12,"items":0,"counts":[1,2],"tags":[],"stock":[],"customer":"","label":"L!"}}]
the value of a write: [[null,null],["kept",10]]
