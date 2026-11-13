<?php

final class ChildWithFoo extends ParentWithFoo
{
    public function take(foo $value): FOO
    {
        return $value;
    }
}
