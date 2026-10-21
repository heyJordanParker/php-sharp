--TEST--
A field of an enum case named like a property or a method of its enum is a compile error that names both, for plain PHP that loads the enum too
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('enum-case-fields', ['Demo/Shop.sharp' => SHOP, 'Demo/Counter.sharp' => COUNTER]);
file_put_contents("$root/Demo/Amount.sharp", <<<'SHARP'
namespace Demo;

public enum Amount
{
    case None;
    case Some(int cents);

    public int cents => 0;
}

SHARP);
refusal("$root/Demo/Amount.sharp", ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile']);
remove_project($root);
?>
--EXPECT--
CompileError: Demo/Amount.sharp has 1 error:
line 6: The field `cents` of the case `Amount.Some` shares its name with the property `Amount.cents`, which every case has.
  help: Rename one of them.
