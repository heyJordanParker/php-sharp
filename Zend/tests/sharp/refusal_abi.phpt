--TEST--
The engine refuses a compiled file written for another SHARP_UNIT_ABI, as compiled for a different engine
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('abi', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$compiled = "$root/.sharp/Shop.sharpc";
overwrite($compiled, 8, chr(ord(file_get_contents($compiled)[8]) ^ 0xff));

refusal("$root/Shop.sharp");
remove_project($root);

// The ABI of 0.6.10's checker, whose `emit` passed the event alone, before it named the dispatcher. The engine refuses
// a file that checker wrote before it runs, so no `emit` of it reaches the engine with one argument.
$root = compile_project('abi-emit', [
    'Paid.sharp' => "namespace Demo;\n\npublic event Paid(int orderId);\n",
    'Payer.sharp' => "namespace Demo;\n\npublic class Payer\n{\n    public void pay() { emit new Paid(1); }\n}\n",
]);
overwrite("$root/.sharp/Payer.sharpc", 8, "\x28\xdb\x0d\xf4\x4c\x54\xe7\xd6\x69\xe2\x78\x75\x6c\x26\x62\x11");

refusal("$root/Payer.sharp");
remove_project($root);
?>
--EXPECT--
CompileError: Shop.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
CompileError: Payer.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine.
