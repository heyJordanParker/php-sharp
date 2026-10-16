--TEST--
A preloaded Position.current() default gives a PHP# call's own position, and a call through a function value or from plain PHP the position of the parameter's declaration
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/position_caller_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Callers\Desk::class, false));
require __DIR__ . '/position_caller.inc';
?>
--EXPECT--
bool(true)
Desk.sharp 10:13 Callers.Desk.calls
Given.sharp 1:1 Callers.given
Desk.sharp 12:13 Callers.Desk.calls
Desk.sharp 13:13 Callers.Desk.calls
Reports.sharp 5:70 Callers.Reports.logSlow
== a plain PHP caller
Reports.sharp 5:70 Callers.Reports.logSlow
Reports.sharp 7:54 Callers.Reports.seen
Note.sharp 7:33 Callers.Note.Note
