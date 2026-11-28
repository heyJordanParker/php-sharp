--TEST--
A read of bad fields by unserialize in a request with no autoloader throws a ValueError that lists each problem after its path, and loads no class to do it
--FILE--
<?php
require __DIR__ . '/Queued/Speed.sharp';
require __DIR__ . '/Queued/Parcel.sharp';

foreach (['O:13:"Queued\Parcel":0:{}', 'O:13:"Queued\Parcel":2:{s:5:"grams";s:3:"ten";s:5:"speed";s:7:"Express";}'] as $data) {
    try {
        var_dump(unserialize($data));
    } catch (Throwable $error) {
        echo $error::class, ': ', $error->getMessage(), "\n";
    }
}
var_dump(class_exists(Sharp\ParseError::class, false));
?>
--EXPECT--
ValueError: grams: missing; speed: missing
ValueError: grams: expected int, got string 'ten'; speed: expected Queued.Speed, got string 'Express'
bool(false)
