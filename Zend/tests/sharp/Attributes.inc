<?php declare(strict_types=1);

require __DIR__ . '/harness/Attributes.inc';

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
