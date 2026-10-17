<?php

namespace Lib;

// A plain PHP base class whose parameters are named as PHP# keywords, `$event` and `$match`, as a framework's are.
abstract class Behavior
{
    abstract public function submit(string $form, string $event): string;

    public function run(bool $match): string
    {
        return $match ? 'matched' : 'missed';
    }
}
