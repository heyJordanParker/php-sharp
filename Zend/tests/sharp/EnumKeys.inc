<?php declare(strict_types=1); namespace Demo;

use Lib\Code;
use Lib\Size;
use Lib\Standing;

class Headcount
{
    private array $counts = [];
    private array $seeded = [Standing::Active->value => 0];

    public function seededCounts(): array
    {
        return $this->seeded;
    }

    public function add(Standing $status): array
    {
        $this->counts[$status->value] = ($this->counts[$status->value] ?? 0) + 1;
        return $this->counts;
    }

    public static function count(array $counts, Standing $status): array
    {
        $counts[$status->value] = ($counts[$status->value] ?? 0) + 1;
        return $counts;
    }

    public static function ensure(array $counts, Standing $status): array
    {
        $counts[$status->value] ??= 0;
        return $counts;
    }

    public static function sizes(Size $small, Size $large): array
    {
        return [$small->value => "small", $large->value => "large"];
    }

    public static function codes(Code $code): array
    {
        $codes = [];
        $codes[$code->value] = 5;
        return $codes;
    }

    public static function lookup(array $counts, Standing $status): ?int
    {
        return ((object) $counts)->get($status->value); // PHP# makes the array a Sharp\Collection
    }

    public static function without(array $counts, Standing $status): array
    {
        ((object) $counts)->delete($status->value); // PHP# makes the array a Sharp\Collection
        return $counts;
    }

    public static function nest(Standing $status, Size $size): array
    {
        $nested = [];
        $nested[$status->value] = [];
        $nested[$status->value][$size->value] = 1;
        return $nested;
    }

    public static function byName(array $counts): array
    {
        $named = [];
        foreach ($counts as $status => $n) { $status = Standing::from($status); // PHP# reads the key back as the case
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
