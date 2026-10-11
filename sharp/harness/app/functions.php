<?php

namespace App {
    function charge(mixed ...$arguments): \App\Receipt
    {
        return new \App\Receipt();
    }
}

namespace {
    // Plain PHP functions the spec's examples call by a bare name.
    function deliver(mixed ...$arguments): void
    {
    }

    function render(mixed ...$arguments): void
    {
    }
}
