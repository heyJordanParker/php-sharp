<?php

namespace App;

// Records what each annotated line of a running example gives, one JSON line per line, for sharp/bin/spec.
final class Outcome
{
    public static function value(int $line, mixed $value): void
    {
        self::record(['line' => $line, 'kind' => 'value', 'type' => gettype($value), 'value' => is_string($value) ? $value : null, 'export' => var_export($value, true)]);
    }

    public static function attempt(int $line, callable $statement): void
    {
        try {
            $statement();
            self::ran($line);
        } catch (\Throwable $e) {
            self::threw($line, $e);
        }
    }

    public static function ran(int $line): void
    {
        self::record(['line' => $line, 'kind' => 'ran']);
    }

    public static function threw(int $line, \Throwable $e): void
    {
        $types = array_map(
            static fn (string $name): string => substr(strrchr("\\$name", '\\'), 1),
            [get_class($e), ...array_values(class_parents($e)), ...array_values(class_implements($e))],
        );
        self::record(['line' => $line, 'kind' => 'threw', 'class' => get_class($e), 'message' => $e->getMessage(), 'types' => $types]);
    }

    private static function record(array $outcome): void
    {
        file_put_contents(getenv('SHARP_SPEC_OUTCOMES'), json_encode($outcome, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
    }
}
