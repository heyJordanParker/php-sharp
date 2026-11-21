--TEST--
A plain PHP closure that crosses into a PHP# function type has each call's result checked: a wrong type throws the crossing's TypeError naming the function type, and text that is not UTF-8 is made valid
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\Library;

// 0.6.41 let each result in unchecked, and Cache.set failed reading `.data` of it. A string crosses into `Bytes` as
// its bytes, as an argument does, and a closure kept for `() => string` is checked for `() => Bytes` where it crosses
// again.
$path = sys_get_temp_dir() . '/sharp-closure-crossing-' . getmypid() . '.sqlite';
$cache = new Sharp\Data\Cache($path);
foreach (['int' => fn () => 42, 'kept' => Library::kept(fn () => 42)] as $key => $make) {
    try {
        $cache->getOrCreate($key, $make);
    } catch (TypeError $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
var_dump($cache->getOrCreate('text', fn () => 'text'));
unset($cache);
unlink($path);

// 0.6.41 kept the byte E9, so the text was not UTF-8.
$made = Library::made(fn () => "caf\xE9");
var_dump(mb_strlen($made), bin2hex($made), Library::made(fn (): string => 'café'));
?>
--EXPECT--
TypeError: Function<Sharp.Bytes()>: Return value must be of type Sharp.Bytes, int returned
TypeError: Function<Sharp.Bytes()>: Return value must be of type Sharp.Bytes, int returned
string(4) "text"
int(4)
string(12) "636166efbfbd"
string(5) "café"
