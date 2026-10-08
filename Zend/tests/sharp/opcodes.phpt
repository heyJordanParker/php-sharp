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
    'Stage' => 'harness/HasLabel.inc',
    'Suit' => null,
    'Rank' => null,
    'Shipment' => null,
    'Task' => null,
    'Shapes' => null,
    'Members' => 'harness/Registry.inc',
    'MembersErrors' => 'harness/Registry.inc',
    'Site' => 'harness/SiteLib.inc',
    'Collections' => null,
    'EnumKeys' => null,
    'Interop' => null,
    'Lambdas' => null,
    'Cashier' => null,
    'Roster' => null,
    'Store' => 'harness/Model.inc',
    'Inbox' => null,
    'Patterns' => null,
    'Signatures' => null,
    'Accessors' => null,
    'Parcel' => 'harness/Row.inc',
];
$user_class = '/(?:Class|Enum) \[ <user> /';

foreach ($fixtures as $fixture => $library) {
    $prelude = $library === null ? '' : 'require ' . var_export(__DIR__ . "/$library", true) . ';';
    $sharp = compiled(__DIR__ . "/$fixture.sharp", $prelude);
    $php = compiled(__DIR__ . "/$fixture.inc", $prelude);
    // An op array either listing prints counts once: phpdbg prints abstract methods, and the dump prints hooks.
    preg_match_all('~^(\S.*):\n {5}; \(lines=~m', $sharp, $op_arrays);
    echo $fixture, ': ', $sharp === $php ? 'same' : 'different', ' opcodes and lines in ',
        count(array_unique($op_arrays[1])), ' op arrays, ',
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
Nulls: same opcodes and lines in 13 op arrays, same signatures in 1 classes
ControlFlow: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Order: same opcodes and lines in 4 op arrays, same signatures in 2 classes
Product: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Expressions: same opcodes and lines in 7 op arrays, same signatures in 1 classes
Checkout: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Page: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Stage: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Suit: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Rank: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Shipment: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Task: same opcodes and lines in 4 op arrays, same signatures in 1 classes
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
Collections: same opcodes and lines in 7 op arrays, same signatures in 2 classes
EnumKeys: same opcodes and lines in 12 op arrays, same signatures in 1 classes
Interop: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Lambdas: different opcodes and lines in 35 op arrays, same signatures in 1 classes
  .sharp      ; (lines=10, args=1, vars=1, tmps=3)
  .sharp L0099 0003 T2 = FETCH_CLASS_CONSTANT string("Demo\\Lambdas") string("twice")
  .sharp L0099 0004 SEND_VAL_EX T2 1
  .sharp L0099 0005 V3 = DO_FCALL
  .sharp L0099 0006 VERIFY_RETURN_TYPE V3
  .sharp L0099 0007 RETURN V3
  .sharp L0099 0008 VERIFY_RETURN_TYPE
  .sharp L0099 0009 RETURN null
  .sharp      ; (lines=10, args=1, vars=1, tmps=3)
  .sharp 0003 T2 = FETCH_CLASS_CONSTANT string("Demo\\Lambdas") string("twice")
  .sharp 0006 VERIFY_RETURN_TYPE V3
  .sharp 0007 RETURN V3
  .sharp 0008 VERIFY_RETURN_TYPE
  .sharp 0009 RETURN null
  .sharp      3: 0006 - 0007 (tmp/var)
  .php        ; (lines=11, args=1, vars=1, tmps=3)
  .php   L0099 0003 INIT_STATIC_METHOD_CALL 0 string("Demo\\Lambdas") string("twice")
  .php   L0099 0004 T2 = CALLABLE_CONVERT
  .php   L0099 0005 SEND_VAL_EX T2 1
  .php   L0099 0006 V3 = DO_FCALL
  .php   L0099 0007 VERIFY_RETURN_TYPE V3
  .php   L0099 0008 RETURN V3
  .php   L0099 0009 VERIFY_RETURN_TYPE
  .php   L0099 0010 RETURN null
  .php        ; (lines=11, args=1, vars=1, tmps=3)
  .php   0003 INIT_STATIC_METHOD_CALL 0 string("Demo\\Lambdas") string("twice")
  .php   0004 T2 = CALLABLE_CONVERT
  .php   0005 SEND_VAL_EX T2 1
  .php   0006 V3 = DO_FCALL
  .php   0007 VERIFY_RETURN_TYPE V3
  .php   0008 RETURN V3
  .php        3: 0007 - 0008 (tmp/var)
Cashier: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Roster: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Store: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  .sharp L0005 0000 DECLARE_CLASS string("store\\order")
  .sharp 0000 DECLARE_CLASS string("store\\order")
  .php   L0005 0000 DECLARE_CLASS string("store\\order") string("lib\\model")
  .php   0000 DECLARE_CLASS_DELAYED string("store\\order") string("lib\\model")
Inbox: same opcodes and lines in 9 op arrays, same signatures in 1 classes
Patterns: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Signatures: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Accessors: same opcodes and lines in 15 op arrays, same signatures in 2 classes
Parcel: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  .sharp L0005 0000 DECLARE_CLASS string("store\\parcel")
  .sharp 0000 DECLARE_CLASS string("store\\parcel")
  .php   L0005 0000 DECLARE_CLASS string("store\\parcel") string("lib\\row")
  .php   0000 DECLARE_CLASS_DELAYED string("store\\parcel") string("lib\\row")
