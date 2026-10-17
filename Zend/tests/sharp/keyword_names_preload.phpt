--TEST--
A preloaded PHP# class keeps the names `@` makes of keywords: `@event` is PHP's `$event`, and `@this` runs beside the object `this`, as a local named as a PHP superglobal runs beside it
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/keyword_names_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(KeywordNames\Form::class, false));
$form = new KeywordNames\Form();
echo $form->submit(form: 'x', event: 'y'), "\n";
echo $form->label('  hi  '), "\n";
echo $form->each(['a', 'b']), ' ', $form->tested('text'), ' ', $form->wrapped('a'), "\n";
echo $form->named(), "\n";
$request = new KeywordNames\Request('srv');
echo $request->_SERVER, ' ', $request->named(), "\n";
var_dump(isset($_SERVER['argv']));
?>
--EXPECT--
bool(true)
x:y
[hi]form
ab text <a>
a:b d:c [e]form [f]form
srv n!n! srv
bool(true)
