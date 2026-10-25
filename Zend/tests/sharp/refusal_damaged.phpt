--TEST--
The engine refuses a compiled file whose length, offsets or sections disagree with its header, or whose tree it can't translate, as not compiled
--FILE--
<?php
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
