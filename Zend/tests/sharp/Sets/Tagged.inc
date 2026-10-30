<?php declare(strict_types=1); namespace Demo;

use Lib\Probe;
use Lib\Size;
use Lib\Standing;

class Tagged
{
    #[\Sharp\Crossing(['set', null])] public \Sharp\Set $tags;
    #[\Sharp\Crossing(['set', null])] public \Sharp\Set $states { get => $this->states; set(\Sharp\Set|array $value) { $value = \is_array($value) ? new \Sharp\Set($value) : $value; $this->writes += 1; $this->states = $value; } } // PHP# makes the PHP array plain PHP writes a Set
    public private(set) int $writes = 0;
    #[\Sharp\Crossing(['set', null])] private \Sharp\Set $sizes;
    private int $calls = 0;

    #[\Sharp\Crossing(['set', null])] public \Sharp\Set $ids; #[\Sharp\Crossing(['set', null])] public readonly \Sharp\Set $labels; public function __construct(\Sharp\Set|array $ids, \Sharp\Set|array $labels) { $this->ids = \is_array($ids) ? new \Sharp\Set($ids) : $ids; $this->labels = \is_array($labels) ? new \Sharp\Set($labels) : $labels; // PHP# declares each promoted Set and makes it a Set

    }

    public static function strings(\Sharp\Set|array $tags): array { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags;

        $added = \Sharp\Internal\addKey($tags, "vip");
        $again = \Sharp\Internal\addKey($tags, "vip");
        $removed = \Sharp\Internal\removeKey($tags, "new");
        $absent = \Sharp\Internal\removeKey($tags, "none");
        $joined = "";
        foreach ($tags as $tag) {
            $joined = $joined . $tag . ";";
        }
        return [$added, $again, $removed, $absent, \Sharp\Internal\containsKey($tags, "5"), \Sharp\Internal\containsKey($tags, "new"), \Sharp\Internal\size($tags), \Sharp\Internal\keys($tags), $joined];
    }

    public static function reads(\Sharp\Set|array $tags): array { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags;

        return [
            \Sharp\Internal\keys(\Sharp\Internal\filterElements($tags, fn ($tag) => $tag !== "vip")),
            \Sharp\Internal\map($tags, fn ($tag) => \mb_strlen($tag, "UTF-8")),
            \Sharp\Internal\any($tags, fn ($tag) => $tag === "5"),
            \Sharp\Internal\first($tags, fn ($tag) => \mb_strlen($tag, "UTF-8") > 2),
            \Sharp\Internal\sortedBy($tags, fn ($tag) => $tag, null),
            Probe::join(",", \Sharp\Internal\arrayOf($tags)),
        ];
    }

    public static function missing(\Sharp\Set|array $tags): string { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags; return \Sharp\Internal\first($tags, fn ($tag) => $tag === "none"); }

    public static function ints(\Sharp\Set|array $ids): array { $ids = \is_array($ids) ? new \Sharp\Set($ids) : $ids;

        $more = new \Sharp\Set([3, 1, 3]);
        return [\Sharp\Internal\addKey($ids, 4), \Sharp\Internal\addKey($ids, 4), \Sharp\Internal\removeKey($ids, 7), \Sharp\Internal\containsKey($ids, 2), \Sharp\Internal\keys($ids), \Sharp\Internal\keys($more), \Sharp\Internal\sumOf($ids, fn ($id) => $id)];
    }

    public static function cases(\Sharp\Set|array $states): array { $states = \is_array($states) ? new \Sharp\Set($states) : $states;

        $sizes = new \Sharp\Set([Size::Large, Size::Small, Size::Large]);
        $closed = \Sharp\Internal\addKey($states, Standing::Closed);
        $active = \Sharp\Internal\addKey($states, Standing::Active);
        $has = \Sharp\Internal\containsKey($states, Standing::Closed);
        $removed = \Sharp\Internal\removeKey($states, Standing::Active);
        return [$closed, $active, $has, $removed, \Sharp\Internal\keys($states), \Sharp\Internal\keys($sizes), \Sharp\Internal\containsKey($sizes, Size::Small)];
    }

    public function properties(Standing $state): array
    {
        $vip = \Sharp\Internal\addKey($this->tags, "vip");
        $again = \Sharp\Internal\addKey($this->tags, "vip");
        $removed = \Sharp\Internal\removeKey($this->tags, "new");
        $tags = \Sharp\Internal\keys($this->tags);
        $added = $this->states->add($state);
        $twice = $this->states->add($state);
        $dropped = $this->states->remove($state);
        return [$vip, $again, $removed, $tags, $added, $twice, $dropped, $this->states->contains($state), $this->writes, $this->sizes->add(4), $this->sizes->contains(4), $this->ids->add(9), $this->ids->toList()];
    }

    public function once(\Sharp\Set|array|null $maybe): array { $maybe = \is_array($maybe) ? new \Sharp\Set($maybe) : $maybe;

        $copy = $this->copy();
        $copied = \Sharp\Internal\addKey($copy, "copy");
        $kept = \Sharp\Internal\containsKey($this->tags, "copy");
        $added = \Sharp\Internal\addKey($this->tags, $this->next());
        $named = \Sharp\Internal\keys($this->tags);
        \Sharp\Internal\clear($this->tags);
        return [$copied, $kept, $added, $this->calls, $named, \Sharp\Internal\keys($this->tags), $maybe === null ? null : \Sharp\Internal\addKey($maybe, "x"), $maybe === null ? null : \Sharp\Internal\containsKey($maybe, "x"), $maybe === null ? null : \Sharp\Internal\size($maybe)];
    }

    private function copy(): \Sharp\Set|array { return $this->tags; }

    private function next(): string
    {
        $this->calls += 1;
        return "n{$this->calls}";
    }

    public static function lambdas(array $names): array
    {
        $prefix = "v";
        $check = function (\Sharp\Set|array $tags) use ($prefix) { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags; return \Sharp\Internal\containsKey($tags, $prefix); };
        $size = function (\Sharp\Set|array $tags) { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags;
            return \Sharp\Internal\size($tags);
        };
        return [$check(new \Sharp\Set($names)), $check(new \Sharp\Set([$prefix])), $size(new \Sharp\Set(["a", "a", "b"]))];
    }

    public static function groups(\Sharp\Set|array ...$groups): array { $groups = \array_map(fn ($collection) => \is_array($collection) ? new \Sharp\Set($collection) : $collection, $groups);

        $found = [];
        foreach ($groups as $group) {
            \Sharp\Internal\add($found, \Sharp\Internal\containsKey($group, "a"));
        }
        return $found;
    }

    public static function maybe(\Sharp\Set|array|null ...$groups): array { $groups = \array_map(fn ($collection) => \is_array($collection) ? new \Sharp\Set($collection) : $collection, $groups);

        $found = [];
        foreach ($groups as $group) {
            \Sharp\Internal\add($found, $group === null ? null : \Sharp\Internal\containsKey($group, "a"));
        }
        return $found;
    }
}
