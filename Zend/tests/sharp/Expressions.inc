<?php declare(strict_types=1); namespace Demo;

use Countable;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

class Expressions
{
    public static function positive(int $value): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("negative");
        }
        return $value;
    }

    public static function known(?int $value): int
    {
        return $value ?? throw new InvalidArgumentException("unknown");
    }

    public static function recover(int $value, \Demo\Box $steps): int
    {
        try {
            $steps->size += 1;
            return Expressions::positive($value);
        } catch (InvalidArgumentException | LogicException $failure) {
            $steps->size += 10;
            throw new RuntimeException("recovered", 0, $failure);
        } catch (Throwable) {
            return -1;
        } finally {
            $steps->size += 100;
        }
    }

    public static function describe(string $label, Countable $items): string
    {
        $token = \random_bytes(\count($items));
        return \sprintf("%s has %d items and a %d-byte token", $label, \count($items), \strlen($token));
    }

    public static function label(string $name, Countable $items): string
    {
        $plain = '${name} {$name} $name';
        $size = ((object) $items)->count();
        return "{$name}: {$size} items\x21 \u{2713} `{$plain}`
total";
    }
}
