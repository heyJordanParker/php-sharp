--TEST--
PHP# a never method throws to its caller, and is PHP's never to plain PHP
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Nevers');

echo Nevers\Errors::named('ada'), "\n";
echo Nevers\Errors::counted(false), "\n";
foreach ([fn () => Nevers\Errors::named(null), fn () => Nevers\Errors::counted(true)] as $call) {
    try {
        $call();
    } catch (Nevers\BadInput $e) {
        echo $e->getMessage(), "\n";
    }
}
echo (new ReflectionMethod(Nevers\Errors::class, 'fail'))->getReturnType(), "\n";
?>
--EXPECT--
ada
1
name is required
bad count
never
