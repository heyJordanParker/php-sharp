<?php
require __DIR__ . '/type_arguments.inc';
require __DIR__ . '/harness/PlainPicker.inc';
require __DIR__ . '/harness/Wrapper.inc';
require_once __DIR__ . '/harness/fixture.inc';
fixture('TypeArgumentsCalls');

function shown(object $object): string
{
    return get_class($object) . '<' . implode(', ', arguments($object)) . '>';
}
