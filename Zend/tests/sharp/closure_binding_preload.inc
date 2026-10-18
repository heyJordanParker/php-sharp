<?php

trait Tagged
{
    public function traitClosure(): Closure
    {
        return function () {
            return [static::class, self::class, get_class($this), $this->tag()];
        };
    }
}

class A
{
    use Tagged;

    const NAME = 'A';
    public int $pad = 0;
    private int $n = 5;

    private function tag(): string
    {
        return 'A-tag';
    }

    public function make(): Closure
    {
        return function () {
            return [self::NAME, static::class, $this->n + 1, $this->tag()];
        };
    }

    public function makeStatic(): Closure
    {
        return static function () {
            return [self::NAME, static::class];
        };
    }

    public function makeArrow(): Closure
    {
        return fn() => $this->n * 2;
    }

    public function method(int $k): string
    {
        return self::NAME . $k . $this->n;
    }

    public function firstClass(): Closure
    {
        return $this->method(...);
    }

    public static function staticMethod(): string
    {
        return static::class;
    }
}

class A2 extends A
{
    const NAME = 'A2';
}

class B
{
    use Tagged;

    const NAME = 'B';
    public string $s1 = 'x';
    public string $s2 = 'y';
    private float $n = 1.5;

    private function tag(): string
    {
        return 'B-tag';
    }
}

function binding_report(): string
{
    $out = [];
    $a = new A();
    $b = new B();

    $c = $a->make();
    $out['closure'] = $c();
    $out['bind'] = Closure::bind($c, $b, B::class)();
    $out['bindTo'] = $c->bindTo($b, B::class)();
    $out['call'] = $c->call($b);
    $out['bindTo subclass'] = $c->bindTo(new A2())();

    $s = $a->makeStatic();
    $out['static'] = $s();
    $out['static bind scope'] = Closure::bind($s, null, B::class)();

    $arrow = $a->makeArrow();
    $out['arrow'] = $arrow();
    $out['arrow bind'] = Closure::bind($arrow, $b, B::class)();

    $f = $a->firstClass();
    $out['first-class'] = $f(3);
    $out['fromCallable'] = Closure::fromCallable([$a, 'method'])(4);
    $out['fromCallable static'] = Closure::fromCallable('A::staticMethod')();
    $out['first-class static'] = (A2::staticMethod(...))();

    $out['trait A'] = $a->traitClosure()();
    $out['trait B'] = $b->traitClosure()();
    $out['trait bind'] = Closure::bind($a->traitClosure(), $b, B::class)();

    set_error_handler(function (int $level, string $message) use (&$out) {
        $out['warning'][] = $message;
        return true;
    });
    $out['first-class bindTo'] = $f->bindTo($b);
    $out['static bindTo this'] = $s->bindTo($b);
    restore_error_handler();

    return json_encode($out);
}

binding_report();
