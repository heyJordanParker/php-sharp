<?php declare(strict_types=1); namespace Demo;

use SplFileObject;

class Import
{
    public function __construct(private array $pending, private \Demo\Row $latest)
    {
    }

    public static function rows(string $path): iterable
    {
        $file = new \SplFileObject($path);
        while (!((object) $file)->eof()) {
            $text = \rtrim(((object) $file)->fgets());
            if ($text !== "") {
                yield \explode(",", $text);
            }
        }
    }

    public function all(): iterable
    {
        yield from $this->pending;
        yield $this->latest;
    }

    public static function fields(iterable $rows): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += \count($row);
        }
        return $total;
    }

    public static function last(iterable $rows): string
    {
        $found = "";
        foreach ($rows as $row) {
            $found = $row->number;
        }
        return $found;
    }

    public function lastPending(): string { return \Demo\Import::last($this->pending); }
}

class Row
{
    public function __construct(public readonly string $number)
    {
    }
}
