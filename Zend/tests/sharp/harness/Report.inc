<?php declare(strict_types=1);

namespace Lib;

class Report
{
    public string $built = '';

    public function __construct(public readonly int $id)
    {
        $this->built = "Report::__construct($id)";
    }
}
