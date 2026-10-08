--TEST--
The type arguments serialize writes take no reference number, so r: and R: after a PHP# object still name the right value
--FILE--
<?php
require __DIR__ . '/harness/Witness.inc';
require __DIR__ . '/harness/Snapshot.inc';
require __DIR__ . '/harness/Sleeper.inc';
require __DIR__ . '/TypeArguments.sharp';

$order = new App\Order(7);
$tag = 'kept';
foreach (['default form' => App\Queue::orders(), '__serialize form' => App\Queue::ledger(), '__sleep form' => App\Queue::journal()] as $form => $object) {
    $copy = unserialize(serialize([$object, $object, $order, $order, &$tag, &$tag]));
    echo $form, ': ',
        var_export($copy[0] === $copy[1], true), ' ',
        var_export($copy[2] === $copy[3], true), ' ',
        $copy[2]->id, ' ',
        implode(', ', (new ReflectionObject($copy[0]))->getTypeArguments()), "\n";
    $copy[4] = 'changed';
    echo $copy[5], "\n";
}
?>
--EXPECT--
default form: true true 7 App.Order
changed
__serialize form: true true 7 App.Order
changed
__sleep form: true true 7 App.Order
changed
