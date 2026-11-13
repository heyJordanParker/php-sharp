<?php declare(strict_types=1);

namespace Lib;

use Closure;
use LogicException;

abstract class Root
{
}

// A plain PHP trait whose property and methods declare `self` and `parent`, which PHP keeps as written in a trait, and
// which name the class that uses it and that class's parent.
trait Chained
{
    public ?self $next = null;

    public function itself(): self
    {
        return $this;
    }

    public function root(): parent
    {
        return $this;
    }

    public function either(): self|Linked
    {
        return $this;
    }
}

// A plain PHP parent whose methods and closures declare `static`, `self` and `never`, which PHP# reads as the class each
// names and as `never`.
abstract class Linked extends Root
{
    use Chained;

    public function copy(): static
    {
        return clone $this;
    }

    public function fail(): never
    {
        throw new LogicException('never returns');
    }

    public static function maker(): Closure
    {
        return static fn (): static => new static();
    }

    public function selfMaker(): Closure
    {
        return fn (): self => $this;
    }

    public static function failer(): Closure
    {
        return static fn (): never => throw new LogicException('never returns');
    }
}
