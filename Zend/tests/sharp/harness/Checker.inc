<?php declare(strict_types=1);

namespace Lib;

/** A plain PHP parent whose methods take and return arrays, which a PHP# subclass overrides with `Map`s. */
abstract class Checker
{
    public function errors(): array
    {
        return [];
    }

    public function merged(array $extra): array
    {
        return $extra;
    }

    public function report(): string
    {
        return json_encode($this->errors()) . ' ' . json_encode($this->merged(['b' => '2']));
    }
}
