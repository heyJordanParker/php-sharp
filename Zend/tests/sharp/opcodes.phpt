--TEST--
PHP# compiles each .sharp fixture to the opcodes, lines and signatures of its PHP twin
--FILE--
<?php

function compiled(string $file, string $prelude): array
{
    $php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n ';
    $require = 'require ' . var_export($file, true) . ';';

    return str_replace($file, '<file>', [
        'phpdbg' => shell_exec(escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1'),
        'opcache' => shell_exec($php . '-d opcache.enable_cli=1 -d opcache.opt_debug_level=0x10000 -r ' . escapeshellarg($prelude . $require) . ' 2>&1'),
        'reflection' => shell_exec($php . '-r ' . escapeshellarg(
            $prelude . '$declared = get_declared_classes(); ' . $require
            . ' foreach (array_diff(get_declared_classes(), $declared) as $class) echo new ReflectionClass($class);'
        ) . ' 2>&1'),
    ]);
}

// Each fixture names the PHP library its classes link against.
$fixtures = [
    'Calc' => null,
    'Nulls' => null,
    'ControlFlow' => null,
    'Order' => null,
    'Product' => null,
    'Expressions' => null,
    'Shapes' => null,
    'Members' => 'Registry.inc',
    'Site' => 'SiteLib.inc',
    'Collections' => null,
    'Interop' => null,
    'Lambdas' => null,
    'Checkout' => null,
    'Exits' => null,
    'Positions' => null,
    'Deploy' => null,
    'Tags' => null,
    'PhpForms' => null,
];

foreach ($fixtures as $fixture => $library) {
    $prelude = $library === null ? '' : 'require ' . var_export(__DIR__ . "/$library", true) . ';';
    $sharp = compiled(__DIR__ . "/$fixture.sharp", $prelude);
    $php = compiled(__DIR__ . "/$fixture.inc", $prelude);
    echo $fixture, ': ', $sharp === $php ? 'same' : 'different', ' opcodes and lines in ',
        substr_count($sharp['phpdbg'], '; (lines='), ' op arrays, opcodes in ',
        preg_match_all('/^\S+::\$\w+::(?:get|set):$/m', $sharp['opcache']), ' property hooks, ',
        substr_count($sharp['reflection'], 'Class [ <user> ') === substr_count($php['reflection'], 'Class [ <user> ') ? 'same' : 'different',
        ' signatures in ', substr_count($sharp['reflection'], 'Class [ <user> '), " classes\n";

    // A difference PHP# makes on purpose is pinned line by line below, so any other one fails.
    $sharp_lines = explode("\n", implode("\n", $sharp));
    $php_lines = explode("\n", implode("\n", $php));
    foreach (array_diff($sharp_lines, $php_lines) as $line) {
        echo '  .sharp ', $line, "\n";
    }
    foreach (array_diff($php_lines, $sharp_lines) as $line) {
        echo '  .php   ', $line, "\n";
    }
}
?>
--EXPECT--
Calc: same opcodes and lines in 4 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Nulls: same opcodes and lines in 10 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
ControlFlow: same opcodes and lines in 6 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Order: same opcodes and lines in 4 op arrays, opcodes in 0 property hooks, same signatures in 2 classes
Product: same opcodes and lines in 3 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Expressions: same opcodes and lines in 6 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Shapes: same opcodes and lines in 5 op arrays, opcodes in 0 property hooks, same signatures in 2 classes
Members: different opcodes and lines in 12 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
  .sharp L0042 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("label")
  .sharp L0053 0002 T1 = FETCH_CLASS_CONSTANT string("Demo\\Members") string("count")
  .sharp L0058 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("missing")
  .sharp L0063 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("hidden")
  .sharp L0068 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("late")
  .sharp L0074 0001 T3 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("items")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("label")
  .sharp 0002 T1 = FETCH_CLASS_CONSTANT string("Demo\\Members") string("count")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("missing")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("hidden")
  .sharp 0000 T0 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("late")
  .sharp 0001 T3 = FETCH_CLASS_CONSTANT string("Lib\\Registry") string("items")
  .php   L0042 0000 T0 = FETCH_STATIC_PROP_R string("label") string("Lib\\Registry")
  .php   L0053 0002 T1 = FETCH_STATIC_PROP_R string("count") string("Demo\\Members")
  .php   L0058 0000 T0 = FETCH_STATIC_PROP_R string("missing") string("Lib\\Registry")
  .php   L0063 0000 T0 = FETCH_STATIC_PROP_R string("hidden") string("Lib\\Registry")
  .php   L0068 0000 T0 = FETCH_STATIC_PROP_R string("late") string("Lib\\Registry")
  .php   L0074 0001 T3 = FETCH_STATIC_PROP_R string("items") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("label") string("Lib\\Registry")
  .php   0002 T1 = FETCH_STATIC_PROP_R string("count") string("Demo\\Members")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("missing") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("hidden") string("Lib\\Registry")
  .php   0000 T0 = FETCH_STATIC_PROP_R string("late") string("Lib\\Registry")
  .php   0001 T3 = FETCH_STATIC_PROP_R string("items") string("Lib\\Registry")
Site: different opcodes and lines in 13 op arrays, opcodes in 0 property hooks, same signatures in 3 classes
  .sharp L0010 0000 DECLARE_CLASS string("site\\page")
  .sharp L0068 0001 DECLARE_CLASS string("site\\square")
  .sharp L0080 0002 RETURN int(1)
  .sharp L0039 0001 T1 = FETCH_CLASS_CONSTANT string("Site\\Page") string("views")
  .sharp 0000 DECLARE_CLASS string("site\\page")
  .sharp 0001 DECLARE_CLASS string("site\\square")
  .sharp 0002 RETURN int(1)
  .sharp 0001 T1 = FETCH_CLASS_CONSTANT string("Site\\Page") string("views")
  .php   L0010 0000 DECLARE_CLASS string("site\\page") string("lib\\entity")
  .php   L0080 0001 RETURN int(1)
  .php   L0039 0001 T1 = FETCH_STATIC_PROP_R string("views") string("Site\\Page")
  .php   0000 DECLARE_CLASS string("site\\page") string("lib\\entity")
  .php   0001 T1 = FETCH_STATIC_PROP_R string("views") string("Site\\Page")
Collections: same opcodes and lines in 6 op arrays, opcodes in 0 property hooks, same signatures in 2 classes
Interop: same opcodes and lines in 3 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Lambdas: different opcodes and lines in 36 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
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
Checkout: same opcodes and lines in 10 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Exits: same opcodes and lines in 2 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Positions: same opcodes and lines in 2 op arrays, opcodes in 3 property hooks, same signatures in 1 classes
Deploy: same opcodes and lines in 7 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
Tags: same opcodes and lines in 2 op arrays, opcodes in 0 property hooks, same signatures in 1 classes
PhpForms: same opcodes and lines in 14 op arrays, opcodes in 1 property hooks, same signatures in 1 classes