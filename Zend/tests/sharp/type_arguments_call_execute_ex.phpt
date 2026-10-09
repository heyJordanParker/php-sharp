--TEST--
Under an extension that replaces zend_execute_ex, as a debugger or a profiler does, a generic call from PHP# gives the method its type arguments and is not checked, and a call from plain PHP is
--SKIPIF--
<?php if (!extension_loaded('zend_test')) die('skip zend_test is missing'); ?>
--INI--
zend_test.replace_zend_execute_ex=1
opcache.jit=disable
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\Order;
use Calls\Repository;
use Calls\Shelf;

function attempt(callable $call): void
{
    try {
        echo $call(), "\n";
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}

attempt(fn () => (new Shelf())->drained());
attempt(fn () => shown(Repository::written(new Order(1))));
attempt(fn () => (new Shelf())->drain(Shelf::orders()));
attempt(fn () => call_user_func([new Shelf(), 'drain'], Shelf::orders()));
attempt(fn () => array_map([new Shelf(), 'drain'], [Shelf::orders()])[0]);
?>
--EXPECTF--
drained
App\Box<App.Order>
Calls\Shelf::drain(): Argument #1 ($source) must be of type Calls.Source<App.DatabaseEntity>, Calls.Source<App.Order> given, called in %s on line %d
Calls\Shelf::drain(): Argument #1 ($source) must be of type Calls.Source<App.DatabaseEntity>, Calls.Source<App.Order> given, called in %s on line %d
Calls\Shelf::drain(): Argument #1 ($source) must be of type Calls.Source<App.DatabaseEntity>, Calls.Source<App.Order> given
