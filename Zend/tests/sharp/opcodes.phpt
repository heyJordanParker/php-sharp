--TEST--
PHP# compiles each .sharp fixture to the opcodes, lines and signatures of its PHP twin
--FILE--
<?php

function compiled(string $file, string $prelude): string
{
    $opcodes = shell_exec(escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1');
    $classes = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        $prelude . '$declared = get_declared_classes(); require ' . var_export($file, true) . ';'
        . ' foreach (array_diff(get_declared_classes(), $declared) as $class) echo new ReflectionClass($class);'
    ) . ' 2>&1');

    return str_replace($file, '<file>', $opcodes . $classes);
}

// Each fixture names the PHP library its classes link against.
$fixtures = [
    'Calc' => null,
    'Nulls' => null,
    'ControlFlow' => null,
    'Order' => null,
    'Product' => null,
    'Expressions' => null,
    'Status' => null,
    'Suit' => null,
    'Rank' => null,
    'Shipment' => null,
    'Task' => null,
    'Shapes' => null,
    'Members' => 'Registry.inc',
    'Site' => 'SiteLib.inc',
];
$user_class = '/(?:Class|Enum) \[ <user> /';

foreach ($fixtures as $fixture => $library) {
    $prelude = $library === null ? '' : 'require ' . var_export(__DIR__ . "/$library", true) . ';';
    $sharp = compiled(__DIR__ . "/$fixture.sharp", $prelude);
    $php = compiled(__DIR__ . "/$fixture.inc", $prelude);
    echo $fixture, ': ', $sharp === $php ? 'same' : 'different', ' opcodes and lines in ',
        substr_count($sharp, '; (lines='), ' op arrays, ',
        preg_match_all($user_class, $sharp) === preg_match_all($user_class, $php) ? 'same' : 'different',
        ' signatures in ', preg_match_all($user_class, $sharp), " classes\n";

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
Status: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Suit: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Rank: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Shipment: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Task: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Shapes: same opcodes and lines in 5 op arrays, same signatures in 2 classes
Members: different opcodes and lines in 12 op arrays, same signatures in 1 classes
  .sharp L0042 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("label")
  .sharp L0053 0002 T1 = FETCH_CLASS_CONSTANT string("Demo\\Members") string("count")
  .sharp L0058 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("missing")
  .sharp L0063 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("hidden")
  .sharp L0068 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("late")
  .sharp L0074 0001 T3 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("items")
  .php   L0042 0000 T0 = FETCH_STATIC_PROP_R string("label") string("Lib\\Registry")
  .php   L0053 0002 T1 = FETCH_STATIC_PROP_R string("count") string("Demo\\Members")
  .php   L0058 0000 T0 = FETCH_STATIC_PROP_R string("missing") string("Lib\\Registry")
  .php   L0063 0000 T0 = FETCH_STATIC_PROP_R string("hidden") string("Lib\\Registry")
  .php   L0068 0000 T0 = FETCH_STATIC_PROP_R string("late") string("Lib\\Registry")
  .php   L0074 0001 T3 = FETCH_STATIC_PROP_R string("items") string("Lib\\Registry")
Site: different opcodes and lines in 13 op arrays, same signatures in 3 classes
  .sharp L0010 0000 DECLARE_CLASS string("site\\page")
  .sharp L0068 0001 DECLARE_CLASS string("site\\square")
  .sharp L0080 0002 RETURN int(1)
  .sharp L0039 0001 T1 = FETCH_CLASS_CONSTANT string("Site\\Page") string("views")
  .php   L0010 0000 DECLARE_CLASS string("site\\page") string("lib\\entity")
  .php   L0080 0001 RETURN int(1)
  .php   L0039 0001 T1 = FETCH_STATIC_PROP_R string("views") string("Site\\Page")
