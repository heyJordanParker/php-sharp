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

    // A PHP# class that writes no parse or tryParse gets them on the line that declares it, where no twin can write a
    // method. The parse tests run them, so the comparison leaves out each parse and tryParse on such a line.
    $source = file($file);
    $generated = static fn (string $first, string $last): bool
        => $first === $last && preg_match('~\b(?:class|struct)\b~', $source[$first - 1]) === 1;
    $left_out = [];
    $listing = preg_replace_callback('~\n^(\S+)::(parse|tryParse):\n((?:[ \dLE].*\n)*)~m', static function (array $match) use ($generated, &$left_out): string {
        preg_match('~:(\d+)-(\d+)$~m', $match[3], $lines);
        if (!$generated($lines[1], $lines[2])) {
            return $match[0];
        }
        $left_out[] = "$match[1]::$match[2]";

        return '';
    }, $opcodes . $dump);
    $listing = preg_replace_callback('~^user class: (\S+)\n\d+ methods: (.*)$~m', static function (array $match) use ($left_out): string {
        $methods = array_filter(explode(', ', $match[2]), static fn (string $method): bool
            => $method !== '-' && !in_array("$match[1]::$method", $left_out, true));

        return "user class: $match[1]\n" . count($methods) . ' methods: ' . ($methods ? implode(', ', $methods) : '-');
    }, $listing);
    $classes = preg_replace_callback('~^ {4}Method \[ <user[^\]]*> static public method (?:parse|tryParse) \] \{\n {6}@@ \S+ (\d+) - (\d+)\n.*?^ {4}\}\n~ms', static fn (array $match): string
        => $generated($match[1], $match[2]) ? '' : $match[0], $classes);
    $classes = preg_replace(['~(- Static methods )\[\d+\]~', '~\n\n+~'], ['$1[]', "\n"], $classes);

    return str_replace($file, '<file>', $listing . $classes);
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
    'Operators' => 'harness/Billing.inc',
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
Collections: different opcodes and lines in 7 op arrays, same signatures in 2 classes
  .sharp     Property [ private Sharp\Map $counts = new \Sharp\Map() ]
  .php       Property [ private Sharp\Map $counts ]
