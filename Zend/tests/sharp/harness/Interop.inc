<?php

namespace Lib\Interop;

// A plain PHP library's types, which the PHP# side of the interop matrix extends, implements and uses.

class PlainLedger
{
    public function count(): int
    {
        return 31;
    }

    public function recordEntry(): int
    {
        return 32;
    }

    public function total(): int
    {
        return 33;
    }
}

abstract class PlainAccount
{
    public function count(): int
    {
        return 31;
    }

    public function recordEntry(): int
    {
        return 32;
    }

    public function total(): int
    {
        return 33;
    }
}

interface PlainRecording
{
    public function count(): int;

    public function recordEntry(): int;

    public function total(): int;
}

trait PlainCounting
{
    public function count(): int
    {
        return 31;
    }

    public function recordEntry(): int
    {
        return 32;
    }

    public function total(): int
    {
        return 33;
    }
}
