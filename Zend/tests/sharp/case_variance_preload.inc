<?php

class Baz
{
}

class ParentWithBaz
{
    public function take(Baz $value): Baz
    {
        return $value;
    }
}

final class ChildWithBaz extends ParentWithBaz
{
    public function take(baz $value): BAZ
    {
        return $value;
    }
}
