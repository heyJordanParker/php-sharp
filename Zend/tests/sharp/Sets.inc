<?php declare(strict_types=1); namespace Demo;

use Lib\Size;
use Lib\Standing;

class Tagged
{
    public array $tags = ["new" => "new"];
    public array $states = [] { get => $this->states; set { $this->writes += 1; $this->states = $value; } }
    public private(set) int $writes = 0;
    private array $sizes = [];
    private int $calls = 0;

    public array $ids; public readonly array $labels; public function __construct(array $ids, array $labels) { $this->ids = \Sharp\Set::from($ids); $this->labels = \Sharp\Set::from($labels); // PHP# declares each promoted Set and makes it a Set

    }

    public static function strings(array $tags): array { $tags = \Sharp\Set::from($tags);

        $added = !isset($tags["vip"]) && ($tags += ["vip" => "vip"]);
        $again = !isset($tags["vip"]) && ($tags += ["vip" => "vip"]);
        $removed = isset($tags["new"]) && ((object) $tags)->delete("new") === null; // PHP# makes the array a Sharp\Collection
        $absent = isset($tags["none"]) && ((object) $tags)->delete("none") === null;
        $joined = "";
        foreach ($tags as $tag) {
            $joined = $joined . $tag . ";";
        }
        return [$added, $again, $removed, $absent, isset($tags["5"]), isset($tags["new"]), \count($tags), \array_values($tags), $joined];
    }

    public static function reads(array $tags): array { $tags = \Sharp\Set::from($tags);

        return [
            \array_values(((object) $tags)->filterValues(fn ($tag) => $tag !== "vip")),
            ((object) $tags)->map(fn ($tag) => \strlen($tag)),
            ((object) $tags)->any(fn ($tag) => $tag === "5"),
            ((object) $tags)->first(fn ($tag) => \strlen($tag) > 2),
            ((object) $tags)->sortedBy(fn ($tag) => $tag),
            \implode(",", $tags),
        ];
    }

    public static function missing(array $tags): string { $tags = \Sharp\Set::from($tags); return ((object) $tags)->first(fn ($tag) => $tag === "none"); }

    public static function ints(array $ids): array { $ids = \Sharp\Set::from($ids);

        $more = [3 => 3, 1 => 1, 3 => 3];
        return [!isset($ids[4]) && ($ids += [4 => 4]), !isset($ids[4]) && ($ids += [4 => 4]), isset($ids[7]) && ((object) $ids)->delete(7) === null, isset($ids[2]), \array_values($ids), \array_values($more), ((object) $ids)->sumOf(fn ($id) => $id)];
    }

    public static function cases(array $states): array { $states = \Sharp\Set::from($states);

        $sizes = [Size::Large->value => Size::Large, Size::Small->value => Size::Small, Size::Large->value => Size::Large];
        $closed = !isset($states[Standing::Closed->value]) && ($states += [Standing::Closed->value => Standing::Closed]);
        $active = !isset($states[Standing::Active->value]) && ($states += [Standing::Active->value => Standing::Active]);
        $has = isset($states[Standing::Closed->value]);
        $removed = isset($states[Standing::Active->value]) && ((object) $states)->delete(Standing::Active->value) === null;
        return [$closed, $active, $has, $removed, \array_values($states), \array_values($sizes), isset($sizes[Size::Small->value])];
    }

    public function properties(Standing $state): array
    {
        $vip = !isset($this->tags["vip"]) && ($this->tags += ["vip" => "vip"]);
        $again = !isset($this->tags["vip"]) && ($this->tags += ["vip" => "vip"]);
        $removed = isset($this->tags["new"]) && $this->tags->delete("new") === null; // PHP# makes the array a Sharp\Collection
        $tags = \array_values($this->tags);
        $added = !isset($this->states[$state->value]) && ($this->states += [$state->value => $state]);
        $twice = !isset($this->states[$state->value]) && ($this->states += [$state->value => $state]);
        $dropped = isset($this->states[$state->value]) && $this->states->delete($state->value) === null;
        return [$vip, $again, $removed, $tags, $added, $twice, $dropped, isset($this->states[$state->value]), $this->writes, !isset($this->sizes[4]) && ($this->sizes += [4 => 4]), isset($this->sizes[4]), !isset($this->ids[9]) && ($this->ids += [9 => 9]), \array_values($this->ids)];
    }

    public function once(?array $maybe): array { $maybe = $maybe === null ? null : \Sharp\Set::from($maybe);

        $copied = !isset((${'receiver#1'} = $this->copy())["copy"]) && (${'receiver#1'} += ["copy" => "copy"]);
        $kept = isset($this->tags["copy"]);
        $added = !isset($this->tags[${'element#1'} = $this->next()]) && ($this->tags += [${'element#1'} => ${'element#1'}]);
        $named = \array_values($this->tags);
        $this->tags = [];
        return [$copied, $kept, $added, $this->calls, $named, \array_values($this->tags), (($maybe === null) ? null : (!isset($maybe["x"]) && ($maybe += ["x" => "x"]))), (($maybe === null) ? null : isset($maybe["x"])), (($maybe === null) ? null : \count($maybe))];
    }

    private function copy(): array { return $this->tags; }

    private function next(): string
    {
        $this->calls += 1;
        return "n{$this->calls}";
    }

    public static function lambdas(array $names): array
    {
        $prefix = "v";
        $check = function (array $tags) use ($prefix) { $tags = \Sharp\Set::from($tags); return isset($tags[$prefix]); };
        $size = function (array $tags) { $tags = \Sharp\Set::from($tags);
            return \count($tags);
        };
        return [$check($names), $check([$prefix]), $size(["a", "a", "b"])];
    }

    public static function groups(array ...$groups): array { $groups = \array_map(\Sharp\Set::from(...), $groups);

        $found = [];
        foreach ($groups as $group) {
            ((object) $found)->add(isset($group["a"]));
        }
        return $found;
    }

    public static function maybe(?array ...$groups): array { $groups = \array_map(fn ($set) => $set === null ? null : \Sharp\Set::from($set), $groups);

        $found = [];
        foreach ($groups as $group) {
            ((object) $found)->add((($group === null) ? null : isset($group["a"])));
        }
        return $found;
    }
}
