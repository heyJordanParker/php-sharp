--TEST--
JIT: a tracing JIT trace through a get hook that passes a hooked property to a method the compiler can't see, whose FETCH_OBJ_FUNC_ARG must not inline the hook while the trace records
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=tracing
opcache.jit_buffer_size=32M
opcache.jit_hot_func=1
opcache.jit_hot_loop=1
opcache.jit_hot_return=1
opcache.jit_hot_side_exit=1
--FILE--
<?php
// Numbers is another file's class, so neither the compiler nor the optimizer knows how mod takes its arguments.
require __DIR__ . '/fetch_obj_func_arg_hooked_prop.inc';

final class Moment
{
    public int $seconds { get => 1792693800; }

    public int $hour { get => intdiv(Numbers::mod($this->seconds, 86400), 3600); }
}

for ($i = 0; $i < 8; $i++) {
    echo (new Moment())->hour, "\n";
}
?>
--EXPECT--
18
18
18
18
18
18
18
18
