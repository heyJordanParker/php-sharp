--TEST--
A PHP# member reaches plain PHP as plain PHP's own does: a Map property overriding a plain parent's array property, typed or not, keeps the parent's storage, the PHP array the parent reads, and reflection gives a computed property one get hook and a property with a protected set none
--FILE--
<?php
require __DIR__ . '/harness/Caster.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Departures');

$invoice = new Departures\Invoice();
echo json_encode($invoice->getCasts()), ' ', json_encode($invoice->getFormats()), "\n";
$invoice->cast('sent', 'datetime');
echo json_encode($invoice->getCasts()), ' ', json_encode($invoice->getFormats()), ' ', $invoice->castOf('sent'), "\n";

foreach (['result', 'count'] as $name) {
    $property = new ReflectionProperty(Departures\Counter::class, $name);
    echo $name, ': [', implode(', ', array_keys($property->getHooks())), "]\n";
}
?>
--EXPECT--
{"id":"int","paid":"bool"} {"id":"%d","total":"%.2f"}
{"id":"int","paid":"bool","sent":"datetime"} {"id":"%d","total":"%.3f"} datetime
result: [get]
count: []
