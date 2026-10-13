<?php declare(strict_types=1);

namespace Lib;

class Wallet
{
    public function __construct(public int $balance = 0)
    {
    }
}

class AuditedWallet extends Wallet
{
    public bool $isEmpty = true;

    public function total(int $extra): int
    {
        return 100 + $extra;
    }
}
