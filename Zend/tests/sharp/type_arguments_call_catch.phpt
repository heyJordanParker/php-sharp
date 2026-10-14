--TEST--
A catch clause of a type with the method's type arguments tests the caught value in order, and one that none matches is thrown on
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use Calls\Repository;

// A hot function's second call runs the code the tracing JIT compiled on its first.
foreach ([1, 2] as $round) {
    echo Repository::caughtUnexpected(Repository::numberFailure()), "\n";
    echo Repository::caughtUnexpected(Repository::textFailure()), "\n";
    echo Repository::caughtUnexpected(new UnexpectedValueException('four')), "\n";
    try {
        Repository::caughtUnexpected(Repository::flagFailure());
    } catch (RuntimeException $rethrown) {
        echo 'rethrown: ', $rethrown->getMessage(), "\n";
    }
}
?>
--EXPECT--
numbers: one
error: two
error: four
rethrown: three
numbers: one
error: two
error: four
rethrown: three
