--TEST--
A plain PHP generic class's type arguments are erased, so plain PHP passes any of its objects where PHP# names it with type arguments
--FILE--
<?php
require __DIR__ . '/type_arguments_entry.inc';

use Checks\Pages;
use Lib\Holder;

attempt(fn () => Pages::keep(new Holder()));
?>
--EXPECT--
kept
