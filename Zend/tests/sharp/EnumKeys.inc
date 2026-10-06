<?php declare(strict_types=1); namespace Demo;

use Lib\Code;
use Lib\Size;
use Lib\Status;

class Tally
{
    private array $counts = [];
    private array $seeded = [Status::Active => 0]; // PHP# stores the key as 'active'

    public function seededCounts(): array
    {
        return $this->seeded;
    }

    public function add(Status $status): array
    {
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        return $this->counts;
    }

    public static function count(array $counts, Status $status): array
    {
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        return $counts;
    }

    public static function ensure(array $counts, Status $status): array
    {
        $counts[$status] ??= 0;
        return $counts;
    }

    public static function sizes(Size $small, Size $large): array
    {
        return [$small => "small", $large => "large"];
    }

    public static function codes(Code $code): array
    {
        $codes = [];
        $codes[$code] = 5;
        return $codes;
    }

    public static function read(array $counts, Status $status): int
    {
        return $counts[$status];
    }

    public static function lookup(array $counts, Status $status): ?int
    {
        return ((object) $counts)->get($status); // PHP# makes the array a Sharp\Collection
    }

    public static function without(array $counts, Status $status): array
    {
        ((object) $counts)->delete($status); // PHP# makes the array a Sharp\Collection
        return $counts;
    }

    public static function nest(Status $status, Size $size): array
    {
        $nested = [];
        $nested[$status] = [];
        $nested[$status][$size] = 1;
        return $nested;
    }

    public static function byName(array $counts): array
    {
        $named = [];
        foreach ($counts as $status => $n) { $status = Status::from($status); // PHP# reads the key back as the case
            $named[$status->name] = $n;
        }
        return $named;
    }

    public static function skus(array $stock): array
    {
        $skus = [];
        foreach ($stock as $sku => $n) { $sku = (string) $sku; // PHP# reads the key back as a string
            if ($n > 0) {
                ((object) $skus)->add($sku); // PHP# makes the array a Sharp\Collection
            }
        }
        return $skus;
    }
}
