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

namespace {
    /** Every attribute of `$class`, its members and their parameters, one per line, as Reflection reads it. */
    function describe(string $class): string
    {
        $class = new ReflectionClass($class);
        $targets = ['class ' . $class->getName() => $class];
        foreach ($class->getProperties() as $property) {
            $targets['property $' . $property->getName()] = $property;
        }
        foreach ($class->getMethods() as $method) {
            $targets['method ' . $method->getName()] = $method;
            foreach ($method->getParameters() as $parameter) {
                $targets['parameter $' . $parameter->getName() . ' of ' . $method->getName()] = $parameter;
            }
        }

        $lines = '';
        foreach ($targets as $target => $reflector) {
            foreach ($reflector->getAttributes() as $attribute) {
                $lines .= "$target: {$attribute->getName()} " . json_encode($attribute->getArguments()) . "\n";
            }
        }

        return $lines;
    }
}
