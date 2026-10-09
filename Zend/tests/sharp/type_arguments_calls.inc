<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/TypeArgumentsCalls.sharp';

function shown(object $object): string
{
    return get_class($object) . '<' . implode(', ', arguments($object)) . '>';
}
