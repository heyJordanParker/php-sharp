--TEST--
Preloaded PHP# named `break` and `continue` run as a request with no preloading runs them under the tracing JIT
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/loop_names_preload.inc
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
if (ini_get('opcache.jit') === false) die('skip JIT is not available');
?>
--FILE--
<?php
echo 'jit ', opcache_get_status()['jit']['on'] ? 'on' : 'off', "\n";
var_dump(class_exists(LoopNames\Walks::class, false));
require __DIR__ . '/loop_names.inc';
?>
--EXPECT--
jit on
bool(true)
from for-of: ["1","2","|","3","5","|","7"]
from for: ["0.1","0.2","|","1.1","2.1","2.2","|","3.1"]
from while: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
from do: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
to a for whose counter each pass keeps: [0,1,2,3]
