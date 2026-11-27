<?php

namespace Vendor\Outbox;

// A plain PHP library's mail, whose send the spec declares with an effect of its own (section 29).
final class Mail
{
    public static function send(): void
    {
    }
}
