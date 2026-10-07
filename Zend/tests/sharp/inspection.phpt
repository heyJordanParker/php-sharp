--TEST--
A backtrace taken inside a PHP# collection method's lambda shows the method's frame without its internal object
--FILE--
<?php
require __DIR__ . '/harness/Inspector.inc';
require __DIR__ . '/Inspection.sharp';

// Plain PHP keeps every frame it got, and uses any object in them after the call returned.
function show(array $frames): void
{
    foreach ($frames as $frame) {
        echo $frame['class'] ?? '', $frame['type'] ?? '', $frame['function'], ': ',
            isset($frame['object']) ? get_class($frame['object']) : 'no object', "\n";
        if (($frame['object'] ?? null) instanceof Sharp\Collection) {
            var_dump($frame['object']->entries());
        }
    }
}

echo implode(",", Demo\Inspection::look([1, 2])), "\n";
show(Lib\Inspector::$frames);

$fiber = new Fiber(fn () => Demo\Inspection::pause([3, 4]));
$fiber->start();
$frames = (new ReflectionFiber($fiber))->getTrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
$fiber->resume();
$fiber->resume();
echo implode(",", $fiber->getReturn()), "\n";
show($frames);
?>
--EXPECTF--
1,2
Lib\Inspector::look: no object
Demo\Inspection::{closure:Demo\Inspection::look():10}: no object
Sharp\Collection->filter: no object
Demo\Inspection::look: no object
3,4
Fiber::suspend: no object
Lib\Inspector::pause: no object
Demo\Inspection::{closure:Demo\Inspection::pause():16}: no object
Sharp\Collection->filter: no object
Demo\Inspection::pause: no object
{closure:%sinspection.php:%d}: no object
