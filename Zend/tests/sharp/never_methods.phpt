--TEST--
PHP# a never method throws to its caller, and is PHP's never to plain PHP, and never is a whole type: a type argument of a result that cannot fail, and a parameter's type, which is PHP's null
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

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
$one = Nevers\Errors::one();
echo $one::class, '<', implode(', ', (new ReflectionObject($one))->getTypeArguments()), ">\n";
echo (new ReflectionMethod(Nevers\Errors::class, 'ignored'))->getParameters()[0]->getType(), "\n";
?>
--EXPECT--
ada
1
name is required
bad count
never
Sharp\Result\Ok<int, never>
null
