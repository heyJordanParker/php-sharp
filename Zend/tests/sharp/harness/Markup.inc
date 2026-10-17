<?php declare(strict_types=1);

// Plain PHP whose `...` parameters collect the named arguments that match no other parameter, as PHP collects them.
namespace Lib {
    final class Markup
    {
        public static function tag(string $name, string ...$attributes): string
        {
            return $name . json_encode($attributes);
        }

        public function open(string $name, string ...$attributes): string
        {
            return '<' . $name . json_encode($attributes);
        }
    }

    final class Sized
    {
        /** @var array<array-key, int> */
        public array $sizes;

        public function __construct(public string $name, int ...$sizes)
        {
            $this->sizes = $sizes;
        }

        public function describe(): string
        {
            return $this->name . json_encode($this->sizes);
        }
    }

    #[\Attribute(\Attribute::TARGET_ALL)]
    final class Generic
    {
        /** @var array<array-key, string> */
        public array $types;

        public function __construct(string ...$types)
        {
            $this->types = $types;
        }
    }
}

namespace {
    function markup_attributes(string $name, string ...$attributes): string
    {
        return $name . json_encode($attributes);
    }
}
