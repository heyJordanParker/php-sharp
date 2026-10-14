--TEST--
A type text whose parse runs out of memory leaves the type table to the rest of the request, as a shutdown function finds it
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

register_shutdown_function(static function (): void {
    ini_set('memory_limit', '-1');
    echo implode(', ', arguments(unserialize(serialize(App\Queue::pair())))), "\n";
});

// A million type arguments fit in memory as text, but not once the parse lists them.
$text = implode(', ', array_fill(0, 1_000_000, 'int'));
$serialized = str_replace('s:14:"App.Order, int"', 's:' . strlen($text) . ':"' . $text . '"', serialize(App\Queue::pair()));
unset($text);
ini_set('memory_limit', (string) (memory_get_usage() + strlen($serialized) + 2 * 1024 * 1024));
unserialize($serialized);
?>
--EXPECTF--
Fatal error: Allowed memory size of %d bytes exhausted %s
App.Order, int
