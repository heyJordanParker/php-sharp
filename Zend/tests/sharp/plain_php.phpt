--TEST--
PHP# runs plain PHP
--FILE--
<?php

final class Greeting
{
    public function __construct(private readonly string $name) {}

    public function text(): string
    {
        return "Hello, {$this->name}!";
    }
}

echo (new Greeting('PHP#'))->text(), "\n";
?>
--EXPECT--
Hello, PHP#!
