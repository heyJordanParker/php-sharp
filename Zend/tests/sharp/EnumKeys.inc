<?php declare(strict_types=1); namespace Demo;

use Lib\Code;
use Lib\Size;
use Lib\Standing;

class Headcount
{
    private \Sharp\Map $counts;
    private \Sharp\Map $seeded;

    public function seededCounts(): \Sharp\Map
    {
        return $this->seeded;
    }

    public function add(Standing $status): \Sharp\Map
    {
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        return $this->counts;
    }

    public static function count(\Sharp\Map|array $counts, Standing $status): \Sharp\Map { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts; // PHP# makes the array a Map, its keys the cases

        $counts[$status] = ($counts[$status] ?? 0) + 1;
        return $counts;
    }

    public static function ensure(\Sharp\Map|array $counts, Standing $status): \Sharp\Map { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        $counts[$status] ??= 0;
        return $counts;
    }

    public static function sizes(Size $small, Size $large): \Sharp\Map
    {
        return new \Sharp\Map([$small, "small", $large, "large"]);
    }

    public static function codes(Code $code): \Sharp\Map
    {
        $codes = new \Sharp\Map();
        $codes[$code] = 5;
        return $codes;
    }

    public static function lookup(\Sharp\Map|array $counts, Standing $status): ?int { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        return ((object) $counts)->get($status); // PHP# makes the Map a Sharp\Collection
    }

    public static function without(\Sharp\Map|array $counts, Standing $status): \Sharp\Map { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        ((object) $counts)->remove($status);
        return $counts;
    }

    public static function nest(Standing $status, Size $size): \Sharp\Map
    {
        $nested = new \Sharp\Map();
        $sizes = new \Sharp\Map();
        $sizes[$size] = 1;
        $nested[$status] = $sizes;
        return $nested;
    }

    public static function byName(\Sharp\Map|array $counts): \Sharp\Map { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        $named = new \Sharp\Map();
        foreach ($counts as $status => $n) {
            $named[$status->name] = $n;
        }
        return $named;
    }

    public static function skus(\Sharp\Map|array $stock): array { $stock = \is_array($stock) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($stock), $stock))) : $stock; // PHP# makes the array a Map, its keys strings

        $skus = [];
        foreach ($stock as $sku => $n) {
            if ($n > 0) {
                ((object) $skus)->add($sku); // PHP# makes the array a Sharp\Collection
            }
        }
        return $skus;
    }

    public static function grouped(array $sizes): \Sharp\Map
    {
        return ((object) $sizes)->groupBy(fn ($n) => $n > 1 ? Standing::Closed : Standing::Active); // PHP# makes the array a Sharp\Collection
    }

    public static function groupNames(array $sizes): array
    {
        $names = [];
        foreach (((object) $sizes)->groupBy(fn ($n) => $n > 1 ? Standing::Closed : Standing::Active) as $status => $group) {
            ((object) $names)->add($status->name); // PHP# makes the array a Sharp\Collection
        }
        return $names;
    }
}
