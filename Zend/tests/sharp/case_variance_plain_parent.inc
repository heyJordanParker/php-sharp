<?php

class Foo
{
}

class ParentWithFoo
{
    public function take(Foo $value): Foo
    {
        return $value;
    }
}
