<?php

namespace Mailchimp;

final class Mailchimp
{
    public static function lists(): self
    {
        return new self();
    }

    public function addListMember(): void
    {
    }
}
