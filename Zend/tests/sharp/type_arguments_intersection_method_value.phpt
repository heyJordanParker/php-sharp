--TEST--
A method value read on a value of a type parameter bounded by an intersection takes the method one class of the intersection declares
--FILE--
<?php
require __DIR__ . '/type_arguments_calls.inc';

use App\SharedOrder;
use Calls\Feed;

echo Feed::shared(), "\n";
echo (new Feed())->sharer(new SharedOrder(6))(), "\n";
?>
--EXPECT--
shared 5
shared 6
