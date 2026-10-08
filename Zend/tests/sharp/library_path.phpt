--TEST--
A PHP# class calls every method of Sharp.IO.Path, none of which can fail
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/IO/Path.sharp';
require __DIR__ . '/PathCalls.sharp';

use Demo\PathCalls;

var_dump(PathCalls::fileName('/var/www/site/index.php'), PathCalls::directory('/var/www/site/index.php'));
var_dump(PathCalls::extension('/var/www/site/index.php'), PathCalls::fileNameWithoutExtension('/var/www/site/index.php'));
var_dump(PathCalls::extension('README'), PathCalls::directory('file.txt'));
?>
--EXPECT--
string(9) "index.php"
string(13) "/var/www/site"
string(3) "php"
string(5) "index"
string(0) ""
string(1) "."
