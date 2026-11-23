<?php declare(strict_types=1);

namespace {
    /** Each listing plain PHP makes of an object's properties, from the namespace of the class that uses it. */
    trait ListsProperties
    {
        /**
         * The array cast comes first, while the object has no table of its properties, and again once the others
         * built it.
         *
         * @return list<string>
         */
        public static function listed(object $object): array
        {
            return [
                json_encode((array) $object),
                json_encode(get_object_vars($object)),
                var_export($object, true),
                json_encode((array) $object),
            ];
        }
    }
}

namespace Demo\InternalReach {
    /** Plain PHP of the internal members' namespace, which resolves a member by name as a framework does. */
    final class Dispatch
    {
        use \ListsProperties;

        public static function call(string|array $target, mixed ...$arguments): mixed
        {
            return call_user_func($target, ...$arguments);
        }

        public static function read(object $object, string $property): mixed
        {
            return $object->$property;
        }

        public static function write(object $object, string $property, mixed $value): mixed
        {
            $object->$property = $value;

            return $object->$property;
        }
    }
}

namespace Demo\Reaching {
    /** Plain PHP outside the internal members' namespace, which resolves a member by name as a framework does. */
    final class Dispatch
    {
        use \ListsProperties;

        public static function call(string|array $target, mixed ...$arguments): mixed
        {
            return call_user_func($target, ...$arguments);
        }
    }
}
