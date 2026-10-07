<?php declare(strict_types=1);

namespace Lib {
    #[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
    final class Entity
    {
        public function __construct(public ?string $label = null, public int $order = 0)
        {
        }
    }

    #[\Attribute(\Attribute::TARGET_ALL)]
    final class Field
    {
        public function __construct(public ?string $label = null, public int $limit = 0)
        {
        }
    }

    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class Action
    {
        public function __construct(public bool $write, public float $weight)
        {
        }
    }
}

namespace Demo {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    final class Searchable
    {
    }

    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class Retry
    {
        public function __construct(public int $times)
        {
        }
    }
}
