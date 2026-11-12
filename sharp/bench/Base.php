<?php

namespace Bench;

/**
 * The plain PHP parent of `Loops`, whose rows reach a PHP# method and a PHP# class each way plain PHP code does: a
 * compiled call, a call by a name the code holds, `call_user_func`, `method_exists`, a static call, the parent's own
 * code calling the method a PHP# child gives it, and `class_exists` of a name the code builds, which no cache holds.
 */
abstract class Base
{
    abstract public function oneMore(): int;

    public function callsOneMore(int $n): int
    {
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += $this->oneMore();
        }

        return $total;
    }

    public static function plainCamelCall(int $n): int
    {
        $loops = new Loops();
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += $loops->oneMore();
        }

        return $total;
    }

    public static function plainDynamicCall(int $n): int
    {
        $loops = new Loops();
        $method = 'oneMore';
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += $loops->$method();
        }

        return $total;
    }

    public static function plainCallUserFunc(int $n): int
    {
        $loops = new Loops();
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += call_user_func([$loops, 'oneMore']);
        }

        return $total;
    }

    public static function plainMethodExists(int $n): int
    {
        $loops = new Loops();
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += (int) method_exists($loops, 'oneMore');
        }

        return $total;
    }

    public static function plainStaticCall(int $n): int
    {
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += Loops::oneStatic();
        }

        return $total;
    }

    public static function parentBodyCall(int $n): int
    {
        return (new Loops())->callsOneMore($n);
    }

    public static function sharpClassExists(int $n): int
    {
        return self::classExists('Bench\Loops', $n);
    }

    public static function plainClassExists(int $n): int
    {
        return self::classExists('Bench\Base', $n);
    }

    // The name is built from `$n`, which only the run gives, so no string the compiler interns holds a cached class.
    private static function classExists(string $name, int $n): int
    {
        $class = substr($name . $n, 0, strlen($name));
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $total += (int) class_exists($class);
        }

        return $total;
    }
}
