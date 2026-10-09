--TEST--
ReflectionObject::getTypeArguments returns nothing beside the exception a lazy proxy's initializer throws, as an observer sees it
--EXTENSIONS--
zend_test
--INI--
zend_test.observer.enabled=1
zend_test.observer.show_output=1
zend_test.observer.observe_function_names=getTypeArguments
zend_test.observer.show_return_value=1
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

$failing = (new ReflectionClass(App\PaginatedList::class))->newLazyProxy(static function (): never {
    throw new RuntimeException('no orders');
});
// invoke() calls it through zend_call_function, which shows an observer the return value an exception left.
try {
    (new ReflectionMethod(ReflectionObject::class, 'getTypeArguments'))->invoke(new ReflectionObject($failing));
} catch (RuntimeException $exception) {
    echo $exception->getMessage(), "\n";
}
?>
--EXPECTF--
%A<ReflectionObject::getTypeArguments>
%A  <!-- Exception: RuntimeException -->
</ReflectionObject::getTypeArguments:NULL>
%Ano orders
