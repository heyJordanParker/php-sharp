--TEST--
A `using` local's value is disposed when its block ends, by a return, by falling off the end or by a throw, and several are disposed newest first
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\Disposals;

echo 'returned: ', Disposals::returned(), "\n";
echo 'ended: ', Disposals::ended(), "\n";
echo 'thrown: ', Disposals::thrown(), "\n";
?>
--EXPECT--
returned: open first, open second, body, dispose second, dispose first, returned
ended: open outer, open inner, inner block, dispose inner, outer block, dispose outer
thrown: open first, open second, dispose second, dispose first, caught failed
