--TEST--
PHP# writes output with printf and fwrite, reads Environment and Position, names a class with typeof and wraps a value with List.wrap
--ENV--
SHARP_REGION=eu-west-1
--ARGS--
production --force
--FILE--
<?php

require __DIR__ . '/PhpForms.sharp';

$forms = new Demo\PhpForms(new Sharp\Environment());
$forms->report(3);
$code = 'require ' . var_export(__DIR__ . '/PhpForms.sharp', true) . '; (new Demo\PhpForms(new Sharp\Environment()))->warn("Prune failed");';
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS') . ' -r ' . escapeshellarg($code) . ' 2>&1 1>/dev/null');

echo $forms->setting('SHARP_REGION'), ' ', $forms->setting('SHARP_UNSET_REGION'), "\n";
echo $forms->target(), "\n";
var_dump($forms->folder() === getcwd());

var_dump($forms->stubs() === __DIR__ . '/stubs');
[$file, $line, $column, $function] = $forms->here();
var_dump($file === realpath(__DIR__ . '/PhpForms.sharp'));
echo $line, ':', $column, ' ', $function, "\n";
$origin = $forms->origin;
echo $origin->line, ':', $origin->column, ' ', $origin->function, "\n";
$located = $forms->located();
var_dump($located->file === $file, $located->directory === __DIR__);
echo $located->line, ':', $located->column, ' ', $located->function, "\n";

echo $forms->kind(), "\n";
var_dump($forms->tags('sale'));
$tags = ['sale', 'new'];
var_dump($forms->tags($tags) === $tags);
?>
--EXPECT--
Pruned 3 carts
Prune failed
eu-west-1 unset
production
bool(true)
bool(true)
bool(true)
35:22 Demo.PhpForms.here
7:31 Demo.PhpForms.origin
bool(true)
bool(true)
41:30 Demo.PhpForms.located
Demo\PhpForms
array(1) {
  [0]=>
  string(4) "sale"
}
bool(true)
