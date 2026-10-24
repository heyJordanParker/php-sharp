--TEST--
An engine that runs opcache refuses each damaged compiled file of refusal_damaged as not compiled, as one without it does
--EXTENSIONS--
opcache
--FILE--
<?php
$refusal_settings = ['opcache.enable_cli' => 1];
require __DIR__ . '/refusal_damaged.inc';
?>
--EXPECT--
one byte too long: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
one byte too short: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
shorter than a header: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a root past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a library version past the texts: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
sections changed after mago wrote them: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a child past the nodes: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a text past the texts: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a kind php-src does not declare: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a return with two children: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a statement list that holds itself: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a statement two places hold: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
a value tag past SHARP_STRING: CompileError: Shop.sharp isn't compiled. Run vendor/bin/mago compile.
the file as mago wrote it: ran
