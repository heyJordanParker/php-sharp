<?php

require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/harness/Holder.inc';
require __DIR__ . '/TypeArguments.sharp';

function arguments(object $object): array
{
    return (new ReflectionObject($object))->getTypeArguments();
}

function show(string $serialized): void
{
    echo str_replace("\0", '\0', $serialized), "\n";
}
