--TEST--
A PHP# member reaches plain PHP as plain PHP's own does: a Map property overriding a plain parent's array property, typed or not, keeps the parent's storage, the PHP array the parent reads, a Map method overriding a plain parent's array method keeps the parent's PHP signature, so its class loads and gives the PHP array through the parent and the child, while PHP# reads the Map, and reflection gives a computed property one get hook and a property with a protected set none
--FILE--
<?php
require __DIR__ . '/harness/Caster.inc';
require __DIR__ . '/harness/Checker.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Departures');

$invoice = new Departures\Invoice();
echo json_encode($invoice->getCasts()), ' ', json_encode($invoice->getFormats()), "\n";
$invoice->cast('sent', 'datetime');
echo json_encode($invoice->getCasts()), ' ', json_encode($invoice->getFormats()), ' ', $invoice->castOf('sent'), "\n";

function errorsOf(Lib\Checker $checker): array
{
    return $checker->errors();
}
$form = new Departures\Form();
echo $form->report(), ' ', json_encode(errorsOf($form)), ' ', json_encode($form->merged(['b' => '2'])), "\n";
echo $form->count($form), ' ', (new ReflectionMethod(Departures\Form::class, 'errors'))->getReturnType(), "\n";

foreach (['result', 'count'] as $name) {
    $property = new ReflectionProperty(Departures\Counter::class, $name);
    echo $name, ': [', implode(', ', array_keys($property->getHooks())), "]\n";
}
?>
--EXPECT--
{"id":"int","paid":"bool"} {"id":"%d","total":"%.2f"}
{"id":"int","paid":"bool","sent":"datetime"} {"id":"%d","total":"%.3f"} datetime
{"name":["required"]} {"a":"1","b":"2"} {"name":["required"]} {"a":"1","b":"2"}
4 array
result: [get]
count: []
