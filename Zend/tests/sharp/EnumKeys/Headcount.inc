<?php declare(strict_types=1); namespace Demo;

use Lib\Code;
use Lib\Size;
use Lib\Standing;

class Headcount
{
    #[\Sharp\Crossing(['map', 'Lib\Standing', null])] private \Sharp\Map $counts;
    #[\Sharp\Crossing(['map', 'Lib\Standing', null])] private \Sharp\Map $seeded;

    final public function seededCounts(): \Sharp\Map|array
    {
        return $this->seeded;
    }

    final public function add(Standing $status): \Sharp\Map|array
    {
        $this->counts[$status] = ($this->counts[$status] ?? 0) + 1;
        return $this->counts;
    }

    final public static function count(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts; // PHP# makes the array a Map, its keys the cases

        $counts[$status] = ($counts[$status] ?? 0) + 1;
        return $counts;
    }

    final public static function ensure(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        $counts[$status] ??= 0;
        return $counts;
    }

    final public static function sizes(Size $small, Size $large): \Sharp\Map|array
    {
        return new \Sharp\Map([$small, "small", $large, "large"]);
    }

    final public static function codes(Code $code): \Sharp\Map|array
    {
        $codes = new \Sharp\Map();
        $codes[$code] = 5;
        return $codes;
    }

    final public static function lookup(\Sharp\Map|array $counts, Standing $status): ?int { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        return ((object) $counts)->get($status); // PHP# makes the Map a Sharp\Collection
    }

    final public static function without(\Sharp\Map|array $counts, Standing $status): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        ((object) $counts)->remove($status);
        return $counts;
    }

    final public static function nest(Standing $status, Size $size): \Sharp\Map|array
    {
        $nested = new \Sharp\Map();
        $sizes = new \Sharp\Map();
        $sizes[$size] = 1;
        $nested[$status] = $sizes;
        return $nested;
    }

    final public static function byName(\Sharp\Map|array $counts): \Sharp\Map|array { $counts = \is_array($counts) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [Standing::from((string) $key), $value], \array_keys($counts), $counts))) : $counts;

        $named = new \Sharp\Map();
        foreach ($counts as $status => $n) {
            $named[$status->name] = $n;
        }
        return $named;
    }

    final public static function skus(\Sharp\Map|array $stock): array { $stock = \is_array($stock) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($stock), $stock))) : $stock; // PHP# makes the array a Map, its keys strings

        $skus = [];
        foreach ($stock as $sku => $n) {
            if ($n > 0) {
                ((object) $skus)->add($sku); // PHP# makes the array a Sharp\Collection
            }
        }
        return $skus;
    }

    final public static function grouped(array $sizes): \Sharp\Map|array
    {
        return ((object) $sizes)->groupBy(fn ($n) => $n > 1 ? Standing::Closed : Standing::Active); // PHP# makes the array a Sharp\Collection
    }

    final public static function groupNames(array $sizes): array
    {
        $names = [];
        foreach (((object) $sizes)->groupBy(fn ($n) => $n > 1 ? Standing::Closed : Standing::Active) as $status => $group) {
            ((object) $names)->add($status->name); // PHP# makes the array a Sharp\Collection
        }
        return $names;
    }
}
