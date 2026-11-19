--TEST--
An extern line's callable parameter takes a typed method value, which plain PHP receives and calls as a closure, and a call that leaves out a defaulted parameter passes the line's default, though plain PHP declares none
--FILE--
<?php
require __DIR__ . '/harness/Hooks.inc';
require __DIR__ . '/vendor/autoload.php';

(new ExternCallables\Saving())->register();

var_dump(Lib\Hooks\Log::$lines);
?>
--EXPECT--
array(3) {
  [0]=>
  string(50) "save_post, priority 20, 3 arguments: updated Hello"
  [1]=>
  string(47) "deleted_post, priority 10, 1 arguments: deleted"
  [2]=>
  string(47) "trashed_post, priority 30, 1 arguments: deleted"
}
