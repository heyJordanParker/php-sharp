<?php declare(strict_types=1);

namespace Lib\Lineage;

/** A plain PHP base class whose `run` its subclasses inherit. */
class Base
{
    public function run(): int
    {
        return 1;
    }
}

/** A plain PHP subclass that declares `send` and `later` and inherits `run`. */
class Sub extends Base
{
    public function send(): int
    {
        return 2;
    }

    public function later(): int
    {
        return 3;
    }
}

/** A plain PHP trait whose `deliver` each class that uses it takes as its own. */
trait Delivers
{
    public function deliver(): int
    {
        return 5;
    }
}

/** A plain PHP class that takes `deliver` from a trait. */
class Courier
{
    use Delivers;
}

/** A plain PHP subclass that no `extern` line declares. */
class Leaf extends Base
{
    public function own(): int
    {
        return 4;
    }
}
