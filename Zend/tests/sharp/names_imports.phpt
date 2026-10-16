--TEST--
PHP# calls a library's function and reads its constant through the namespace it imports, calls a class through its renamed import, a class an import shadows a default with, a class of its own namespace that shadows a default, and classes named like keywords. Plain PHP calls a PHP# class through a renamed use
--FILE--
<?php

require __DIR__ . '/names_imports.inc';

use Imports\Printer as Shown;

echo Shown::print('invoice'), "\n";
echo Shown::dpi(), "\n";
echo Shown::clock(), "\n";
echo Shown::events(), "\n";
echo Shown::stage(), "\n";
echo Shown::keywords(), "\n";
echo Imports\Return::label(), ' ', Imports\If::label(), "\n";
?>
--EXPECT--
<pdf>invoice</pdf>
300
system clock
2
production
return if return
return if
