<?php declare(strict_types=1);

namespace Demo\InternalReach {
    /** Plain PHP of the internal members' namespace, which resolves a member by name as a framework does. */
    final class Dispatch
    {
        public static function call(string|array $target, mixed ...$arguments): mixed
        {
            return call_user_func($target, ...$arguments);
        }

        public static function read(object $object, string $property): mixed
        {
            return $object->$property;
        }
    }
}

namespace Demo\Reaching {
    /** Plain PHP outside the internal members' namespace, which resolves a member by name as a framework does. */
    final class Dispatch
    {
        public static function call(string|array $target, mixed ...$arguments): mixed
        {
            return call_user_func($target, ...$arguments);
        }
    }
}