EnumKeys: different opcodes and lines in 22 op arrays, same signatures in 1 classes
  .sharp L0023 0000 CV0($key#1) = RECV 1
  .sharp L0023 0001 CV1($value#2) = RECV 2
  .sharp L0023 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0023 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0029 0000 CV0($key#1) = RECV 1
  .sharp L0029 0001 CV1($value#2) = RECV 2
  .sharp L0029 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0029 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0047 0000 CV0($key#1) = RECV 1
  .sharp L0047 0001 CV1($value#2) = RECV 2
  .sharp L0047 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0052 0000 CV0($key#1) = RECV 1
  .sharp L0052 0001 CV1($value#2) = RECV 2
  .sharp L0052 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0052 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0067 0000 CV0($key#1) = RECV 1
  .sharp L0067 0001 CV1($value#2) = RECV 2
  .sharp L0067 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0067 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0076 0000 CV0($key#1) = RECV 1
  .sharp L0076 0001 CV1($value#2) = RECV 2
  .sharp L0076 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0076 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp     Property [ private Sharp\Map $counts = new \Sharp\Map() ]
  .sharp     Property [ private Sharp\Map $seeded = new \Sharp\Map([\Lib\Standing::Active, 0]) ]
  .php   L0023 0000 CV0($key) = RECV 1
  .php   L0023 0001 CV1($value) = RECV 2
  .php   L0023 0003 T2 = CAST (string) CV0($key)
  .php   L0023 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0029 0000 CV0($key) = RECV 1
  .php   L0029 0001 CV1($value) = RECV 2
  .php   L0029 0003 T2 = CAST (string) CV0($key)
  .php   L0029 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0047 0000 CV0($key) = RECV 1
  .php   L0047 0001 CV1($value) = RECV 2
  .php   L0047 0003 T2 = CAST (string) CV0($key)
  .php   L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0052 0000 CV0($key) = RECV 1
  .php   L0052 0001 CV1($value) = RECV 2
  .php   L0052 0003 T2 = CAST (string) CV0($key)
  .php   L0052 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0067 0000 CV0($key) = RECV 1
  .php   L0067 0001 CV1($value) = RECV 2
  .php   L0067 0003 T2 = CAST (string) CV0($key)
  .php   L0067 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0076 0000 CV0($key) = RECV 1
  .php   L0076 0001 CV1($value) = RECV 2
  .php   L0076 0002 T2 = CAST (string) CV0($key)
  .php   L0076 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php       Property [ private Sharp\Map $counts ]
  .php       Property [ private Sharp\Map $seeded ]
Interop: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Lambdas: different opcodes and lines in 36 op arrays, same signatures in 1 classes
  .sharp L0043 0000 CV0($key#1) = RECV 1
  .sharp L0043 0001 CV1($value#2) = RECV 2
  .sharp L0043 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0043 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php   L0043 0000 CV0($key) = RECV 1
  .php   L0043 0001 CV1($value) = RECV 2
  .php   L0043 0002 T2 = CAST (string) CV0($key)
  .php   L0043 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Cashier: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Roster: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  .sharp L0007 0000 CV0($key#1) = RECV 1
  .sharp L0007 0001 CV1($value#2) = RECV 2
  .sharp L0007 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0007 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php   L0007 0000 CV0($key) = RECV 1
  .php   L0007 0001 CV1($value) = RECV 2
  .php   L0007 0003 T2 = CAST (string) CV0($key)
  .php   L0007 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Sets: different opcodes and lines in 27 op arrays, same signatures in 1 classes
  .sharp L0102 0000 CV0($collection#1) = RECV 1
  .sharp L0102 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp L0102 0004 SEND_VAR_EX CV0($collection#1) 1
  .sharp L0102 0008 T4 = QM_ASSIGN CV0($collection#1)
  .sharp L0111 0000 CV0($collection#1) = RECV 1
  .sharp L0111 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp L0111 0004 SEND_VAR_EX CV0($collection#1) 1
  .sharp L0111 0008 T4 = QM_ASSIGN CV0($collection#1)
  .sharp 0000 CV0($collection#1) = RECV 1
  .sharp 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp 0004 SEND_VAR_EX CV0($collection#1) 1
  .sharp 0008 T4 = QM_ASSIGN CV0($collection#1)
  .sharp 0000 CV0($collection#1) = RECV 1
  .sharp 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp 0004 SEND_VAR_EX CV0($collection#1) 1
  .sharp 0008 T4 = QM_ASSIGN CV0($collection#1)
  .sharp     Property [ public Sharp\Set $tags = new \Sharp\Set([0 => 'new']) ]
  .sharp     Property [ public Sharp\Set $states = new \Sharp\Set([]) { get; set; } ]
  .sharp     Property [ private Sharp\Set $sizes = new \Sharp\Set([]) ]
  .php   L0102 0000 CV0($collection) = RECV 1
  .php   L0102 0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   L0102 0004 SEND_VAR_EX CV0($collection) 1
  .php   L0102 0008 T4 = QM_ASSIGN CV0($collection)
  .php   L0111 0000 CV0($collection) = RECV 1
  .php   L0111 0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   L0111 0004 SEND_VAR_EX CV0($collection) 1
  .php   L0111 0008 T4 = QM_ASSIGN CV0($collection)
  .php   0000 CV0($collection) = RECV 1
  .php   0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   0004 SEND_VAR_EX CV0($collection) 1
  .php   0008 T4 = QM_ASSIGN CV0($collection)
  .php   0000 CV0($collection) = RECV 1
  .php   0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   0004 SEND_VAR_EX CV0($collection) 1
  .php   0008 T4 = QM_ASSIGN CV0($collection)
  .php       Property [ public Sharp\Set $tags ]
  .php       Property [ public Sharp\Set $states { get; set; } ]
  .php       Property [ private Sharp\Set $sizes ]
Store: same opcodes and lines in 3 op arrays, same signatures in 1 classes
RushOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
TypedOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
Permalink: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Library: different opcodes and lines in 20 op arrays, same signatures in 1 classes
  .sharp L0054 0000 CV0($key#1) = RECV 1
  .sharp L0054 0001 CV1($value#2) = RECV 2
  .sharp L0054 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0054 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php   L0054 0000 CV0($key) = RECV 1
  .php   L0054 0001 CV1($value) = RECV 2
  .php   L0054 0002 T2 = CAST (string) CV0($key)
  .php   L0054 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Inbox: different opcodes and lines in 9 op arrays, same signatures in 1 classes
  .sharp     Property [ private Sharp\Map $saved = new \Sharp\Map() ]
  .php       Property [ private Sharp\Map $saved ]
Patterns: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Signatures: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Accessors: same opcodes and lines in 15 op arrays, same signatures in 2 classes
Parcel: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Exits: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Positions: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Deploy: same opcodes and lines in 7 op arrays, same signatures in 1 classes
Tags: same opcodes and lines in 2 op arrays, same signatures in 1 classes
PhpForms: same opcodes and lines in 15 op arrays, same signatures in 1 classes
Operators: different opcodes and lines in 73 op arrays, same signatures in 9 classes
  .sharp L0265 0000 CV0($key#1) = RECV 1
  .sharp L0265 0001 CV1($value#2) = RECV 2
  .sharp L0265 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0265 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0265 0000 CV0($key#1) = RECV 1
  .sharp L0265 0001 CV1($value#2) = RECV 2
  .sharp L0265 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0265 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php   L0265 0000 CV0($key) = RECV 1
  .php   L0265 0001 CV1($value) = RECV 2
  .php   L0265 0002 T2 = CAST (string) CV0($key)
  .php   L0265 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0265 0000 CV0($key) = RECV 1
  .php   L0265 0001 CV1($value) = RECV 2
  .php   L0265 0002 T2 = CAST (string) CV0($key)
  .php   L0265 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
