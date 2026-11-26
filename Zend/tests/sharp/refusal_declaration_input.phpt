--TEST--
The engine runs a .sharp file that reads an import only through its declarations after a body edit to the import, before and after a compile that leaves its compiled file as it was, and refuses it once the import's compiled file names other declarations
--FILE--
<?php
require __DIR__ . '/project.inc';

const MONEY = "namespace Demo;\n\npublic class Money\n{\n    public int amount()\n    {\n        return 1;\n    }\n}\n";
const LABEL = "namespace Demo;\n\npublic class Label\n{\n    public static string name() => typeof(Money);\n}\n";

$root = compile_project('declaration-input', ['Money.sharp' => MONEY, 'Label.sharp' => LABEL]);
$label = "$root/.sharp/current/Label.sharpc";
$compiled = file_get_contents($label);
refusal("$root/Label.sharp");

file_put_contents("$root/Money.sharp", str_replace('return 1;', 'return 2;', MONEY));
refusal(["$root/Label.sharp", "$root/Money.sharp"]);

run_mago($root);
echo file_get_contents($label) === $compiled ? "the compile leaves Label.sharp's compiled file as it was\n" : "the compile rewrote Label.sharp's compiled file\n";
refusal(["$root/Label.sharp", "$root/Money.sharp"]);

file_put_contents("$root/Money.sharp", str_replace('int amount()', 'int amount(int scale = 1)', MONEY));
run_mago($root);
file_put_contents($label, $compiled);
refusal("$root/Label.sharp");
remove_project($root);
?>
--EXPECT--
ran
ran
CompileError: Money.sharp is out of date (Money.sharp changed). Run vendor/bin/mago compile.
the compile leaves Label.sharp's compiled file as it was
ran
ran
CompileError: Label.sharp is out of date (Money.sharp changed). Run vendor/bin/mago compile.
