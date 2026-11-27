--TEST--
A Result's ok() gives the Ok value or null, and its unwrap() gives the Ok value or throws LogicException with the error's message, as Rust's Result::ok and unwrap do
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$gateway = new Demo\Gateway();
var_dump($gateway->doubledOrNull(5), $gateway->doubledOrNull(0), $gateway->doubledOrThrow(5));
try {
    $gateway->doubledOrThrow(0);
} catch (LogicException $error) {
    echo $error::class, ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
int(10)
NULL
int(10)
LogicException: declined: empty cart
