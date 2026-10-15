--TEST--
A PHP# final override is a final PHP method with #[\Override], so a plain PHP subclass that replaces it fails to link
--FILE--
<?php

require __DIR__ . '/harness/Traits.inc';
require __DIR__ . '/Inheritance.sharp';

$kind = new ReflectionMethod(Demo\Sealed::class, 'kind');
var_dump((new Demo\Sealed())->kind(), $kind->isFinal(), count($kind->getAttributes(Override::class)));

// The refusal is fatal, so it runs in its own process.
$code = 'require ' . var_export(__DIR__ . '/harness/Traits.inc', true) . ';'
    . ' require ' . var_export(__DIR__ . '/Inheritance.sharp', true) . ';'
    . ' class Plain extends Demo\Sealed { public function kind(): string { return "plain"; } }';
echo strtok(trim(shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg($code) . ' 2>&1')), "\n"), "\n";
?>
--EXPECTF--
string(6) "sealed"
bool(true)
int(1)
%AFatal error: Cannot override final method Demo\Sealed::kind() in %s on line %d
