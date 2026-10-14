<?php declare(strict_types=1);

namespace Lib;

final class JobFailed
{
    public function __construct(public string $job) {}
}
