<?php declare(strict_types=1); namespace Demo;

use Lib\Code;
use Lib\Size;
use Lib\Standing;

class Headcount
{
    #[\Sharp\Crossing(['map', 'Lib\Standing', null])] private \Sharp\Map $counts;
    #[\Sharp\Crossing(['map', 'Lib\Standing', null])] private \Sharp\Map $seeded;

    public function seededCounts(): \Sharp\Map|array
    {
        return $this->seeded;
    }

    public function add(Standing $status): \Sharp\Map|array
    {
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        return $this->counts;
    }

    public static function count(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts; // PHP# makes the array a Map, its keys the cases

        $counts[$status] = ($counts[$status] ?? 0) + 1;
        return $counts;
    }

    public static function ensure(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        $counts[$status] ??= 0;
        return $counts;
    }

    public static function sizes(Size $small, Size $large): \Sharp\Map|array
    {
        return new \Sharp\Map([$small, "small", $large, "large"]);
    }

    public static function codes(Code $code): \Sharp\Map|array
    {
        $codes = new \Sharp\Map();
        $codes[$code] = 5;
        return $codes;
    }

    public static function lookup(\Sharp\Map|array $counts, Standing $status): ?int { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        return \Sharp\Internal\get($counts, $status);
    }

    public static function without(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        \Sharp\Internal\removeKey($counts, $status);
        return $counts;
    }

    public static function nest(Standing $status, Size $size): \Sharp\Map|array
    {
        $nested = new \Sharp\Map();
        $sizes = new \Sharp\Map();
        $sizes[$size] = 1;
        $nested[$status] = $sizes;
        return $nested;
    }

    public static function byName(\Sharp\Map|array $counts): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

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
                \Sharp\Internal\add($skus, $sku);
            }
        }
        return $skus;
    }

    public static function grouped(array $sizes): \Sharp\Map|array
    {
        return \Sharp\Internal\groupBy($sizes, fn ($n) => $n > 1 ? Standing::Closed : Standing::Active, "Lib.Standing, List<int>");
    }

    public static function groupNames(array $sizes): array
    {
        $names = [];
        foreach (\Sharp\Internal\groupBy($sizes, fn ($n) => $n > 1 ? Standing::Closed : Standing::Active, "Lib.Standing, List<int>") as $status => $group) {
            \Sharp\Internal\add($names, $status->name);
        }
        return $names;
    }
}
