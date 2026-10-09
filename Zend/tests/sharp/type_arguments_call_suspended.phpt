--TEST--
A generic call whose argument suspends the Fiber gives the method its type arguments when the Fiber resumes
--FILE--
<?php
require __DIR__ . '/type_arguments_call_suspended.inc';
?>
--EXPECT--
suspended
App\Box<App.Order>
suspended
App\Box<App.Order>
