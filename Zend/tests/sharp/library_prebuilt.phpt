--TEST--
A project's PHP# code runs the standard library's List, Map, Json and Events from the compiled files PHP# ships in the library's package, and the project's compile writes no compiled file of the library
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Prebuilt\Basket;

$listening = Basket::watch();
$basket = new Basket();
$basket->sell('tea', 2);
$basket->sell('cake', 1);
$listening = null;
$basket->sell('pie', 3);
echo implode(', ', Basket::$log), "\n";
echo $basket->counted(), "\n";
$library = 'vendor/heyjordanparker/php-sharp-composer';
echo is_dir(__DIR__ . "/.sharp/current/$library") ? "The project compiled the library.\n" : "The project compiled no file of the library.\n";
echo is_file(__DIR__ . "/$library/.sharp/current/library/Sharp/Encoding/Json.sharpc") ? "The library's package holds its compiled files.\n" : "The library's package holds no compiled file.\n";
?>
--EXPECT--
heard tea, watched 2, heard cake, watched 1, heard pie
{"tea":2,"cake":1,"pie":3}
The project compiled no file of the library.
The library's package holds its compiled files.
