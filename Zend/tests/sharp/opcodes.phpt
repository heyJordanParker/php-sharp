--TEST--
PHP# compiles each .sharp fixture to the opcodes, lines and signatures of its PHP twin
--FILE--
<?php

function compiled(string $file, string $prelude): string
{
    $opcodes = shell_exec(escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1');
    // phpdbg prints no property hook. Opcache's dump before optimization prints every op array with code, without
    // op lines. -l compiles the file without running it, so its classes need no library to link against.
    $dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
        . ' -n -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x10000 -l ' . escapeshellarg($file) . ' 2>&1');
    $classes = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        $prelude . '$declared = get_declared_classes(); require ' . var_export($file, true) . ';'
        . ' foreach (array_diff(get_declared_classes(), $declared) as $class) echo new ReflectionClass($class);'
    ) . ' 2>&1');

    return str_replace($file, '<file>', $opcodes . $dump . $classes);
}

// Each fixture names the PHP library its classes link against.
$fixtures = [
    'Calc' => null,
    'Nulls' => null,
    'ControlFlow' => null,
    'Order' => null,
    'Product' => null,
    'Expressions' => null,
    'Checkout' => null,
    'Page' => null,
    'Shapes' => null,
    'Members' => 'harness/Registry.inc',
    'MembersErrors' => 'harness/Registry.inc',
    'Site' => 'harness/SiteLib.inc',
];

foreach ($fixtures as $fixture => $library) {
    $prelude = $library === null ? '' : 'require ' . var_export(__DIR__ . "/$library", true) . ';';
    $sharp = compiled(__DIR__ . "/$fixture.sharp", $prelude);
    $php = compiled(__DIR__ . "/$fixture.inc", $prelude);
    // An op array either listing prints counts once: phpdbg prints abstract methods, and the dump prints hooks.
    preg_match_all('~^(\S.*):\n {5}; \(lines=~m', $sharp, $op_arrays);
    echo $fixture, ': ', $sharp === $php ? 'same' : 'different', ' opcodes and lines in ',
        count(array_unique($op_arrays[1])), ' op arrays, ',
        substr_count($sharp, 'Class [ <user> ') === substr_count($php, 'Class [ <user> ') ? 'same' : 'different',
        ' signatures in ', substr_count($sharp, 'Class [ <user> '), " classes\n";

    // A difference PHP# makes on purpose is pinned line by line below, so any other one fails.
    $sharp_lines = explode("\n", $sharp);
    $php_lines = explode("\n", $php);
    foreach (array_diff($sharp_lines, $php_lines) as $line) {
        echo '  .sharp ', $line, "\n";
    }
    foreach (array_diff($php_lines, $sharp_lines) as $line) {
        echo '  .php   ', $line, "\n";
    }
}
?>
--EXPECT--
Calc: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Nulls: same opcodes and lines in 10 op arrays, same signatures in 1 classes
ControlFlow: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Order: same opcodes and lines in 4 op arrays, same signatures in 2 classes
Product: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Expressions: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Checkout: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Page: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Shapes: same opcodes and lines in 5 op arrays, same signatures in 2 classes
Members: different opcodes and lines in 10 op arrays, same signatures in 1 classes
  .sharp L0042 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("label")
  .sharp L0053 0002 T1 = FETCH_CLASS_CONSTANT string("Demo\\Members") string("count")
  .sharp L0058 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("late")
  .sharp L0064 0001 T3 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("items")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("label")
  .sharp 0002 T1 = FETCH_CLASS_CONSTANT string("Demo\\Members") string("count")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("late")
  .sharp 0001 T3 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("items")
  .php   L0042 0000 T0 = FETCH_STATIC_PROP_R string("label") string("Lib\\Registry")
  .php   L0053 0002 T1 = FETCH_STATIC_PROP_R string("count") string("Demo\\Members")
  .php   L0058 0000 T0 = FETCH_STATIC_PROP_R string("late") string("Lib\\Registry")
  .php   L0064 0001 T3 = FETCH_STATIC_PROP_R string("items") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("label") string("Lib\\Registry")
  .php   0002 T1 = FETCH_STATIC_PROP_R string("count") string("Demo\\Members")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("late") string("Lib\\Registry")
  .php   0001 T3 = FETCH_STATIC_PROP_R string("items") string("Lib\\Registry")
MembersErrors: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  .sharp L0009 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("missing")
  .sharp L0014 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("hidden")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("missing")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("hidden")
  .php   L0009 0000 T0 = FETCH_STATIC_PROP_R string("missing") string("Lib\\Registry")
  .php   L0014 0000 T0 = FETCH_STATIC_PROP_R string("hidden") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("missing") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("hidden") string("Lib\\Registry")
Site: different opcodes and lines in 13 op arrays, same signatures in 3 classes
  .sharp L0010 0000 DECLARE_CLASS string("site\\page")
  .sharp L0068 0001 DECLARE_CLASS string("site\\square")
  .sharp L0080 0002 RETURN int(1)
  .sharp L0039 0001 T1 = FETCH_CLASS_CONSTANT string("Site\\Page") string("views")
  .sharp 0000 DECLARE_CLASS string("site\\page")
  .sharp 0001 DECLARE_CLASS string("site\\square")
  .sharp 0002 RETURN int(1)
  .sharp 0001 T1 = FETCH_CLASS_CONSTANT string("Site\\Page") string("views")
  .php   L0010 0000 DECLARE_CLASS string("site\\page") string("lib\\resource")
  .php   L0080 0001 RETURN int(1)
  .php   L0039 0001 T1 = FETCH_STATIC_PROP_R string("views") string("Site\\Page")
  .php   0000 DECLARE_CLASS string("site\\page") string("lib\\resource")
  .php   0001 RETURN int(1)
  .php   0001 T1 = FETCH_STATIC_PROP_R string("views") string("Site\\Page")
