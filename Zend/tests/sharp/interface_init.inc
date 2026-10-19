<?php

use InterfaceInit\Boxed;
use InterfaceInit\Report;
use InterfaceInit\Sized;

echo 'set while each object is created, read through the interface: ', json_encode(Report::made()), "\n";
echo 'a copy sets it: ', json_encode(Report::copied()), "\n";
echo 'plain PHP sees the interface property get-only: ', json_encode((new ReflectionProperty(Sized::class, 'max'))->hasHook(PropertyHookType::Set)), "\n";
try {
    $boxed = new Boxed();
    $boxed->max = 9;
} catch (Error $error) {
    echo 'plain PHP writes it after creation: ', $error->getMessage(), "\n";
}
