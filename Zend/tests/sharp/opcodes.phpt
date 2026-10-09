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

// Each fixture names the libraries its classes link against.
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
    'Site' => 'harness/SiteLib.inc',
    'Collections' => null,
    'EnumKeys' => null,
    'Interop' => null,
    'Lambdas' => null,
    'Cashier' => null,
    'Roster' => null,
    'Sets' => 'harness/Standing.inc',
    'Store' => 'harness/Model.inc',
    'RushOrders' => 'harness/Model.inc',
    'TypedOrders' => 'harness/TypedModel.inc',
    'Permalink' => '../../../sharp/composer/library/Sharp/Text/Text.sharp',
    'Library' => [
        '../../../sharp/composer/library/Sharp/Data/Base64.sharp',
        '../../../sharp/composer/library/Sharp/Data/Binary.sharp',
        '../../../sharp/composer/library/Sharp/Data/Compression.sharp',
        '../../../sharp/composer/library/Sharp/Data/Hash.sharp',
        '../../../sharp/composer/library/Sharp/Data/Hex.sharp',
        '../../../sharp/composer/library/Sharp/Data/Password.sharp',
        '../../../sharp/composer/library/Sharp/Data/PhpSerializer.sharp',
        '../../../sharp/composer/library/Sharp/IO/Path.sharp',
        '../../../sharp/composer/library/Sharp/Json/Json.sharp',
        '../../../sharp/composer/library/Sharp/Math/Math.sharp',
        '../../../sharp/composer/library/Sharp/Net/Email.sharp',
        '../../../sharp/composer/library/Sharp/Net/Ip.sharp',
        '../../../sharp/composer/library/Sharp/Net/Url.sharp',
        '../../../sharp/composer/library/Sharp/Text/Html.sharp',
        '../../../sharp/composer/library/Sharp/Text/Regex.sharp',
        '../../../sharp/composer/library/Sharp/Text/Text.sharp',
        '../../../sharp/composer/library/Sharp/Time/Date.sharp',
        '../../../sharp/composer/library/Sharp/Time/TimeZone.sharp',
    ],
    'Inbox' => null,
    'Patterns' => null,
    'Signatures' => null,
    'Accessors' => null,
    'Parcel' => 'harness/Row.inc',
    'Exits' => null,
    'Positions' => null,
    'Deploy' => null,
    'Tags' => null,
    'PhpForms' => null,
];
$user_class = '/(?:Class|Enum) \[ <user> /';

foreach ($fixtures as $fixture => $library) {
    $prelude = implode('', array_map(static fn (string $file): string => 'require ' . var_export(__DIR__ . "/$file", true) . ';', (array) $library));
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
Expressions: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Checkout: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Page: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Stage: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Suit: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Rank: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Shipment: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Task: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Shapes: same opcodes and lines in 5 op arrays, same signatures in 2 classes
Members: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Site: same opcodes and lines in 13 op arrays, same signatures in 3 classes
Collections: same opcodes and lines in 7 op arrays, same signatures in 2 classes
EnumKeys: same opcodes and lines in 12 op arrays, same signatures in 1 classes
Interop: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Lambdas: same opcodes and lines in 35 op arrays, same signatures in 1 classes
Cashier: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Roster: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Sets: same opcodes and lines in 26 op arrays, same signatures in 1 classes
Store: same opcodes and lines in 3 op arrays, same signatures in 1 classes
RushOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
TypedOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
Permalink: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Library: same opcodes and lines in 19 op arrays, same signatures in 1 classes
Inbox: same opcodes and lines in 9 op arrays, same signatures in 1 classes
Patterns: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Signatures: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Accessors: same opcodes and lines in 15 op arrays, same signatures in 2 classes
Parcel: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Exits: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Positions: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Deploy: same opcodes and lines in 7 op arrays, same signatures in 1 classes
Tags: same opcodes and lines in 2 op arrays, same signatures in 1 classes
PhpForms: same opcodes and lines in 15 op arrays, same signatures in 1 classes
