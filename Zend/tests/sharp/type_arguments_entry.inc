<?php
// The fixtures every type_arguments_entry_*.phpt test checks values entering PHP# from plain PHP against.
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/harness/Pager.inc';
require __DIR__ . '/../../../sharp/composer/library/Sharp/Encoding/Json.sharp';
require_once __DIR__ . '/harness/fixture.inc';
fixture('TypeArgumentsChecks');

function attempt(callable $call): void
{
    try {
        echo $call(), "\n";
    } catch (Error $error) {
        echo $error->getMessage(), "\n";
    }
}
