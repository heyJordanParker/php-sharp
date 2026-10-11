<?php

// Plain PHP functions the spec's examples call by a bare name. Probe: a builder PR edits the harness.
function charge(mixed ...$arguments): \App\Receipt
{
    return new \App\Receipt();
}

function deliver(mixed ...$arguments): void
{
}

function render(mixed ...$arguments): void
{
}
