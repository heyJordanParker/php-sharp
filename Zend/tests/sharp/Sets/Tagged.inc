<?php declare(strict_types=1); namespace Demo;

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

        $added = ((object) $tags)->add("vip"); // PHP# makes the Set a Sharp\Collection
        $again = ((object) $tags)->add("vip");
        $removed = ((object) $tags)->remove("new");
        $absent = ((object) $tags)->remove("none");
        $joined = "";
        foreach ($tags as $tag) {
            $joined = $joined . $tag . ";";
        }
        return [$added, $again, $removed, $absent, ((object) $tags)->contains("5"), ((object) $tags)->contains("new"), ((object) $tags)->count(), ((object) $tags)->toList(), $joined];
    }

    public static function reads(\Sharp\Set|array $tags): array { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags;

        return [
            ((object) ((object) $tags)->filter(fn ($tag) => $tag !== "vip"))->toList(),
            ((object) $tags)->map(fn ($tag) => \strlen($tag)),
            ((object) $tags)->any(fn ($tag) => $tag === "5"),
            ((object) $tags)->first(fn ($tag) => \strlen($tag) > 2),
            ((object) $tags)->sortedBy(fn ($tag) => $tag),
            \implode(",", \Sharp\Internal\arrayOf($tags)),
        ];
    }

    public static function missing(\Sharp\Set|array $tags): string { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags; return ((object) $tags)->first(fn ($tag) => $tag === "none"); }

    public static function ints(\Sharp\Set|array $ids): array { $ids = \is_array($ids) ? new \Sharp\Set($ids) : $ids;

        $more = new \Sharp\Set([3, 1, 3]);
        return [((object) $ids)->add(4), ((object) $ids)->add(4), ((object) $ids)->remove(7), ((object) $ids)->contains(2), ((object) $ids)->toList(), ((object) $more)->toList(), ((object) $ids)->sumOf(fn ($id) => $id)];
    }

    public static function cases(\Sharp\Set|array $states): array { $states = \is_array($states) ? new \Sharp\Set($states) : $states;

        $sizes = new \Sharp\Set([Size::Large, Size::Small, Size::Large]);
        $closed = ((object) $states)->add(Standing::Closed);
        $active = ((object) $states)->add(Standing::Active);
        $has = ((object) $states)->contains(Standing::Closed);
        $removed = ((object) $states)->remove(Standing::Active);
        return [$closed, $active, $has, $removed, ((object) $states)->toList(), ((object) $sizes)->toList(), ((object) $sizes)->contains(Size::Small)];
    }

    public function properties(Standing $state): array
    {
        $vip = $this->tags->add("vip");
        $again = $this->tags->add("vip");
        $removed = $this->tags->remove("new");
        $tags = $this->tags->toList();
        $added = $this->states->add($state);
        $twice = $this->states->add($state);
        $dropped = $this->states->remove($state);
        return [$vip, $again, $removed, $tags, $added, $twice, $dropped, $this->states->contains($state), $this->writes, $this->sizes->add(4), $this->sizes->contains(4), $this->ids->add(9), $this->ids->toList()];
    }

    public function once(\Sharp\Set|array|null $maybe): array { $maybe = \is_array($maybe) ? new \Sharp\Set($maybe) : $maybe;

        $copy = $this->copy();
        $copied = ((object) $copy)->add("copy");
        $kept = $this->tags->contains("copy");
        $added = $this->tags->add($this->next());
        $named = $this->tags->toList();
        $this->tags->clear();
        return [$copied, $kept, $added, $this->calls, $named, $this->tags->toList(), ((object) $maybe)?->add("x"), ((object) $maybe)?->contains("x"), ((object) $maybe)?->count()];
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
        $check = function (\Sharp\Set|array $tags) use ($prefix) { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags; return ((object) $tags)->contains($prefix); };
        $size = function (\Sharp\Set|array $tags) { $tags = \is_array($tags) ? new \Sharp\Set($tags) : $tags;
            return ((object) $tags)->count();
        };
        return [$check(new \Sharp\Set($names)), $check(new \Sharp\Set([$prefix])), $size(new \Sharp\Set(["a", "a", "b"]))];
    }

    public static function groups(\Sharp\Set|array ...$groups): array { $groups = \array_map(fn ($collection) => \is_array($collection) ? new \Sharp\Set($collection) : $collection, $groups);

        $found = [];
        foreach ($groups as $group) {
            ((object) $found)->add(((object) $group)->contains("a"));
        }
        return $found;
    }

    public static function maybe(\Sharp\Set|array|null ...$groups): array { $groups = \array_map(fn ($collection) => \is_array($collection) ? new \Sharp\Set($collection) : $collection, $groups);

        $found = [];
        foreach ($groups as $group) {
            ((object) $found)->add(((object) $group)?->contains("a"));
        }
        return $found;
    }
}
