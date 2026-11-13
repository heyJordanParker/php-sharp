--TEST--
Sharp\Container knows a PHP# class by its exact name, as the class table does, spec section 24: `App\Shop\Stamp` is bound and `app\shop\stamp` names nothing, and a test file's module applies to its class by its exact name. A plain PHP class is known by its name in any case
--FILE--
<?php
require __DIR__ . '/Container.inc';

class Ledger
{
}

$root = container_project('container-exact-class-names');
// A disk that ignores case, as macOS's does, finds a file by any case of its name, so the project loads a class only
// from the file named as the class is.
foreach (spl_autoload_functions() as $loader) {
    spl_autoload_unregister($loader);
}
spl_autoload_register(function (string $class) use ($root): void {
    $library = "$root/vendor/heyjordanparker/php-sharp-composer/library";
    $file = str_replace('\\', '/', $class) . '.sharp';
    $path = str_starts_with($class, 'Sharp\\') ? "$library/$file" : "$root/" . lcfirst($file);
    if (is_file($path) && in_array(basename($path), scandir(dirname($path)), true)) {
        require $path;
    }
});
$container = new Sharp\Container();

echo "the PHP# class: ", get_class($container->get('App\Shop\Stamp')), "\n";
try {
    $container->get('app\shop\stamp');
} catch (Sharp\ContainerError $e) {
    echo "another case: ", $e->getMessage(), "\n";
}
var_dump($container->has('App\Shop\Stamp'), $container->has('app\shop\stamp'));

// A test's module, as the compiler writes one, binds the plain class by its lowercase name.
Sharp\Internal\testModule('ExactTest', [['', 'ledger', null, 'transient', 'Ledger']]);
Sharp\Internal\startTest('ExactTest');
echo "the plain class, in any case: ", get_class($container->get('Ledger')), " ", get_class($container->get('LEDGER')), "\n";
Sharp\Internal\endTest();
Sharp\Internal\startTest('exacttest');
var_dump($container->has('Ledger'));
Sharp\Internal\endTest();
?>
--EXPECT--
the PHP# class: App\Shop\Stamp
another case: No module binds app\shop\stamp
bool(true)
bool(false)
the plain class, in any case: Ledger Ledger
bool(false)
