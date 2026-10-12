<?php

namespace App;

// The exceptions the spec's examples throw, each taking the value it reports.
class SpecException extends \RuntimeException
{
    public function __construct(mixed $value = null)
    {
        parent::__construct(is_scalar($value) ? (string) $value : get_debug_type($value));
    }
}

final class UnknownPlan extends SpecException
{
}

final class BadPayload extends SpecException
{
}

final class NotPaid extends SpecException
{
}

final class NotFound extends SpecException
{
}

final class UnknownElement extends SpecException
{
}

final class InactiveTenant extends SpecException
{
}
