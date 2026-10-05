<?php

function attempt(callable $call): void
{
    try {
        var_dump($call());
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), ' in ', basename($e->getFile()), ' on line ', $e->getLine(), "\n";
    }
}
