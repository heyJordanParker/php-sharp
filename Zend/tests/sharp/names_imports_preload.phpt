--TEST--
Preloading keeps what each PHP# name resolves to: a library's function and constant through its imported namespace, a renamed import, an import and a class of the file's own namespace that shadow a default, and classes named like keywords
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/names_imports.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php

// Preloading declared the classes and functions before this request, which never ran the files that declare them.
var_dump(class_exists(Imports\Printer::class, false));
// PHP's preloading keeps no constant, so the request defines the library's constant, as a Composer `files` entry would.
define('Acme\Pdf\DEFAULT_DPI', 300);

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
bool(true)
<pdf>invoice</pdf>
300
system clock
2
production
return if return
return if
