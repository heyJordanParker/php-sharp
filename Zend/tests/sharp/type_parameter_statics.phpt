--TEST--
PHP# TItem.make() and TItem.size reach the statics of the type argument the code runs with, which it redeclares or inherits, and a type parameter bounded by a class refuses a type argument of two classes, which has no statics to reach
--FILE--
<?php

require __DIR__ . '/harness/fixture.inc';
require __DIR__ . '/project.inc';
fixture('TypeStatics');

var_dump(TypeStatics\Builds::ofClasses());

$types = [];
foreach (['Maker', 'Fancy', 'Plain', 'Builder'] as $type) {
    $types["$type.sharp"] = file_get_contents(__DIR__ . "/TypeStatics/$type.sharp");
}
$root = compile_project('type-parameter-statics', $types);
file_put_contents("$root/Unions.sharp", <<<'SHARP'
namespace TypeStatics;

public class Unions
{
    public static string built() => new Builder<Fancy|Plain>().build();
}

SHARP);
refusal("$root/Unions.sharp", ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile']);
remove_project($root);
?>
--EXPECT--
array(9) {
  [0]=>
  string(5) "fancy"
  [1]=>
  string(10) "fancy size"
  [2]=>
  string(5) "maker"
  [3]=>
  string(10) "maker size"
  [4]=>
  string(5) "maker"
  [5]=>
  string(5) "fancy"
  [6]=>
  string(10) "fancy size"
  [7]=>
  string(5) "maker"
  [8]=>
  string(10) "maker size"
}
CompileError: Unions.sharp has 1 error:
line 5: `TItem`'s bound is a class, so its type argument is one class. Write `Builder<Maker>`.
  help: Supply one class `Maker` holds: `TItem`'s code reaches the statics of that class.
