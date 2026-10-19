--TEST--
Preloaded PHP# named `break` and `continue` run as a request with no preloading runs them
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/loop_names_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(LoopNames\Walks::class, false));
require __DIR__ . '/loop_names.inc';
?>
--EXPECT--
bool(true)
from for-of: ["1","2","|","3","5","|","7"]
from for: ["0.1","0.2","|","1.1","2.1","2.2","|","3.1"]
from while: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
from do: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
to a for whose counter each pass keeps: [0,1,2,3]
