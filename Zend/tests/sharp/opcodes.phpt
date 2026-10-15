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

/**
 * `$listing` without the one opcode PHP# adds on purpose: a SHARP_RECV_TYPE_ARGS with no result, which checks the
 * parameters of a call from plain PHP that its PHP twin's types can't check. Each op array loses it and counts one
 * line less, and every op number after it, in an op line, a jump, a live range or a try block, is one lower. Each check lands in
 * `$checks` by its op array, once for both listings.
 */
function without_checks(string $listing, array &$checks): string
{
    $lines = explode("\n", $listing);
    $header = -1;
    $dropped = [];
    $renumber = static function (array $op) use (&$dropped): string {
        return sprintf('%04d', (int) $op[0] - count(array_filter($dropped, static fn (int $at): bool => $at < (int) $op[0])));
    };
    $kept = [];
    foreach ($lines as $index => $line) {
        if (preg_match('~^(\S.*):$~', $line, $name) && preg_match('~^ {5}; \(lines=~', $lines[$index + 1] ?? '')) {
            $header = count($kept) + 1;
            $dropped = [];
            $op_array = $name[1];
        } elseif ($header >= 0 && preg_match('~^(?:L\d{4} )?(\d{4}) SHARP_RECV_TYPE_ARGS (.*)$~', $line, $check)) {
            $dropped[] = (int) $check[1];
            $checks[$op_array] = $check[2];
            $kept[$header] = preg_replace_callback('~lines=(\d+)~', static fn (array $count): string => 'lines=' . ($count[1] - 1), $kept[$header]);
            continue;
        } elseif ($dropped && preg_match('~^(?:L\d{4} )?\d{4} |^ +(?:\d+: )?\d{4}[ ,]~', $line)) {
            $line = preg_replace_callback('~(?<![\w(])\d{4}(?![\w)])~', $renumber, $line);
        }
        $kept[] = $line;
    }

    return implode("\n", $kept);
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
    'Generics' => null,
    'Operators' => 'harness/Billing.inc',
    'Traits' => 'harness/Traits.inc',
];
$user_class = '/(?:Class|Enum) \[ <user> /';

foreach ($fixtures as $fixture => $library) {
    $prelude = implode('', array_map(static fn (string $file): string => 'require ' . var_export(__DIR__ . "/$file", true) . ';', (array) $library));
    $checks = [];
    $sharp = without_checks(compiled(__DIR__ . "/$fixture.sharp", $prelude), $checks);
    $php = compiled(__DIR__ . "/$fixture.inc", $prelude);
    // An op array either listing prints counts once: phpdbg prints abstract methods, and the dump prints hooks.
    preg_match_all('~^(\S.*):\n {5}; \(lines=~m', $sharp, $op_arrays);
    echo $fixture, ': ', $sharp === $php ? 'same' : 'different', ' opcodes and lines in ',
        count(array_unique($op_arrays[1])), ' op arrays, ',
        preg_match_all($user_class, $sharp) === preg_match_all($user_class, $php) ? 'same' : 'different',
        ' signatures in ', preg_match_all($user_class, $sharp), " classes\n";
    foreach ($checks as $op_array => $text) {
        echo '  check  ', $op_array, ' ', $text, "\n";
    }

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

// Dropping the checks hides no other opcode: one more beside a check still differs from the twin.
$checks = [];
$twin = "Demo\\run:\n     ; (lines=2, args=1, vars=1, tmps=0)\n0000 CV0(\$a) = RECV 1\n0001 JMP 0001";
$checked = "Demo\\run:\n     ; (lines=3, args=1, vars=1, tmps=0)\n0000 CV0(\$a) = RECV 1\n0001 SHARP_RECV_TYPE_ARGS string(\"List<int>\")\n0002 JMP 0002";
$added = "Demo\\run:\n     ; (lines=4, args=1, vars=1, tmps=0)\n0000 CV0(\$a) = RECV 1\n0001 SHARP_RECV_TYPE_ARGS string(\"List<int>\")\n0002 NOP\n0003 JMP 0003";
echo 'a check: ', without_checks($checked, $checks) === $twin ? 'same' : 'different', "\n";
echo 'a check and another opcode: ', without_checks($added, $checks) === $twin ? 'same' : 'different', "\n";
?>
--EXPECT--
Calc: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Nulls: same opcodes and lines in 17 op arrays, same signatures in 1 classes
ControlFlow: different opcodes and lines in 6 op arrays, same signatures in 1 classes
  .sharp      ; (lines=25, args=1, vars=4, tmps=13)
  .sharp L0061 0004 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("Map<int, int>")
  .sharp L0061 0005 V7 = FE_RESET_R T6 0010
  .sharp L0061 0006 FE_FETCH_R V7 CV2($price) 0010
  .sharp L0062 0007 T8 = MUL CV2($price) CV0($factor)
  .sharp L0062 0008 ASSIGN_OP (ADD) CV1($total) T8
  .sharp L0061 0009 JMP 0006
  .sharp L0061 0010 FE_FREE V7
  .sharp L0064 0011 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .sharp L0064 0012 V10 = DO_FCALL
  .sharp L0064 0013 T11 = SHARP_VERIFY_TYPE_ARGS V10 string("Map<int, int>")
  .sharp L0064 0014 V12 = FE_RESET_R T11 0020
  .sharp L0064 0015 T13 = FE_FETCH_R V12 CV2($price) 0020
  .sharp L0064 0016 ASSIGN CV3($slot) T13
  .sharp L0065 0017 ASSIGN_OP (ADD) CV1($total) CV3($slot)
  .sharp L0066 0018 ASSIGN CV2($price) int(0)
  .sharp L0064 0019 JMP 0015
  .sharp L0064 0020 FE_FREE V12
  .sharp L0068 0021 VERIFY_RETURN_TYPE CV1($total)
  .sharp L0068 0022 RETURN CV1($total)
  .sharp L0069 0023 VERIFY_RETURN_TYPE
  .sharp L0069 0024 RETURN null
  .sharp      ; (lines=25, args=1, vars=4, tmps=13)
  .sharp 0004 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("Map<int, int>")
  .sharp 0005 V7 = FE_RESET_R T6 0010
  .sharp 0006 FE_FETCH_R V7 CV2($price) 0010
  .sharp 0007 T8 = MUL CV2($price) CV0($factor)
  .sharp 0008 ASSIGN_OP (ADD) CV1($total) T8
  .sharp 0009 JMP 0006
  .sharp 0010 FE_FREE V7
  .sharp 0011 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .sharp 0012 V10 = DO_FCALL
  .sharp 0013 T11 = SHARP_VERIFY_TYPE_ARGS V10 string("Map<int, int>")
  .sharp 0014 V12 = FE_RESET_R T11 0020
  .sharp 0015 T13 = FE_FETCH_R V12 CV2($price) 0020
  .sharp 0016 ASSIGN CV3($slot) T13
  .sharp 0017 ASSIGN_OP (ADD) CV1($total) CV3($slot)
  .sharp 0018 ASSIGN CV2($price) int(0)
  .sharp 0019 JMP 0015
  .sharp 0020 FE_FREE V12
  .sharp 0021 VERIFY_RETURN_TYPE CV1($total)
  .sharp 0022 RETURN CV1($total)
  .sharp 0023 VERIFY_RETURN_TYPE
  .sharp 0024 RETURN null
  .sharp      7: 0006 - 0010 (loop)
  .sharp      12: 0015 - 0020 (loop)
  .php        ; (lines=23, args=1, vars=4, tmps=11)
  .php   L0061 0004 V6 = FE_RESET_R V5 0009
  .php   L0061 0005 FE_FETCH_R V6 CV2($price) 0009
  .php   L0062 0006 T7 = MUL CV2($price) CV0($factor)
  .php   L0062 0007 ASSIGN_OP (ADD) CV1($total) T7
  .php   L0061 0008 JMP 0005
  .php   L0061 0009 FE_FREE V6
  .php   L0064 0010 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .php   L0064 0011 V9 = DO_FCALL
  .php   L0064 0012 V10 = FE_RESET_R V9 0018
  .php   L0064 0013 T11 = FE_FETCH_R V10 CV2($price) 0018
  .php   L0064 0014 ASSIGN CV3($slot) T11
  .php   L0065 0015 ASSIGN_OP (ADD) CV1($total) CV3($slot)
  .php   L0066 0016 ASSIGN CV2($price) int(0)
  .php   L0064 0017 JMP 0013
  .php   L0064 0018 FE_FREE V10
  .php   L0068 0019 VERIFY_RETURN_TYPE CV1($total)
  .php   L0068 0020 RETURN CV1($total)
  .php   L0069 0021 VERIFY_RETURN_TYPE
  .php   L0069 0022 RETURN null
  .php        ; (lines=23, args=1, vars=4, tmps=11)
  .php   0004 V6 = FE_RESET_R V5 0009
  .php   0005 FE_FETCH_R V6 CV2($price) 0009
  .php   0006 T7 = MUL CV2($price) CV0($factor)
  .php   0007 ASSIGN_OP (ADD) CV1($total) T7
  .php   0008 JMP 0005
  .php   0009 FE_FREE V6
  .php   0010 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .php   0011 V9 = DO_FCALL
  .php   0012 V10 = FE_RESET_R V9 0018
  .php   0013 T11 = FE_FETCH_R V10 CV2($price) 0018
  .php   0014 ASSIGN CV3($slot) T11
  .php   0015 ASSIGN_OP (ADD) CV1($total) CV3($slot)
  .php   0016 ASSIGN CV2($price) int(0)
  .php   0017 JMP 0013
  .php   0018 FE_FREE V10
  .php   0019 VERIFY_RETURN_TYPE CV1($total)
  .php   0020 RETURN CV1($total)
  .php   0021 VERIFY_RETURN_TYPE
  .php   0022 RETURN null
  .php        6: 0005 - 0009 (loop)
  .php        10: 0013 - 0018 (loop)
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
  check  Demo\Cart::fill string("List<Demo.Item>")
  check  Demo\Cart::withPrices string("List<int>, List<int>")
  .sharp     Property [ private Sharp\Map $counts = new \Sharp\Map() ]
  .php       Property [ private Sharp\Map $counts ]
EnumKeys: different opcodes and lines in 22 op arrays, same signatures in 1 classes
  check  Demo\Headcount::count string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::ensure string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::lookup string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::without string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::byName string("Map<Lib.Standing, int>")
  check  Demo\Headcount::skus string("Map<string, int>")
  check  Demo\Headcount::grouped string("List<int>")
  check  Demo\Headcount::groupNames string("List<int>")
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
Interop: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Lambdas: different opcodes and lines in 36 op arrays, same signatures in 1 classes
  check  Demo\Lambdas::doubled string("List<int>, Any?")
  check  Demo\Lambdas::pipeline string("List<int>, Map<string, int>")
  check  Demo\Lambdas::total string("List<int>")
  check  Demo\Lambdas::failures string("List<int>")
  check  Demo\Lambdas::captured string("List<int>")
  check  Demo\Lambdas::scaled string("List<int>")
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
Cashier: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Cashier::receipt string("List<int>, Any?, Any?")
  .sharp      ; (lines=61, args=3, vars=7, tmps=30)
  .sharp L0022 0035 T24 = SHARP_VERIFY_TYPE_ARGS V23 string("List<int>")
  .sharp L0022 0036 ASSIGN CV6($recorded) T24
  .sharp L0024 0037 T26 = CAST (object) CV4($paid)
  .sharp L0024 0038 INIT_METHOD_CALL 1 T26 string("map")
  .sharp L0024 0039 INIT_METHOD_CALL 0 THIS string("format")
  .sharp L0024 0040 T27 = CALLABLE_CONVERT
  .sharp L0024 0041 SEND_VAL_EX T27 1
  .sharp L0024 0042 V28 = DO_FCALL
  .sharp L0024 0043 T29 = FRAMELESS_ICALL_2(implode) string(",") V28
  .sharp L0024 0044 T30 = INIT_ARRAY 5 (packed) T29 NEXT
  .sharp L0025 0045 T31 = CAST (string) CV5($total)
  .sharp L0025 0046 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp L0026 0047 T32 = CAST (string) CV3($count)
  .sharp L0026 0048 T30 = ADD_ARRAY_ELEMENT T32 NEXT
  .sharp L0027 0049 T33 = FRAMELESS_ICALL_2(implode) string(",") CV6($recorded)
  .sharp L0027 0050 T30 = ADD_ARRAY_ELEMENT T33 NEXT
  .sharp L0028 0051 T34 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp L0028 0052 INIT_DYNAMIC_CALL 1 T34
  .sharp L0028 0053 SEND_VAR_EX CV5($total) 1
  .sharp L0028 0054 V35 = DO_FCALL
  .sharp L0028 0055 T36 = CAST (string) V35
  .sharp L0028 0056 T30 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp L0028 0057 VERIFY_RETURN_TYPE T30
  .sharp L0028 0058 RETURN T30
  .sharp L0030 0059 VERIFY_RETURN_TYPE
  .sharp L0030 0060 RETURN null
  .sharp      ; (lines=61, args=3, vars=7, tmps=30)
  .sharp 0035 T24 = SHARP_VERIFY_TYPE_ARGS V23 string("List<int>")
  .sharp 0036 ASSIGN CV6($recorded) T24
  .sharp 0037 T26 = CAST (object) CV4($paid)
  .sharp 0038 INIT_METHOD_CALL 1 T26 string("map")
  .sharp 0039 INIT_METHOD_CALL 0 THIS string("format")
  .sharp 0040 T27 = CALLABLE_CONVERT
  .sharp 0041 SEND_VAL_EX T27 1
  .sharp 0042 V28 = DO_FCALL
  .sharp 0043 T29 = FRAMELESS_ICALL_2(implode) string(",") V28
  .sharp 0044 T30 = INIT_ARRAY 5 (packed) T29 NEXT
  .sharp 0045 T31 = CAST (string) CV5($total)
  .sharp 0046 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp 0047 T32 = CAST (string) CV3($count)
  .sharp 0048 T30 = ADD_ARRAY_ELEMENT T32 NEXT
  .sharp 0049 T33 = FRAMELESS_ICALL_2(implode) string(",") CV6($recorded)
  .sharp 0050 T30 = ADD_ARRAY_ELEMENT T33 NEXT
  .sharp 0051 T34 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp 0052 INIT_DYNAMIC_CALL 1 T34
  .sharp 0053 SEND_VAR_EX CV5($total) 1
  .sharp 0054 V35 = DO_FCALL
  .sharp 0055 T36 = CAST (string) V35
  .sharp 0056 T30 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp 0057 VERIFY_RETURN_TYPE T30
  .sharp 0058 RETURN T30
  .sharp 0059 VERIFY_RETURN_TYPE
  .sharp 0060 RETURN null
  .sharp      30: 0045 - 0058 (tmp/var)
  .php        ; (lines=60, args=3, vars=7, tmps=29)
  .php   L0022 0035 ASSIGN CV6($recorded) V23
  .php   L0024 0036 T25 = CAST (object) CV4($paid)
  .php   L0024 0037 INIT_METHOD_CALL 1 T25 string("map")
  .php   L0024 0038 INIT_METHOD_CALL 0 THIS string("format")
  .php   L0024 0039 T26 = CALLABLE_CONVERT
  .php   L0024 0040 SEND_VAL_EX T26 1
  .php   L0024 0041 V27 = DO_FCALL
  .php   L0024 0042 T28 = FRAMELESS_ICALL_2(implode) string(",") V27
  .php   L0024 0043 T29 = INIT_ARRAY 5 (packed) T28 NEXT
  .php   L0025 0044 T30 = CAST (string) CV5($total)
  .php   L0025 0045 T29 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   L0026 0046 T31 = CAST (string) CV3($count)
  .php   L0026 0047 T29 = ADD_ARRAY_ELEMENT T31 NEXT
  .php   L0027 0048 T32 = FRAMELESS_ICALL_2(implode) string(",") CV6($recorded)
  .php   L0027 0049 T29 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   L0028 0050 T33 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   L0028 0051 INIT_DYNAMIC_CALL 1 T33
  .php   L0028 0052 SEND_VAR_EX CV5($total) 1
  .php   L0028 0053 V34 = DO_FCALL
  .php   L0028 0054 T35 = CAST (string) V34
  .php   L0028 0055 T29 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   L0028 0056 VERIFY_RETURN_TYPE T29
  .php   L0028 0057 RETURN T29
  .php   L0030 0058 VERIFY_RETURN_TYPE
  .php   L0030 0059 RETURN null
  .php        ; (lines=60, args=3, vars=7, tmps=29)
  .php   0035 ASSIGN CV6($recorded) V23
  .php   0036 T25 = CAST (object) CV4($paid)
  .php   0037 INIT_METHOD_CALL 1 T25 string("map")
  .php   0038 INIT_METHOD_CALL 0 THIS string("format")
  .php   0039 T26 = CALLABLE_CONVERT
  .php   0040 SEND_VAL_EX T26 1
  .php   0041 V27 = DO_FCALL
  .php   0042 T28 = FRAMELESS_ICALL_2(implode) string(",") V27
  .php   0043 T29 = INIT_ARRAY 5 (packed) T28 NEXT
  .php   0044 T30 = CAST (string) CV5($total)
  .php   0045 T29 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   0046 T31 = CAST (string) CV3($count)
  .php   0047 T29 = ADD_ARRAY_ELEMENT T31 NEXT
  .php   0048 T32 = FRAMELESS_ICALL_2(implode) string(",") CV6($recorded)
  .php   0049 T29 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   0050 T33 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   0051 INIT_DYNAMIC_CALL 1 T33
  .php   0052 SEND_VAR_EX CV5($total) 1
  .php   0053 V34 = DO_FCALL
  .php   0054 T35 = CAST (string) V34
  .php   0055 T29 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   0056 VERIFY_RETURN_TYPE T29
  .php   0057 RETURN T29
  .php   0058 VERIFY_RETURN_TYPE
  .php   0059 RETURN null
  .php        29: 0044 - 0057 (tmp/var)
Roster: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Roster::report string("Map<Lib.Standing, int>")
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
  check  Demo\Tagged::__construct string("Set<int>, Set<string>")
  check  Demo\Tagged::strings string("Set<string>")
  check  Demo\Tagged::reads string("Set<string>")
  check  Demo\Tagged::missing string("Set<string>")
  check  Demo\Tagged::ints string("Set<int>")
  check  Demo\Tagged::cases string("Set<Lib.Standing>")
  check  Demo\Tagged::once string("Set<string>?")
  check  Demo\Tagged::lambdas string("List<string>")
  check  {closure:Demo\Tagged::lambdas():95} string("Set<string>")
  check  {closure:Demo\Tagged::lambdas():96} string("Set<string>")
  check  Demo\Tagged::groups string("Set<string>")
  check  Demo\Tagged::maybe string("Set<string>?")
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
  check  Demo\Library::query string("Map<string, Any?>")
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
  check  Demo\Inbox::keep string("Any")
  .sharp     Property [ private Sharp\Map $saved = new \Sharp\Map() ]
  .php       Property [ private Sharp\Map $saved ]
Patterns: different opcodes and lines in 18 op arrays, same signatures in 1 classes
  check  Demo\Patterns::lines string("List<Demo.Figure>")
  check  Demo\Patterns::head string("List<Demo.Figure>?")
  check  Demo\Patterns::ends string("List<Demo.Figure>")
  check  Demo\Patterns::circles string("List<Demo.Figure>")
  check  Demo\Patterns::listed string("Any")
  check  Demo\Patterns::rows string("List<Any>")
  .sharp      ; (lines=61, args=1, vars=5, tmps=26)
  .sharp L0098 0007 JMPNZ T5 0047
  .sharp L0099 0019 JMPNZ T5 0049
  .sharp L0100 0035 T15 = JMPZ_EX T15 0044
  .sharp L0100 0041 T26 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp L0100 0042 T27 = SHARP_IS_TYPE T25 T26
  .sharp L0100 0043 T15 = BOOL T27
  .sharp L0100 0044 T5 = IS_IDENTICAL T15 bool(true)
  .sharp L0100 0045 JMPNZ T5 0051
  .sharp L0100 0046 JMP 0055
  .sharp L0098 0047 T28 = QM_ASSIGN int(0)
  .sharp L0098 0048 JMP 0057
  .sharp L0099 0049 T28 = QM_ASSIGN int(1)
  .sharp L0099 0050 JMP 0057
  .sharp L0100 0051 T29 = COUNT CV4($rest)
  .sharp L0100 0052 T30 = ADD int(2) T29
  .sharp L0100 0053 T28 = QM_ASSIGN T30
  .sharp L0100 0054 JMP 0057
  .sharp L0101 0055 T28 = QM_ASSIGN int(-1)
  .sharp L0101 0056 JMP 0057
  .sharp L0101 0057 VERIFY_RETURN_TYPE T28
  .sharp L0101 0058 RETURN T28
  .sharp L0102 0059 VERIFY_RETURN_TYPE
  .sharp L0102 0060 RETURN null
  .sharp      ; (lines=31, args=1, vars=3, tmps=13)
  .sharp L0106 0011 T3 = JMPZ_EX T3 0020
  .sharp L0106 0017 T11 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp L0106 0018 T12 = SHARP_IS_TYPE T10 T11
  .sharp L0106 0019 T3 = BOOL T12
  .sharp L0106 0020 T3 = JMPZ_EX T3 0024
  .sharp L0106 0021 T13 = COUNT CV2($others)
  .sharp L0106 0022 T14 = IS_IDENTICAL T13 int(0)
  .sharp L0106 0023 T3 = BOOL T14
  .sharp L0106 0024 JMPZ T3 0028
  .sharp L0107 0025 T15 = FETCH_OBJ_R CV1($first) string("radius")
  .sharp L0107 0026 VERIFY_RETURN_TYPE T15
  .sharp L0107 0027 RETURN T15
  .sharp L0109 0028 RETURN float(0)
  .sharp L0110 0029 VERIFY_RETURN_TYPE
  .sharp L0110 0030 RETURN null
  .sharp      ; (lines=99, args=1, vars=7, tmps=53)
  .sharp L0113 0018 T8 = JMPZ_EX T8 0030
  .sharp L0113 0027 T23 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp L0113 0028 T24 = SHARP_IS_TYPE T22 T23
  .sharp L0113 0029 T8 = BOOL T24
  .sharp L0113 0030 T7 = IS_IDENTICAL T8 bool(true)
  .sharp L0113 0031 JMPNZ T7 0078
  .sharp L0114 0032 T25 = TYPE_CHECK (array) CV0($shapes)
  .sharp L0114 0033 T25 = JMPZ_EX T25 0037
  .sharp L0114 0034 T26 = COUNT CV0($shapes)
  .sharp L0114 0035 T27 = IS_SMALLER_OR_EQUAL int(1) T26
  .sharp L0114 0036 T25 = BOOL T27
  .sharp L0114 0037 T25 = JMPZ_EX T25 0044
  .sharp L0114 0038 T28 = COUNT CV0($shapes)
  .sharp L0114 0039 T29 = SUB T28 int(1)
  .sharp L0114 0040 T30 = FETCH_DIM_R CV0($shapes) T29
  .sharp L0114 0041 T31 = ASSIGN CV4($last) T30
  .sharp L0114 0042 T32 = INSTANCEOF T31 string("Demo\\Circle")
  .sharp L0114 0043 T25 = BOOL T32
  .sharp L0114 0044 T25 = JMPZ_EX T25 0056
  .sharp L0114 0045 INIT_FCALL 3 128 string("array_slice")
  .sharp L0114 0046 SEND_VAR CV0($shapes) 1
  .sharp L0114 0047 SEND_VAL int(0) 2
  .sharp L0114 0048 T33 = COUNT CV0($shapes)
  .sharp L0114 0049 T34 = SUB T33 int(1)
  .sharp L0114 0050 SEND_VAL T34 3
  .sharp L0114 0051 V35 = DO_ICALL
  .sharp L0114 0052 T36 = ASSIGN CV5($before) V35
  .sharp L0114 0053 T37 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp L0114 0054 T38 = SHARP_IS_TYPE T36 T37
  .sharp L0114 0055 T25 = BOOL T38
  .sharp L0114 0056 T7 = IS_IDENTICAL T25 bool(true)
  .sharp L0114 0057 JMPNZ T7 0083
  .sharp L0115 0058 T39 = TYPE_CHECK (array) CV0($shapes)
  .sharp L0115 0059 T39 = JMPZ_EX T39 0063
  .sharp L0115 0060 T40 = COUNT CV0($shapes)
  .sharp L0115 0061 T41 = IS_SMALLER_OR_EQUAL int(2) T40
  .sharp L0115 0062 T39 = BOOL T41
  .sharp L0115 0063 T39 = JMPZ_EX T39 0068
  .sharp L0115 0064 T42 = FETCH_DIM_R CV0($shapes) int(0)
  .sharp L0115 0065 T43 = ASSIGN CV6($first) T42
  .sharp L0115 0066 T44 = INSTANCEOF T43 string("Demo\\Square")
  .sharp L0115 0067 T39 = BOOL T44
  .sharp L0115 0068 T39 = JMPZ_EX T39 0075
  .sharp L0115 0069 T45 = COUNT CV0($shapes)
  .sharp L0115 0070 T46 = SUB T45 int(1)
  .sharp L0115 0071 T47 = FETCH_DIM_R CV0($shapes) T46
  .sharp L0115 0072 T48 = ASSIGN CV4($last) T47
  .sharp L0115 0073 T49 = INSTANCEOF T48 string("Demo\\Square")
  .sharp L0115 0074 T39 = BOOL T49
  .sharp L0115 0075 T7 = IS_IDENTICAL T39 bool(true)
  .sharp L0115 0076 JMPNZ T7 0088
  .sharp L0115 0077 JMP 0093
  .sharp L0113 0078 T50 = COUNT CV3($inner)
  .sharp L0113 0079 T51 = CAST (double) T50
  .sharp L0113 0080 T52 = MUL T51 int(-1)
  .sharp L0113 0081 T53 = QM_ASSIGN T52
  .sharp L0113 0082 JMP 0095
  .sharp L0114 0083 T54 = FETCH_OBJ_R CV4($last) string("radius")
  .sharp L0114 0084 T55 = COUNT CV5($before)
  .sharp L0114 0085 T56 = ADD T54 T55
  .sharp L0114 0086 T53 = QM_ASSIGN T56
  .sharp L0114 0087 JMP 0095
  .sharp L0115 0088 T57 = FETCH_OBJ_R CV6($first) string("side")
  .sharp L0115 0089 T58 = FETCH_OBJ_R CV4($last) string("side")
  .sharp L0115 0090 T59 = ADD T57 T58
  .sharp L0115 0091 T53 = QM_ASSIGN T59
  .sharp L0115 0092 JMP 0095
  .sharp L0116 0093 T53 = QM_ASSIGN float(0)
  .sharp L0116 0094 JMP 0095
  .sharp L0116 0095 VERIFY_RETURN_TYPE T53
  .sharp L0116 0096 RETURN T53
  .sharp L0117 0097 VERIFY_RETURN_TYPE
  .sharp L0117 0098 RETURN null
  .sharp      ; (lines=32, args=1, vars=3, tmps=13)
  .sharp L0120 0011 T4 = JMPZ_EX T4 0020
  .sharp L0120 0017 T12 = SHARP_TYPE_ARGS string("List<Demo.Circle>")
  .sharp L0120 0018 T13 = SHARP_IS_TYPE T11 T12
  .sharp L0120 0019 T4 = BOOL T13
  .sharp L0120 0020 T3 = IS_IDENTICAL T4 bool(true)
  .sharp L0120 0021 JMPNZ T3 0023
  .sharp L0120 0022 JMP 0026
  .sharp L0120 0023 T14 = COUNT CV2($rest)
  .sharp L0120 0024 T15 = QM_ASSIGN T14
  .sharp L0120 0025 JMP 0028
  .sharp L0121 0026 T15 = QM_ASSIGN int(-1)
  .sharp L0121 0027 JMP 0028
  .sharp L0121 0028 VERIFY_RETURN_TYPE T15
  .sharp L0121 0029 RETURN T15
  .sharp L0122 0030 VERIFY_RETURN_TYPE
  .sharp L0122 0031 RETURN null
  .sharp      ; (lines=59, args=1, vars=4, tmps=26)
  .sharp L0125 0001 T5 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp L0125 0002 T6 = SHARP_IS_TYPE CV0($value) T5
  .sharp L0125 0003 T6 = JMPZ_EX T6 0007
  .sharp L0125 0004 T7 = COUNT CV0($value)
  .sharp L0125 0005 T8 = IS_IDENTICAL T7 int(0)
  .sharp L0125 0006 T6 = BOOL T8
  .sharp L0125 0007 T4 = IS_IDENTICAL T6 bool(true)
  .sharp L0125 0008 JMPNZ T4 0045
  .sharp L0126 0009 T9 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp L0126 0010 T10 = SHARP_IS_TYPE CV0($value) T9
  .sharp L0126 0011 T10 = JMPZ_EX T10 0015
  .sharp L0126 0012 T11 = COUNT CV0($value)
  .sharp L0126 0013 T12 = IS_IDENTICAL T11 int(1)
  .sharp L0126 0014 T10 = BOOL T12
  .sharp L0126 0015 T10 = JMPZ_EX T10 0020
  .sharp L0126 0016 T13 = FETCH_DIM_R CV0($value) int(0)
  .sharp L0126 0017 T14 = ASSIGN CV1($one) T13
  .sharp L0126 0018 T15 = TYPE_CHECK (long) T14
  .sharp L0126 0019 T10 = BOOL T15
  .sharp L0126 0020 T4 = IS_IDENTICAL T10 bool(true)
  .sharp L0126 0021 JMPNZ T4 0047
  .sharp L0127 0022 T16 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp L0127 0023 T17 = SHARP_IS_TYPE CV0($value) T16
  .sharp L0127 0024 T17 = JMPZ_EX T17 0028
  .sharp L0127 0025 T18 = COUNT CV0($value)
  .sharp L0127 0026 T19 = IS_SMALLER_OR_EQUAL int(1) T18
  .sharp L0127 0027 T17 = BOOL T19
  .sharp L0127 0028 T17 = JMPZ_EX T17 0033
  .sharp L0127 0029 T20 = FETCH_DIM_R CV0($value) int(0)
  .sharp L0127 0030 T21 = ASSIGN CV2($match#1) T20
  .sharp L0127 0031 T22 = TYPE_CHECK (string) T21
  .sharp L0127 0032 T17 = BOOL T22
  .sharp L0127 0033 T17 = JMPZ_EX T17 0042
  .sharp L0127 0034 INIT_FCALL 2 112 string("array_slice")
  .sharp L0127 0035 SEND_VAR CV0($value) 1
  .sharp L0127 0036 SEND_VAL int(1) 2
  .sharp L0127 0037 V23 = DO_ICALL
  .sharp L0127 0038 T24 = ASSIGN CV3($rest) V23
  .sharp L0127 0039 T25 = SHARP_TYPE_ARGS string("List<string>")
  .sharp L0127 0040 T26 = SHARP_IS_TYPE T24 T25
  .sharp L0127 0041 T17 = BOOL T26
  .sharp L0127 0042 T4 = IS_IDENTICAL T17 bool(true)
  .sharp L0127 0043 JMPNZ T4 0049
  .sharp L0127 0044 JMP 0053
  .sharp L0125 0045 T27 = QM_ASSIGN int(0)
  .sharp L0125 0046 JMP 0055
  .sharp L0126 0047 T27 = QM_ASSIGN CV1($one)
  .sharp L0126 0048 JMP 0055
  .sharp L0127 0049 T28 = COUNT CV3($rest)
  .sharp L0127 0050 T29 = ADD int(100) T28
  .sharp L0127 0051 T27 = QM_ASSIGN T29
  .sharp L0127 0052 JMP 0055
  .sharp L0128 0053 T27 = QM_ASSIGN int(-1)
  .sharp L0128 0054 JMP 0055
  .sharp L0128 0055 VERIFY_RETURN_TYPE T27
  .sharp L0128 0056 RETURN T27
  .sharp L0129 0057 VERIFY_RETURN_TYPE
  .sharp L0129 0058 RETURN null
  .sharp      ; (lines=35, args=1, vars=4, tmps=18)
  .sharp L0131 0006 T4 = JMPZ_EX T4 0016
  .sharp L0131 0009 T9 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp L0131 0010 T10 = SHARP_IS_TYPE T8 T9
  .sharp L0131 0011 T10 = JMPZ_EX T10 0015
  .sharp L0131 0012 T11 = COUNT CV1($match#1)
  .sharp L0131 0013 T12 = IS_IDENTICAL T11 int(0)
  .sharp L0131 0014 T10 = BOOL T12
  .sharp L0131 0015 T4 = BOOL T10
  .sharp L0131 0016 T4 = JMPZ_EX T4 0031
  .sharp L0131 0017 T13 = FETCH_DIM_R CV0($rows) int(1)
  .sharp L0131 0018 T14 = ASSIGN CV2($match#2) T13
  .sharp L0131 0019 T15 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp L0131 0020 T16 = SHARP_IS_TYPE T14 T15
  .sharp L0131 0021 T16 = JMPZ_EX T16 0025
  .sharp L0131 0022 T17 = COUNT CV2($match#2)
  .sharp L0131 0023 T18 = IS_IDENTICAL T17 int(1)
  .sharp L0131 0024 T16 = BOOL T18
  .sharp L0131 0025 T16 = JMPZ_EX T16 0030
  .sharp L0131 0026 T19 = FETCH_DIM_R CV2($match#2) int(0)
  .sharp L0131 0027 T20 = ASSIGN CV3($match#3) T19
  .sharp L0131 0028 T21 = TYPE_CHECK (long) T20
  .sharp L0131 0029 T16 = BOOL T21
  .sharp L0131 0030 T4 = BOOL T16
  .sharp L0131 0031 VERIFY_RETURN_TYPE T4
  .sharp L0131 0032 RETURN T4
  .sharp L0131 0033 VERIFY_RETURN_TYPE
  .sharp L0131 0034 RETURN null
  .sharp      ; (lines=61, args=1, vars=5, tmps=26)
  .sharp 0007 JMPNZ T5 0047
  .sharp 0019 JMPNZ T5 0049
  .sharp 0035 T15 = JMPZ_EX T15 0044
  .sharp 0041 T26 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp 0042 T27 = SHARP_IS_TYPE T25 T26
  .sharp 0043 T15 = BOOL T27
  .sharp 0044 T5 = IS_IDENTICAL T15 bool(true)
  .sharp 0045 JMPNZ T5 0051
  .sharp 0046 JMP 0055
  .sharp 0047 T28 = QM_ASSIGN int(0)
  .sharp 0048 JMP 0057
  .sharp 0049 T28 = QM_ASSIGN int(1)
  .sharp 0050 JMP 0057
  .sharp 0051 T29 = COUNT CV4($rest)
  .sharp 0052 T30 = ADD int(2) T29
  .sharp 0053 T28 = QM_ASSIGN T30
  .sharp 0054 JMP 0057
  .sharp 0055 T28 = QM_ASSIGN int(-1)
  .sharp 0056 JMP 0057
  .sharp 0057 VERIFY_RETURN_TYPE T28
  .sharp 0058 RETURN T28
  .sharp 0059 VERIFY_RETURN_TYPE
  .sharp 0060 RETURN null
  .sharp      25: 0041 - 0042 (tmp/var)
  .sharp      28: 0056 - 0058 (tmp/var)
  .sharp      ; (lines=31, args=1, vars=3, tmps=13)
  .sharp 0011 T3 = JMPZ_EX T3 0020
  .sharp 0017 T11 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp 0018 T12 = SHARP_IS_TYPE T10 T11
  .sharp 0019 T3 = BOOL T12
  .sharp 0020 T3 = JMPZ_EX T3 0024
  .sharp 0021 T13 = COUNT CV2($others)
  .sharp 0022 T14 = IS_IDENTICAL T13 int(0)
  .sharp 0023 T3 = BOOL T14
  .sharp 0024 JMPZ T3 0028
  .sharp 0025 T15 = FETCH_OBJ_R CV1($first) string("radius")
  .sharp 0026 VERIFY_RETURN_TYPE T15
  .sharp 0027 RETURN T15
  .sharp 0028 RETURN float(0)
  .sharp      10: 0017 - 0018 (tmp/var)
  .sharp      15: 0026 - 0027 (tmp/var)
  .sharp      ; (lines=99, args=1, vars=7, tmps=53)
  .sharp 0018 T8 = JMPZ_EX T8 0030
  .sharp 0027 T23 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp 0028 T24 = SHARP_IS_TYPE T22 T23
  .sharp 0029 T8 = BOOL T24
  .sharp 0030 T7 = IS_IDENTICAL T8 bool(true)
  .sharp 0031 JMPNZ T7 0078
  .sharp 0032 T25 = TYPE_CHECK (array) CV0($shapes)
  .sharp 0033 T25 = JMPZ_EX T25 0037
  .sharp 0034 T26 = COUNT CV0($shapes)
  .sharp 0035 T27 = IS_SMALLER_OR_EQUAL int(1) T26
  .sharp 0036 T25 = BOOL T27
  .sharp 0037 T25 = JMPZ_EX T25 0044
  .sharp 0038 T28 = COUNT CV0($shapes)
  .sharp 0039 T29 = SUB T28 int(1)
  .sharp 0040 T30 = FETCH_DIM_R CV0($shapes) T29
  .sharp 0041 T31 = ASSIGN CV4($last) T30
  .sharp 0042 T32 = INSTANCEOF T31 string("Demo\\Circle")
  .sharp 0043 T25 = BOOL T32
  .sharp 0044 T25 = JMPZ_EX T25 0056
  .sharp 0045 INIT_FCALL 3 128 string("array_slice")
  .sharp 0046 SEND_VAR CV0($shapes) 1
  .sharp 0047 SEND_VAL int(0) 2
  .sharp 0048 T33 = COUNT CV0($shapes)
  .sharp 0049 T34 = SUB T33 int(1)
  .sharp 0050 SEND_VAL T34 3
  .sharp 0051 V35 = DO_ICALL
  .sharp 0052 T36 = ASSIGN CV5($before) V35
  .sharp 0053 T37 = SHARP_TYPE_ARGS string("List<Demo.Figure>")
  .sharp 0054 T38 = SHARP_IS_TYPE T36 T37
  .sharp 0055 T25 = BOOL T38
  .sharp 0056 T7 = IS_IDENTICAL T25 bool(true)
  .sharp 0057 JMPNZ T7 0083
  .sharp 0058 T39 = TYPE_CHECK (array) CV0($shapes)
  .sharp 0059 T39 = JMPZ_EX T39 0063
  .sharp 0060 T40 = COUNT CV0($shapes)
  .sharp 0061 T41 = IS_SMALLER_OR_EQUAL int(2) T40
  .sharp 0062 T39 = BOOL T41
  .sharp 0063 T39 = JMPZ_EX T39 0068
  .sharp 0064 T42 = FETCH_DIM_R CV0($shapes) int(0)
  .sharp 0065 T43 = ASSIGN CV6($first) T42
  .sharp 0066 T44 = INSTANCEOF T43 string("Demo\\Square")
  .sharp 0067 T39 = BOOL T44
  .sharp 0068 T39 = JMPZ_EX T39 0075
  .sharp 0069 T45 = COUNT CV0($shapes)
  .sharp 0070 T46 = SUB T45 int(1)
  .sharp 0071 T47 = FETCH_DIM_R CV0($shapes) T46
  .sharp 0072 T48 = ASSIGN CV4($last) T47
  .sharp 0073 T49 = INSTANCEOF T48 string("Demo\\Square")
  .sharp 0074 T39 = BOOL T49
  .sharp 0075 T7 = IS_IDENTICAL T39 bool(true)
  .sharp 0076 JMPNZ T7 0088
  .sharp 0077 JMP 0093
  .sharp 0078 T50 = COUNT CV3($inner)
  .sharp 0079 T51 = CAST (double) T50
  .sharp 0080 T52 = MUL T51 int(-1)
  .sharp 0081 T53 = QM_ASSIGN T52
  .sharp 0082 JMP 0095
  .sharp 0083 T54 = FETCH_OBJ_R CV4($last) string("radius")
  .sharp 0084 T55 = COUNT CV5($before)
  .sharp 0085 T56 = ADD T54 T55
  .sharp 0086 T53 = QM_ASSIGN T56
  .sharp 0087 JMP 0095
  .sharp 0088 T57 = FETCH_OBJ_R CV6($first) string("side")
  .sharp 0089 T58 = FETCH_OBJ_R CV4($last) string("side")
  .sharp 0090 T59 = ADD T57 T58
  .sharp 0091 T53 = QM_ASSIGN T59
  .sharp 0092 JMP 0095
  .sharp 0093 T53 = QM_ASSIGN float(0)
  .sharp 0094 JMP 0095
  .sharp 0095 VERIFY_RETURN_TYPE T53
  .sharp 0096 RETURN T53
  .sharp 0097 VERIFY_RETURN_TYPE
  .sharp 0098 RETURN null
  .sharp      22: 0027 - 0028 (tmp/var)
  .sharp      36: 0053 - 0054 (tmp/var)
  .sharp      54: 0084 - 0085 (tmp/var)
  .sharp      57: 0089 - 0090 (tmp/var)
  .sharp      53: 0094 - 0096 (tmp/var)
  .sharp      ; (lines=32, args=1, vars=3, tmps=13)
  .sharp 0011 T4 = JMPZ_EX T4 0020
  .sharp 0017 T12 = SHARP_TYPE_ARGS string("List<Demo.Circle>")
  .sharp 0018 T13 = SHARP_IS_TYPE T11 T12
  .sharp 0019 T4 = BOOL T13
  .sharp 0020 T3 = IS_IDENTICAL T4 bool(true)
  .sharp 0021 JMPNZ T3 0023
  .sharp 0022 JMP 0026
  .sharp 0023 T14 = COUNT CV2($rest)
  .sharp 0024 T15 = QM_ASSIGN T14
  .sharp 0025 JMP 0028
  .sharp 0026 T15 = QM_ASSIGN int(-1)
  .sharp 0027 JMP 0028
  .sharp 0028 VERIFY_RETURN_TYPE T15
  .sharp 0029 RETURN T15
  .sharp 0030 VERIFY_RETURN_TYPE
  .sharp 0031 RETURN null
  .sharp      11: 0017 - 0018 (tmp/var)
  .sharp      15: 0027 - 0029 (tmp/var)
  .sharp      ; (lines=59, args=1, vars=4, tmps=26)
  .sharp 0001 T5 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp 0002 T6 = SHARP_IS_TYPE CV0($value) T5
  .sharp 0003 T6 = JMPZ_EX T6 0007
  .sharp 0004 T7 = COUNT CV0($value)
  .sharp 0005 T8 = IS_IDENTICAL T7 int(0)
  .sharp 0006 T6 = BOOL T8
  .sharp 0007 T4 = IS_IDENTICAL T6 bool(true)
  .sharp 0008 JMPNZ T4 0045
  .sharp 0009 T9 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp 0010 T10 = SHARP_IS_TYPE CV0($value) T9
  .sharp 0011 T10 = JMPZ_EX T10 0015
  .sharp 0012 T11 = COUNT CV0($value)
  .sharp 0013 T12 = IS_IDENTICAL T11 int(1)
  .sharp 0014 T10 = BOOL T12
  .sharp 0015 T10 = JMPZ_EX T10 0020
  .sharp 0016 T13 = FETCH_DIM_R CV0($value) int(0)
  .sharp 0017 T14 = ASSIGN CV1($one) T13
  .sharp 0018 T15 = TYPE_CHECK (long) T14
  .sharp 0019 T10 = BOOL T15
  .sharp 0020 T4 = IS_IDENTICAL T10 bool(true)
  .sharp 0021 JMPNZ T4 0047
  .sharp 0022 T16 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp 0023 T17 = SHARP_IS_TYPE CV0($value) T16
  .sharp 0024 T17 = JMPZ_EX T17 0028
  .sharp 0025 T18 = COUNT CV0($value)
  .sharp 0026 T19 = IS_SMALLER_OR_EQUAL int(1) T18
  .sharp 0027 T17 = BOOL T19
  .sharp 0028 T17 = JMPZ_EX T17 0033
  .sharp 0029 T20 = FETCH_DIM_R CV0($value) int(0)
  .sharp 0030 T21 = ASSIGN CV2($match#1) T20
  .sharp 0031 T22 = TYPE_CHECK (string) T21
  .sharp 0032 T17 = BOOL T22
  .sharp 0033 T17 = JMPZ_EX T17 0042
  .sharp 0034 INIT_FCALL 2 112 string("array_slice")
  .sharp 0035 SEND_VAR CV0($value) 1
  .sharp 0036 SEND_VAL int(1) 2
  .sharp 0037 V23 = DO_ICALL
  .sharp 0038 T24 = ASSIGN CV3($rest) V23
  .sharp 0039 T25 = SHARP_TYPE_ARGS string("List<string>")
  .sharp 0040 T26 = SHARP_IS_TYPE T24 T25
  .sharp 0041 T17 = BOOL T26
  .sharp 0042 T4 = IS_IDENTICAL T17 bool(true)
  .sharp 0043 JMPNZ T4 0049
  .sharp 0044 JMP 0053
  .sharp 0045 T27 = QM_ASSIGN int(0)
  .sharp 0046 JMP 0055
  .sharp 0047 T27 = QM_ASSIGN CV1($one)
  .sharp 0048 JMP 0055
  .sharp 0049 T28 = COUNT CV3($rest)
  .sharp 0050 T29 = ADD int(100) T28
  .sharp 0051 T27 = QM_ASSIGN T29
  .sharp 0052 JMP 0055
  .sharp 0053 T27 = QM_ASSIGN int(-1)
  .sharp 0054 JMP 0055
  .sharp 0055 VERIFY_RETURN_TYPE T27
  .sharp 0056 RETURN T27
  .sharp 0057 VERIFY_RETURN_TYPE
  .sharp 0058 RETURN null
  .sharp      24: 0039 - 0040 (tmp/var)
  .sharp      27: 0054 - 0056 (tmp/var)
  .sharp      ; (lines=35, args=1, vars=4, tmps=18)
  .sharp 0006 T4 = JMPZ_EX T4 0016
  .sharp 0009 T9 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp 0010 T10 = SHARP_IS_TYPE T8 T9
  .sharp 0011 T10 = JMPZ_EX T10 0015
  .sharp 0012 T11 = COUNT CV1($match#1)
  .sharp 0013 T12 = IS_IDENTICAL T11 int(0)
  .sharp 0014 T10 = BOOL T12
  .sharp 0015 T4 = BOOL T10
  .sharp 0016 T4 = JMPZ_EX T4 0031
  .sharp 0017 T13 = FETCH_DIM_R CV0($rows) int(1)
  .sharp 0018 T14 = ASSIGN CV2($match#2) T13
  .sharp 0019 T15 = SHARP_TYPE_ARGS string("List<Any?>")
  .sharp 0020 T16 = SHARP_IS_TYPE T14 T15
  .sharp 0021 T16 = JMPZ_EX T16 0025
  .sharp 0022 T17 = COUNT CV2($match#2)
  .sharp 0023 T18 = IS_IDENTICAL T17 int(1)
  .sharp 0024 T16 = BOOL T18
  .sharp 0025 T16 = JMPZ_EX T16 0030
  .sharp 0026 T19 = FETCH_DIM_R CV2($match#2) int(0)
  .sharp 0027 T20 = ASSIGN CV3($match#3) T19
  .sharp 0028 T21 = TYPE_CHECK (long) T20
  .sharp 0029 T16 = BOOL T21
  .sharp 0030 T4 = BOOL T16
  .sharp 0031 VERIFY_RETURN_TYPE T4
  .sharp 0032 RETURN T4
  .sharp 0033 VERIFY_RETURN_TYPE
  .sharp 0034 RETURN null
  .sharp      8: 0009 - 0010 (tmp/var)
  .sharp      14: 0019 - 0020 (tmp/var)
  .php        ; (lines=60, args=1, vars=5, tmps=25)
  .php   L0098 0007 JMPNZ T5 0046
  .php   L0099 0019 JMPNZ T5 0048
  .php   L0100 0035 T15 = JMPZ_EX T15 0043
  .php   L0100 0041 T26 = TYPE_CHECK (array) T25
  .php   L0100 0042 T15 = BOOL T26
  .php   L0100 0043 T5 = IS_IDENTICAL T15 bool(true)
  .php   L0100 0044 JMPNZ T5 0050
  .php   L0100 0045 JMP 0054
  .php   L0098 0046 T27 = QM_ASSIGN int(0)
  .php   L0098 0047 JMP 0056
  .php   L0099 0048 T27 = QM_ASSIGN int(1)
  .php   L0099 0049 JMP 0056
  .php   L0100 0050 T28 = COUNT CV4($rest)
  .php   L0100 0051 T29 = ADD int(2) T28
  .php   L0100 0052 T27 = QM_ASSIGN T29
  .php   L0100 0053 JMP 0056
  .php   L0101 0054 T27 = QM_ASSIGN int(-1)
  .php   L0101 0055 JMP 0056
  .php   L0101 0056 VERIFY_RETURN_TYPE T27
  .php   L0101 0057 RETURN T27
  .php   L0102 0058 VERIFY_RETURN_TYPE
  .php   L0102 0059 RETURN null
  .php        ; (lines=30, args=1, vars=3, tmps=12)
  .php   L0106 0011 T3 = JMPZ_EX T3 0019
  .php   L0106 0017 T11 = TYPE_CHECK (array) T10
  .php   L0106 0018 T3 = BOOL T11
  .php   L0106 0019 T3 = JMPZ_EX T3 0023
  .php   L0106 0020 T12 = COUNT CV2($others)
  .php   L0106 0021 T13 = IS_IDENTICAL T12 int(0)
  .php   L0106 0022 T3 = BOOL T13
  .php   L0106 0023 JMPZ T3 0027
  .php   L0107 0024 T14 = FETCH_OBJ_R CV1($first) string("radius")
  .php   L0107 0025 VERIFY_RETURN_TYPE T14
  .php   L0107 0026 RETURN T14
  .php   L0109 0027 RETURN float(0)
  .php   L0110 0028 VERIFY_RETURN_TYPE
  .php   L0110 0029 RETURN null
  .php        ; (lines=97, args=1, vars=7, tmps=51)
  .php   L0113 0018 T8 = JMPZ_EX T8 0029
  .php   L0113 0027 T23 = TYPE_CHECK (array) T22
  .php   L0113 0028 T8 = BOOL T23
  .php   L0113 0029 T7 = IS_IDENTICAL T8 bool(true)
  .php   L0113 0030 JMPNZ T7 0076
  .php   L0114 0031 T24 = TYPE_CHECK (array) CV0($shapes)
  .php   L0114 0032 T24 = JMPZ_EX T24 0036
  .php   L0114 0033 T25 = COUNT CV0($shapes)
  .php   L0114 0034 T26 = IS_SMALLER_OR_EQUAL int(1) T25
  .php   L0114 0035 T24 = BOOL T26
  .php   L0114 0036 T24 = JMPZ_EX T24 0043
  .php   L0114 0037 T27 = COUNT CV0($shapes)
  .php   L0114 0038 T28 = SUB T27 int(1)
  .php   L0114 0039 T29 = FETCH_DIM_R CV0($shapes) T28
  .php   L0114 0040 T30 = ASSIGN CV4($last) T29
  .php   L0114 0041 T31 = INSTANCEOF T30 string("Demo\\Circle")
  .php   L0114 0042 T24 = BOOL T31
  .php   L0114 0043 T24 = JMPZ_EX T24 0054
  .php   L0114 0044 INIT_FCALL 3 128 string("array_slice")
  .php   L0114 0045 SEND_VAR CV0($shapes) 1
  .php   L0114 0046 SEND_VAL int(0) 2
  .php   L0114 0047 T32 = COUNT CV0($shapes)
  .php   L0114 0048 T33 = SUB T32 int(1)
  .php   L0114 0049 SEND_VAL T33 3
  .php   L0114 0050 V34 = DO_ICALL
  .php   L0114 0051 T35 = ASSIGN CV5($before) V34
  .php   L0114 0052 T36 = TYPE_CHECK (array) T35
  .php   L0114 0053 T24 = BOOL T36
  .php   L0114 0054 T7 = IS_IDENTICAL T24 bool(true)
  .php   L0114 0055 JMPNZ T7 0081
  .php   L0115 0056 T37 = TYPE_CHECK (array) CV0($shapes)
  .php   L0115 0057 T37 = JMPZ_EX T37 0061
  .php   L0115 0058 T38 = COUNT CV0($shapes)
  .php   L0115 0059 T39 = IS_SMALLER_OR_EQUAL int(2) T38
  .php   L0115 0060 T37 = BOOL T39
  .php   L0115 0061 T37 = JMPZ_EX T37 0066
  .php   L0115 0062 T40 = FETCH_DIM_R CV0($shapes) int(0)
  .php   L0115 0063 T41 = ASSIGN CV6($first) T40
  .php   L0115 0064 T42 = INSTANCEOF T41 string("Demo\\Square")
  .php   L0115 0065 T37 = BOOL T42
  .php   L0115 0066 T37 = JMPZ_EX T37 0073
  .php   L0115 0067 T43 = COUNT CV0($shapes)
  .php   L0115 0068 T44 = SUB T43 int(1)
  .php   L0115 0069 T45 = FETCH_DIM_R CV0($shapes) T44
  .php   L0115 0070 T46 = ASSIGN CV4($last) T45
  .php   L0115 0071 T47 = INSTANCEOF T46 string("Demo\\Square")
  .php   L0115 0072 T37 = BOOL T47
  .php   L0115 0073 T7 = IS_IDENTICAL T37 bool(true)
  .php   L0115 0074 JMPNZ T7 0086
  .php   L0115 0075 JMP 0091
  .php   L0113 0076 T48 = COUNT CV3($inner)
  .php   L0113 0077 T49 = CAST (double) T48
  .php   L0113 0078 T50 = MUL T49 int(-1)
  .php   L0113 0079 T51 = QM_ASSIGN T50
  .php   L0113 0080 JMP 0093
  .php   L0114 0081 T52 = FETCH_OBJ_R CV4($last) string("radius")
  .php   L0114 0082 T53 = COUNT CV5($before)
  .php   L0114 0083 T54 = ADD T52 T53
  .php   L0114 0084 T51 = QM_ASSIGN T54
  .php   L0114 0085 JMP 0093
  .php   L0115 0086 T55 = FETCH_OBJ_R CV6($first) string("side")
  .php   L0115 0087 T56 = FETCH_OBJ_R CV4($last) string("side")
  .php   L0115 0088 T57 = ADD T55 T56
  .php   L0115 0089 T51 = QM_ASSIGN T57
  .php   L0115 0090 JMP 0093
  .php   L0116 0091 T51 = QM_ASSIGN float(0)
  .php   L0116 0092 JMP 0093
  .php   L0116 0093 VERIFY_RETURN_TYPE T51
  .php   L0116 0094 RETURN T51
  .php   L0117 0095 VERIFY_RETURN_TYPE
  .php   L0117 0096 RETURN null
  .php        ; (lines=31, args=1, vars=3, tmps=12)
  .php   L0120 0011 T4 = JMPZ_EX T4 0019
  .php   L0120 0017 T12 = TYPE_CHECK (array) T11
  .php   L0120 0018 T4 = BOOL T12
  .php   L0120 0019 T3 = IS_IDENTICAL T4 bool(true)
  .php   L0120 0020 JMPNZ T3 0022
  .php   L0120 0021 JMP 0025
  .php   L0120 0022 T13 = COUNT CV2($rest)
  .php   L0120 0023 T14 = QM_ASSIGN T13
  .php   L0120 0024 JMP 0027
  .php   L0121 0025 T14 = QM_ASSIGN int(-1)
  .php   L0121 0026 JMP 0027
  .php   L0121 0027 VERIFY_RETURN_TYPE T14
  .php   L0121 0028 RETURN T14
  .php   L0122 0029 VERIFY_RETURN_TYPE
  .php   L0122 0030 RETURN null
  .php        ; (lines=55, args=1, vars=4, tmps=22)
  .php   L0125 0001 T5 = TYPE_CHECK (array) CV0($value)
  .php   L0125 0002 T5 = JMPZ_EX T5 0006
  .php   L0125 0003 T6 = COUNT CV0($value)
  .php   L0125 0004 T7 = IS_IDENTICAL T6 int(0)
  .php   L0125 0005 T5 = BOOL T7
  .php   L0125 0006 T4 = IS_IDENTICAL T5 bool(true)
  .php   L0125 0007 JMPNZ T4 0041
  .php   L0126 0008 T8 = TYPE_CHECK (array) CV0($value)
  .php   L0126 0009 T8 = JMPZ_EX T8 0013
  .php   L0126 0010 T9 = COUNT CV0($value)
  .php   L0126 0011 T10 = IS_IDENTICAL T9 int(1)
  .php   L0126 0012 T8 = BOOL T10
  .php   L0126 0013 T8 = JMPZ_EX T8 0018
  .php   L0126 0014 T11 = FETCH_DIM_R CV0($value) int(0)
  .php   L0126 0015 T12 = ASSIGN CV1($one) T11
  .php   L0126 0016 T13 = TYPE_CHECK (long) T12
  .php   L0126 0017 T8 = BOOL T13
  .php   L0126 0018 T4 = IS_IDENTICAL T8 bool(true)
  .php   L0126 0019 JMPNZ T4 0043
  .php   L0127 0020 T14 = TYPE_CHECK (array) CV0($value)
  .php   L0127 0021 T14 = JMPZ_EX T14 0025
  .php   L0127 0022 T15 = COUNT CV0($value)
  .php   L0127 0023 T16 = IS_SMALLER_OR_EQUAL int(1) T15
  .php   L0127 0024 T14 = BOOL T16
  .php   L0127 0025 T14 = JMPZ_EX T14 0030
  .php   L0127 0026 T17 = FETCH_DIM_R CV0($value) int(0)
  .php   L0127 0027 T18 = ASSIGN CV2($match#1) T17
  .php   L0127 0028 T19 = TYPE_CHECK (string) T18
  .php   L0127 0029 T14 = BOOL T19
  .php   L0127 0030 T14 = JMPZ_EX T14 0038
  .php   L0127 0031 INIT_FCALL 2 112 string("array_slice")
  .php   L0127 0032 SEND_VAR CV0($value) 1
  .php   L0127 0033 SEND_VAL int(1) 2
  .php   L0127 0034 V20 = DO_ICALL
  .php   L0127 0035 T21 = ASSIGN CV3($rest) V20
  .php   L0127 0036 T22 = TYPE_CHECK (array) T21
  .php   L0127 0037 T14 = BOOL T22
  .php   L0127 0038 T4 = IS_IDENTICAL T14 bool(true)
  .php   L0127 0039 JMPNZ T4 0045
  .php   L0127 0040 JMP 0049
  .php   L0125 0041 T23 = QM_ASSIGN int(0)
  .php   L0125 0042 JMP 0051
  .php   L0126 0043 T23 = QM_ASSIGN CV1($one)
  .php   L0126 0044 JMP 0051
  .php   L0127 0045 T24 = COUNT CV3($rest)
  .php   L0127 0046 T25 = ADD int(100) T24
  .php   L0127 0047 T23 = QM_ASSIGN T25
  .php   L0127 0048 JMP 0051
  .php   L0128 0049 T23 = QM_ASSIGN int(-1)
  .php   L0128 0050 JMP 0051
  .php   L0128 0051 VERIFY_RETURN_TYPE T23
  .php   L0128 0052 RETURN T23
  .php   L0129 0053 VERIFY_RETURN_TYPE
  .php   L0129 0054 RETURN null
  .php        ; (lines=33, args=1, vars=4, tmps=16)
  .php   L0131 0006 T4 = JMPZ_EX T4 0015
  .php   L0131 0009 T9 = TYPE_CHECK (array) T8
  .php   L0131 0010 T9 = JMPZ_EX T9 0014
  .php   L0131 0011 T10 = COUNT CV1($match#1)
  .php   L0131 0012 T11 = IS_IDENTICAL T10 int(0)
  .php   L0131 0013 T9 = BOOL T11
  .php   L0131 0014 T4 = BOOL T9
  .php   L0131 0015 T4 = JMPZ_EX T4 0029
  .php   L0131 0016 T12 = FETCH_DIM_R CV0($rows) int(1)
  .php   L0131 0017 T13 = ASSIGN CV2($match#2) T12
  .php   L0131 0018 T14 = TYPE_CHECK (array) T13
  .php   L0131 0019 T14 = JMPZ_EX T14 0023
  .php   L0131 0020 T15 = COUNT CV2($match#2)
  .php   L0131 0021 T16 = IS_IDENTICAL T15 int(1)
  .php   L0131 0022 T14 = BOOL T16
  .php   L0131 0023 T14 = JMPZ_EX T14 0028
  .php   L0131 0024 T17 = FETCH_DIM_R CV2($match#2) int(0)
  .php   L0131 0025 T18 = ASSIGN CV3($match#3) T17
  .php   L0131 0026 T19 = TYPE_CHECK (long) T18
  .php   L0131 0027 T14 = BOOL T19
  .php   L0131 0028 T4 = BOOL T14
  .php   L0131 0029 VERIFY_RETURN_TYPE T4
  .php   L0131 0030 RETURN T4
  .php   L0131 0031 VERIFY_RETURN_TYPE
  .php   L0131 0032 RETURN null
  .php        ; (lines=60, args=1, vars=5, tmps=25)
  .php   0007 JMPNZ T5 0046
  .php   0019 JMPNZ T5 0048
  .php   0035 T15 = JMPZ_EX T15 0043
  .php   0041 T26 = TYPE_CHECK (array) T25
  .php   0042 T15 = BOOL T26
  .php   0043 T5 = IS_IDENTICAL T15 bool(true)
  .php   0044 JMPNZ T5 0050
  .php   0045 JMP 0054
  .php   0046 T27 = QM_ASSIGN int(0)
  .php   0047 JMP 0056
  .php   0048 T27 = QM_ASSIGN int(1)
  .php   0049 JMP 0056
  .php   0050 T28 = COUNT CV4($rest)
  .php   0051 T29 = ADD int(2) T28
  .php   0052 T27 = QM_ASSIGN T29
  .php   0053 JMP 0056
  .php   0054 T27 = QM_ASSIGN int(-1)
  .php   0055 JMP 0056
  .php   0056 VERIFY_RETURN_TYPE T27
  .php   0057 RETURN T27
  .php   0058 VERIFY_RETURN_TYPE
  .php   0059 RETURN null
  .php        27: 0055 - 0057 (tmp/var)
  .php        ; (lines=30, args=1, vars=3, tmps=12)
  .php   0011 T3 = JMPZ_EX T3 0019
  .php   0017 T11 = TYPE_CHECK (array) T10
  .php   0018 T3 = BOOL T11
  .php   0019 T3 = JMPZ_EX T3 0023
  .php   0020 T12 = COUNT CV2($others)
  .php   0021 T13 = IS_IDENTICAL T12 int(0)
  .php   0022 T3 = BOOL T13
  .php   0023 JMPZ T3 0027
  .php   0024 T14 = FETCH_OBJ_R CV1($first) string("radius")
  .php   0025 VERIFY_RETURN_TYPE T14
  .php   0026 RETURN T14
  .php   0027 RETURN float(0)
  .php   0028 VERIFY_RETURN_TYPE
  .php   0029 RETURN null
  .php        14: 0025 - 0026 (tmp/var)
  .php        ; (lines=97, args=1, vars=7, tmps=51)
  .php   0018 T8 = JMPZ_EX T8 0029
  .php   0027 T23 = TYPE_CHECK (array) T22
  .php   0028 T8 = BOOL T23
  .php   0029 T7 = IS_IDENTICAL T8 bool(true)
  .php   0030 JMPNZ T7 0076
  .php   0031 T24 = TYPE_CHECK (array) CV0($shapes)
  .php   0032 T24 = JMPZ_EX T24 0036
  .php   0033 T25 = COUNT CV0($shapes)
  .php   0034 T26 = IS_SMALLER_OR_EQUAL int(1) T25
  .php   0035 T24 = BOOL T26
  .php   0036 T24 = JMPZ_EX T24 0043
  .php   0037 T27 = COUNT CV0($shapes)
  .php   0038 T28 = SUB T27 int(1)
  .php   0039 T29 = FETCH_DIM_R CV0($shapes) T28
  .php   0040 T30 = ASSIGN CV4($last) T29
  .php   0041 T31 = INSTANCEOF T30 string("Demo\\Circle")
  .php   0042 T24 = BOOL T31
  .php   0043 T24 = JMPZ_EX T24 0054
  .php   0044 INIT_FCALL 3 128 string("array_slice")
  .php   0045 SEND_VAR CV0($shapes) 1
  .php   0046 SEND_VAL int(0) 2
  .php   0047 T32 = COUNT CV0($shapes)
  .php   0048 T33 = SUB T32 int(1)
  .php   0049 SEND_VAL T33 3
  .php   0050 V34 = DO_ICALL
  .php   0051 T35 = ASSIGN CV5($before) V34
  .php   0052 T36 = TYPE_CHECK (array) T35
  .php   0053 T24 = BOOL T36
  .php   0054 T7 = IS_IDENTICAL T24 bool(true)
  .php   0055 JMPNZ T7 0081
  .php   0056 T37 = TYPE_CHECK (array) CV0($shapes)
  .php   0057 T37 = JMPZ_EX T37 0061
  .php   0058 T38 = COUNT CV0($shapes)
  .php   0059 T39 = IS_SMALLER_OR_EQUAL int(2) T38
  .php   0060 T37 = BOOL T39
  .php   0061 T37 = JMPZ_EX T37 0066
  .php   0062 T40 = FETCH_DIM_R CV0($shapes) int(0)
  .php   0063 T41 = ASSIGN CV6($first) T40
  .php   0064 T42 = INSTANCEOF T41 string("Demo\\Square")
  .php   0065 T37 = BOOL T42
  .php   0066 T37 = JMPZ_EX T37 0073
  .php   0067 T43 = COUNT CV0($shapes)
  .php   0068 T44 = SUB T43 int(1)
  .php   0069 T45 = FETCH_DIM_R CV0($shapes) T44
  .php   0070 T46 = ASSIGN CV4($last) T45
  .php   0071 T47 = INSTANCEOF T46 string("Demo\\Square")
  .php   0072 T37 = BOOL T47
  .php   0073 T7 = IS_IDENTICAL T37 bool(true)
  .php   0074 JMPNZ T7 0086
  .php   0075 JMP 0091
  .php   0076 T48 = COUNT CV3($inner)
  .php   0077 T49 = CAST (double) T48
  .php   0078 T50 = MUL T49 int(-1)
  .php   0079 T51 = QM_ASSIGN T50
  .php   0080 JMP 0093
  .php   0081 T52 = FETCH_OBJ_R CV4($last) string("radius")
  .php   0082 T53 = COUNT CV5($before)
  .php   0083 T54 = ADD T52 T53
  .php   0084 T51 = QM_ASSIGN T54
  .php   0085 JMP 0093
  .php   0086 T55 = FETCH_OBJ_R CV6($first) string("side")
  .php   0087 T56 = FETCH_OBJ_R CV4($last) string("side")
  .php   0088 T57 = ADD T55 T56
  .php   0089 T51 = QM_ASSIGN T57
  .php   0090 JMP 0093
  .php   0091 T51 = QM_ASSIGN float(0)
  .php   0092 JMP 0093
  .php   0093 VERIFY_RETURN_TYPE T51
  .php   0094 RETURN T51
  .php   0095 VERIFY_RETURN_TYPE
  .php   0096 RETURN null
  .php        52: 0082 - 0083 (tmp/var)
  .php        55: 0087 - 0088 (tmp/var)
  .php        51: 0092 - 0094 (tmp/var)
  .php        ; (lines=31, args=1, vars=3, tmps=12)
  .php   0011 T4 = JMPZ_EX T4 0019
  .php   0017 T12 = TYPE_CHECK (array) T11
  .php   0018 T4 = BOOL T12
  .php   0019 T3 = IS_IDENTICAL T4 bool(true)
  .php   0020 JMPNZ T3 0022
  .php   0021 JMP 0025
  .php   0022 T13 = COUNT CV2($rest)
  .php   0023 T14 = QM_ASSIGN T13
  .php   0024 JMP 0027
  .php   0025 T14 = QM_ASSIGN int(-1)
  .php   0026 JMP 0027
  .php   0027 VERIFY_RETURN_TYPE T14
  .php   0028 RETURN T14
  .php        14: 0026 - 0028 (tmp/var)
  .php        ; (lines=55, args=1, vars=4, tmps=22)
  .php   0001 T5 = TYPE_CHECK (array) CV0($value)
  .php   0002 T5 = JMPZ_EX T5 0006
  .php   0003 T6 = COUNT CV0($value)
  .php   0004 T7 = IS_IDENTICAL T6 int(0)
  .php   0005 T5 = BOOL T7
  .php   0006 T4 = IS_IDENTICAL T5 bool(true)
  .php   0007 JMPNZ T4 0041
  .php   0008 T8 = TYPE_CHECK (array) CV0($value)
  .php   0009 T8 = JMPZ_EX T8 0013
  .php   0010 T9 = COUNT CV0($value)
  .php   0011 T10 = IS_IDENTICAL T9 int(1)
  .php   0012 T8 = BOOL T10
  .php   0013 T8 = JMPZ_EX T8 0018
  .php   0014 T11 = FETCH_DIM_R CV0($value) int(0)
  .php   0015 T12 = ASSIGN CV1($one) T11
  .php   0016 T13 = TYPE_CHECK (long) T12
  .php   0017 T8 = BOOL T13
  .php   0018 T4 = IS_IDENTICAL T8 bool(true)
  .php   0019 JMPNZ T4 0043
  .php   0020 T14 = TYPE_CHECK (array) CV0($value)
  .php   0021 T14 = JMPZ_EX T14 0025
  .php   0022 T15 = COUNT CV0($value)
  .php   0023 T16 = IS_SMALLER_OR_EQUAL int(1) T15
  .php   0024 T14 = BOOL T16
  .php   0025 T14 = JMPZ_EX T14 0030
  .php   0026 T17 = FETCH_DIM_R CV0($value) int(0)
  .php   0027 T18 = ASSIGN CV2($match#1) T17
  .php   0028 T19 = TYPE_CHECK (string) T18
  .php   0029 T14 = BOOL T19
  .php   0030 T14 = JMPZ_EX T14 0038
  .php   0031 INIT_FCALL 2 112 string("array_slice")
  .php   0032 SEND_VAR CV0($value) 1
  .php   0033 SEND_VAL int(1) 2
  .php   0034 V20 = DO_ICALL
  .php   0035 T21 = ASSIGN CV3($rest) V20
  .php   0036 T22 = TYPE_CHECK (array) T21
  .php   0037 T14 = BOOL T22
  .php   0038 T4 = IS_IDENTICAL T14 bool(true)
  .php   0039 JMPNZ T4 0045
  .php   0040 JMP 0049
  .php   0041 T23 = QM_ASSIGN int(0)
  .php   0042 JMP 0051
  .php   0043 T23 = QM_ASSIGN CV1($one)
  .php   0044 JMP 0051
  .php   0045 T24 = COUNT CV3($rest)
  .php   0046 T25 = ADD int(100) T24
  .php   0047 T23 = QM_ASSIGN T25
  .php   0048 JMP 0051
  .php   0049 T23 = QM_ASSIGN int(-1)
  .php   0050 JMP 0051
  .php   0051 VERIFY_RETURN_TYPE T23
  .php   0052 RETURN T23
  .php   0053 VERIFY_RETURN_TYPE
  .php   0054 RETURN null
  .php        23: 0050 - 0052 (tmp/var)
  .php        ; (lines=33, args=1, vars=4, tmps=16)
  .php   0006 T4 = JMPZ_EX T4 0015
  .php   0009 T9 = TYPE_CHECK (array) T8
  .php   0010 T9 = JMPZ_EX T9 0014
  .php   0011 T10 = COUNT CV1($match#1)
  .php   0012 T11 = IS_IDENTICAL T10 int(0)
  .php   0013 T9 = BOOL T11
  .php   0014 T4 = BOOL T9
  .php   0015 T4 = JMPZ_EX T4 0029
  .php   0016 T12 = FETCH_DIM_R CV0($rows) int(1)
  .php   0017 T13 = ASSIGN CV2($match#2) T12
  .php   0018 T14 = TYPE_CHECK (array) T13
  .php   0019 T14 = JMPZ_EX T14 0023
  .php   0020 T15 = COUNT CV2($match#2)
  .php   0021 T16 = IS_IDENTICAL T15 int(1)
  .php   0022 T14 = BOOL T16
  .php   0023 T14 = JMPZ_EX T14 0028
  .php   0024 T17 = FETCH_DIM_R CV2($match#2) int(0)
  .php   0025 T18 = ASSIGN CV3($match#3) T17
  .php   0026 T19 = TYPE_CHECK (long) T18
  .php   0027 T14 = BOOL T19
  .php   0028 T4 = BOOL T14
  .php   0029 VERIFY_RETURN_TYPE T4
  .php   0030 RETURN T4
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
Signatures: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Accessors: same opcodes and lines in 15 op arrays, same signatures in 2 classes
Parcel: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Exits: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Positions: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Deploy: same opcodes and lines in 7 op arrays, same signatures in 1 classes
Tags: same opcodes and lines in 2 op arrays, same signatures in 1 classes
  check  Demo\Tags::of string("List<string>|string")
PhpForms: same opcodes and lines in 15 op arrays, same signatures in 1 classes
  check  Demo\PhpForms::tags string("List<string>|string")
Generics: different opcodes and lines in 33 op arrays, same signatures in 10 classes
  check  Paging\OrderQuery::__construct string("List<Paging.Order>")
  check  Paging\PaginatedList::__construct string("List<$0>")
  check  Paging\Feed::__construct string("List<$0>")
  check  Paging\Validator::validate string("$0")
  check  Paging\Catalog::newest string("Paging.Feed<Paging.Order>")
  check  Paging\Catalog::check string("Paging.Validator<Paging.DatabaseEntity>, Any?")
  .sharp      ; (lines=14, args=1, vars=2, tmps=6)
  .sharp L0055 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0055 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0055 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp L0055 0004 V4 = NEW 1 V2 T3
  .sharp L0055 0005 T5 = CAST (object) CV0($query)
  .sharp L0055 0006 INIT_METHOD_CALL 0 T5 string("rows")
  .sharp L0055 0007 V6 = DO_FCALL
  .sharp L0055 0008 SEND_VAR_NO_REF_EX V6 1
  .sharp L0055 0009 DO_FCALL
  .sharp L0055 0010 VERIFY_RETURN_TYPE V4
  .sharp L0055 0011 RETURN V4
  .sharp L0055 0012 VERIFY_RETURN_TYPE
  .sharp L0055 0013 RETURN null
  .sharp      ; (lines=16, args=2, vars=4, tmps=3)
  .sharp L0089 0002 CV2($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0091 0003 V4 = FE_RESET_R CV0($items) 0012
  .sharp L0091 0004 FE_FETCH_R V4 CV3($item) 0012
  .sharp L0092 0005 T5 = FETCH_OBJ_R CV3($item) string("id")
  .sharp L0092 0006 T6 = IS_IDENTICAL CV1($id) T5
  .sharp L0092 0007 JMPZ T6 0011
  .sharp L0093 0008 VERIFY_RETURN_TYPE CV3($item)
  .sharp L0093 0009 FE_FREE V4 loop-end(+3)
  .sharp L0093 0010 RETURN CV3($item)
  .sharp L0091 0011 JMP 0004
  .sharp L0091 0012 FE_FREE V4
  .sharp L0096 0013 RETURN null
  .sharp L0097 0014 VERIFY_RETURN_TYPE
  .sharp L0097 0015 RETURN null
  .sharp      ; (lines=6, args=1, vars=2, tmps=1)
  .sharp L0102 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Any?")
  .sharp L0102 0002 T2 = FETCH_DIM_R CV0($items) int(0)
  .sharp L0102 0003 RETURN T2
  .sharp L0102 0004 VERIFY_RETURN_TYPE
  .sharp L0102 0005 RETURN null
  .sharp      ; (lines=9, args=1, vars=2, tmps=2)
  .sharp L0106 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp L0106 0002 T2 = CAST (object) CV0($item)
  .sharp L0106 0003 INIT_METHOD_CALL 0 T2 string("link")
  .sharp L0106 0004 V3 = DO_FCALL
  .sharp L0106 0005 VERIFY_RETURN_TYPE V3
  .sharp L0106 0006 RETURN V3
  .sharp L0106 0007 VERIFY_RETURN_TYPE
  .sharp L0106 0008 RETURN null
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp L0120 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0120 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0120 0002 V2 = NEW 1 V0 T1
  .sharp L0120 0003 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp L0120 0004 V3 = DO_FCALL
  .sharp L0120 0005 SEND_VAR_NO_REF_EX V3 1
  .sharp L0120 0006 DO_FCALL
  .sharp L0120 0007 T5 = CAST (object) V2
  .sharp L0120 0008 INIT_METHOD_CALL 0 T5 string("first")
  .sharp L0120 0009 V6 = DO_FCALL
  .sharp L0120 0010 T7 = FETCH_OBJ_R V6 string("id")
  .sharp L0120 0011 VERIFY_RETURN_TYPE T7
  .sharp L0120 0012 RETURN T7
  .sharp L0120 0013 VERIFY_RETURN_TYPE
  .sharp L0120 0014 RETURN null
  .sharp      ; (lines=20, args=0, vars=0, tmps=11)
  .sharp L0122 0010 T6 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0122 0011 V7 = DO_FCALL T6
  .sharp L0122 0012 T8 = CAST (object) V7
  .sharp L0122 0013 INIT_METHOD_CALL 0 T8 string("first")
  .sharp L0122 0014 V9 = DO_FCALL
  .sharp L0122 0015 T10 = FETCH_OBJ_R V9 string("id")
  .sharp L0122 0016 VERIFY_RETURN_TYPE T10
  .sharp L0122 0017 RETURN T10
  .sharp L0122 0018 VERIFY_RETURN_TYPE
  .sharp L0122 0019 RETURN null
  .sharp      ; (lines=15, args=0, vars=0, tmps=5)
  .sharp L0124 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0124 0006 V2 = DO_FCALL T1
  .sharp L0124 0007 T3 = JMP_NULL V2 0009
  .sharp L0124 0008 T3 = FETCH_OBJ_IS V2 string("id")
  .sharp L0124 0009 T4 = COALESCE T3 0011
  .sharp L0124 0010 T4 = QM_ASSIGN int(0)
  .sharp L0124 0011 VERIFY_RETURN_TYPE T4
  .sharp L0124 0012 RETURN T4
  .sharp L0124 0013 VERIFY_RETURN_TYPE
  .sharp L0124 0014 RETURN null
  .sharp      ; (lines=15, args=0, vars=0, tmps=5)
  .sharp L0126 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0126 0006 V2 = DO_FCALL T1
  .sharp L0126 0007 T3 = JMP_NULL V2 0009
  .sharp L0126 0008 T3 = FETCH_OBJ_IS V2 string("id")
  .sharp L0126 0009 T4 = COALESCE T3 0011
  .sharp L0126 0010 T4 = QM_ASSIGN int(0)
  .sharp L0126 0011 VERIFY_RETURN_TYPE T4
  .sharp L0126 0012 RETURN T4
  .sharp L0126 0013 VERIFY_RETURN_TYPE
  .sharp L0126 0014 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=6)
  .sharp L0132 0001 V0 = FETCH_CLASS (exception) string("Paging\\Feed")
  .sharp L0132 0002 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0132 0003 V2 = NEW 1 V0 T1
  .sharp L0132 0004 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp L0132 0005 V3 = DO_FCALL
  .sharp L0132 0006 SEND_VAR_NO_REF_EX V3 1
  .sharp L0132 0007 DO_FCALL
  .sharp L0132 0008 SEND_VAR V2 1
  .sharp L0132 0009 V5 = DO_FCALL
  .sharp L0132 0010 VERIFY_RETURN_TYPE V5
  .sharp L0132 0011 RETURN V5
  .sharp L0132 0012 VERIFY_RETURN_TYPE
  .sharp L0132 0013 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp L0138 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0138 0008 V5 = DO_FCALL T4
  .sharp L0138 0009 T6 = FETCH_OBJ_R V5 string("id")
  .sharp L0138 0010 VERIFY_RETURN_TYPE T6
  .sharp L0138 0011 RETURN T6
  .sharp L0138 0012 VERIFY_RETURN_TYPE
  .sharp L0138 0013 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp L0140 0008 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0140 0009 V6 = DO_FCALL T5
  .sharp L0140 0010 VERIFY_RETURN_TYPE V6
  .sharp L0140 0011 RETURN V6
  .sharp L0140 0012 VERIFY_RETURN_TYPE
  .sharp L0140 0013 RETURN null
  .sharp      ; (lines=14, args=1, vars=2, tmps=6)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp 0004 V4 = NEW 1 V2 T3
  .sharp 0005 T5 = CAST (object) CV0($query)
  .sharp 0006 INIT_METHOD_CALL 0 T5 string("rows")
  .sharp 0007 V6 = DO_FCALL
  .sharp 0008 SEND_VAR_NO_REF_EX V6 1
  .sharp 0009 DO_FCALL
  .sharp 0010 VERIFY_RETURN_TYPE V4
  .sharp 0011 RETURN V4
  .sharp      4: 0005 - 0010 (new)
  .sharp      4: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=16, args=2, vars=4, tmps=3)
  .sharp 0002 CV2($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp 0003 V4 = FE_RESET_R CV0($items) 0012
  .sharp 0004 FE_FETCH_R V4 CV3($item) 0012
  .sharp 0005 T5 = FETCH_OBJ_R CV3($item) string("id")
  .sharp 0006 T6 = IS_IDENTICAL CV1($id) T5
  .sharp 0007 JMPZ T6 0011
  .sharp 0008 VERIFY_RETURN_TYPE CV3($item)
  .sharp 0009 FE_FREE V4 loop-end(+3)
  .sharp 0010 RETURN CV3($item)
  .sharp 0011 JMP 0004
  .sharp 0012 FE_FREE V4
  .sharp 0014 VERIFY_RETURN_TYPE
  .sharp 0015 RETURN null
  .sharp      4: 0004 - 0009 (loop)
  .sharp      4: 0011 - 0012 (loop)
  .sharp      ; (lines=6, args=1, vars=2, tmps=1)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Any?")
  .sharp 0002 T2 = FETCH_DIM_R CV0($items) int(0)
  .sharp 0003 RETURN T2
  .sharp      ; (lines=9, args=1, vars=2, tmps=2)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp 0002 T2 = CAST (object) CV0($item)
  .sharp 0003 INIT_METHOD_CALL 0 T2 string("link")
  .sharp 0005 VERIFY_RETURN_TYPE V3
  .sharp 0006 RETURN V3
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0002 V2 = NEW 1 V0 T1
  .sharp 0003 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp 0004 V3 = DO_UCALL
  .sharp 0007 T5 = CAST (object) V2
  .sharp 0008 INIT_METHOD_CALL 0 T5 string("first")
  .sharp 0009 V6 = DO_FCALL
  .sharp 0010 T7 = FETCH_OBJ_R V6 string("id")
  .sharp 0011 VERIFY_RETURN_TYPE T7
  .sharp 0012 RETURN T7
  .sharp      2: 0003 - 0007 (new)
  .sharp      7: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=20, args=0, vars=0, tmps=11)
  .sharp 0010 T6 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0011 V7 = DO_FCALL T6
  .sharp 0012 T8 = CAST (object) V7
  .sharp 0013 INIT_METHOD_CALL 0 T8 string("first")
  .sharp 0014 V9 = DO_FCALL
  .sharp 0015 T10 = FETCH_OBJ_R V9 string("id")
  .sharp 0016 VERIFY_RETURN_TYPE T10
  .sharp 0017 RETURN T10
  .sharp 0018 VERIFY_RETURN_TYPE
  .sharp 0019 RETURN null
  .sharp      10: 0016 - 0017 (tmp/var)
  .sharp      ; (lines=15, args=0, vars=0, tmps=5)
  .sharp 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0006 V2 = DO_UCALL T1
  .sharp 0007 T3 = JMP_NULL V2 0009
  .sharp 0008 T3 = FETCH_OBJ_IS V2 string("id")
  .sharp 0009 T4 = COALESCE T3 0011
  .sharp 0010 T4 = QM_ASSIGN int(0)
  .sharp 0011 VERIFY_RETURN_TYPE T4
  .sharp 0012 RETURN T4
  .sharp      2: 0007 - 0008 (tmp/var)
  .sharp      4: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=15, args=0, vars=0, tmps=5)
  .sharp 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0006 V2 = DO_UCALL T1
  .sharp 0007 T3 = JMP_NULL V2 0009
  .sharp 0008 T3 = FETCH_OBJ_IS V2 string("id")
  .sharp 0009 T4 = COALESCE T3 0011
  .sharp 0010 T4 = QM_ASSIGN int(0)
  .sharp 0011 VERIFY_RETURN_TYPE T4
  .sharp 0012 RETURN T4
  .sharp      2: 0007 - 0008 (tmp/var)
  .sharp      4: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=14, args=0, vars=0, tmps=6)
  .sharp 0001 V0 = FETCH_CLASS (exception) string("Paging\\Feed")
  .sharp 0002 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0003 V2 = NEW 1 V0 T1
  .sharp 0007 DO_FCALL
  .sharp 0008 SEND_VAR V2 1
  .sharp 0009 V5 = DO_UCALL
  .sharp 0010 VERIFY_RETURN_TYPE V5
  .sharp 0011 RETURN V5
  .sharp      2: 0004 - 0008 (new)
  .sharp      5: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0008 V5 = DO_FCALL T4
  .sharp 0009 T6 = FETCH_OBJ_R V5 string("id")
  .sharp 0010 VERIFY_RETURN_TYPE T6
  .sharp 0011 RETURN T6
  .sharp      6: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp 0008 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0009 V6 = DO_FCALL T5
  .sharp 0010 VERIFY_RETURN_TYPE V6
  .sharp 0011 RETURN V6
  .sharp      6: 0010 - 0011 (tmp/var)
  .sharp     Property [ private Sharp\Map $saved = new \Sharp\Map() ]
  .sharp     Property [ private Sharp\Map $kinds = new \Sharp\Map([0 => 'order', 1 => 'Paging\\Order']) ]
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   L0055 0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   L0055 0002 T2 = CAST (object) CV0($query)
  .php   L0055 0003 INIT_METHOD_CALL 0 T2 string("rows")
  .php   L0055 0004 V3 = DO_FCALL
  .php   L0055 0005 SEND_VAR_NO_REF_EX V3 1
  .php   L0055 0006 DO_FCALL
  .php   L0055 0007 VERIFY_RETURN_TYPE V1
  .php   L0055 0008 RETURN V1
  .php   L0055 0009 VERIFY_RETURN_TYPE
  .php   L0055 0010 RETURN null
  .php        ; (lines=15, args=2, vars=3, tmps=3)
  .php   L0091 0002 V3 = FE_RESET_R CV0($items) 0011
  .php   L0091 0003 FE_FETCH_R V3 CV2($item) 0011
  .php   L0092 0004 T4 = FETCH_OBJ_R CV2($item) string("id")
  .php   L0092 0005 T5 = IS_IDENTICAL CV1($id) T4
  .php   L0092 0006 JMPZ T5 0010
  .php   L0093 0007 VERIFY_RETURN_TYPE CV2($item)
  .php   L0093 0008 FE_FREE V3 loop-end(+3)
  .php   L0093 0009 RETURN CV2($item)
  .php   L0091 0010 JMP 0003
  .php   L0091 0011 FE_FREE V3
  .php   L0096 0012 RETURN null
  .php   L0097 0013 VERIFY_RETURN_TYPE
  .php   L0097 0014 RETURN null
  .php        ; (lines=5, args=1, vars=1, tmps=1)
  .php   L0102 0001 T1 = FETCH_DIM_R CV0($items) int(0)
  .php   L0102 0002 RETURN T1
  .php   L0102 0003 VERIFY_RETURN_TYPE
  .php   L0102 0004 RETURN null
  .php        ; (lines=8, args=1, vars=1, tmps=2)
  .php   L0106 0001 T1 = CAST (object) CV0($item)
  .php   L0106 0002 INIT_METHOD_CALL 0 T1 string("link")
  .php   L0106 0003 V2 = DO_FCALL
  .php   L0106 0004 VERIFY_RETURN_TYPE V2
  .php   L0106 0005 RETURN V2
  .php   L0106 0006 VERIFY_RETURN_TYPE
  .php   L0106 0007 RETURN null
  .php   L0120 0000 V0 = NEW 1 string("Paging\\PaginatedList")
  .php   L0120 0001 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   L0120 0002 V1 = DO_FCALL
  .php   L0120 0003 SEND_VAR_NO_REF_EX V1 1
  .php   L0120 0004 DO_FCALL
  .php   L0120 0005 T3 = CAST (object) V0
  .php   L0120 0006 INIT_METHOD_CALL 0 T3 string("first")
  .php   L0120 0007 V4 = DO_FCALL
  .php   L0120 0008 T5 = FETCH_OBJ_R V4 string("id")
  .php   L0120 0009 VERIFY_RETURN_TYPE T5
  .php   L0120 0010 RETURN T5
  .php   L0120 0011 VERIFY_RETURN_TYPE
  .php   L0120 0012 RETURN null
  .php        ; (lines=19, args=0, vars=0, tmps=10)
  .php   L0122 0010 V6 = DO_FCALL
  .php   L0122 0011 T7 = CAST (object) V6
  .php   L0122 0012 INIT_METHOD_CALL 0 T7 string("first")
  .php   L0122 0013 V8 = DO_FCALL
  .php   L0122 0014 T9 = FETCH_OBJ_R V8 string("id")
  .php   L0122 0015 VERIFY_RETURN_TYPE T9
  .php   L0122 0016 RETURN T9
  .php   L0122 0017 VERIFY_RETURN_TYPE
  .php   L0122 0018 RETURN null
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   L0124 0005 V1 = DO_FCALL
  .php   L0124 0006 T2 = JMP_NULL V1 0008
  .php   L0124 0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   L0124 0008 T3 = COALESCE T2 0010
  .php   L0124 0009 T3 = QM_ASSIGN int(0)
  .php   L0124 0010 VERIFY_RETURN_TYPE T3
  .php   L0124 0011 RETURN T3
  .php   L0124 0012 VERIFY_RETURN_TYPE
  .php   L0124 0013 RETURN null
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   L0126 0005 V1 = DO_FCALL
  .php   L0126 0006 T2 = JMP_NULL V1 0008
  .php   L0126 0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   L0126 0008 T3 = COALESCE T2 0010
  .php   L0126 0009 T3 = QM_ASSIGN int(0)
  .php   L0126 0010 VERIFY_RETURN_TYPE T3
  .php   L0126 0011 RETURN T3
  .php   L0126 0012 VERIFY_RETURN_TYPE
  .php   L0126 0013 RETURN null
  .php        ; (lines=12, args=0, vars=0, tmps=4)
  .php   L0132 0001 V0 = NEW 1 string("Paging\\Feed")
  .php   L0132 0002 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   L0132 0003 V1 = DO_FCALL
  .php   L0132 0004 SEND_VAR_NO_REF_EX V1 1
  .php   L0132 0005 DO_FCALL
  .php   L0132 0006 SEND_VAR V0 1
  .php   L0132 0007 V3 = DO_FCALL
  .php   L0132 0008 VERIFY_RETURN_TYPE V3
  .php   L0132 0009 RETURN V3
  .php   L0132 0010 VERIFY_RETURN_TYPE
  .php   L0132 0011 RETURN null
  .php   L0138 0007 V4 = DO_FCALL
  .php   L0138 0008 T5 = FETCH_OBJ_R V4 string("id")
  .php   L0138 0009 VERIFY_RETURN_TYPE T5
  .php   L0138 0010 RETURN T5
  .php   L0138 0011 VERIFY_RETURN_TYPE
  .php   L0138 0012 RETURN null
  .php   L0140 0008 V5 = DO_FCALL
  .php   L0140 0009 VERIFY_RETURN_TYPE V5
  .php   L0140 0010 RETURN V5
  .php   L0140 0011 VERIFY_RETURN_TYPE
  .php   L0140 0012 RETURN null
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   0002 T2 = CAST (object) CV0($query)
  .php   0003 INIT_METHOD_CALL 0 T2 string("rows")
  .php   0007 VERIFY_RETURN_TYPE V1
  .php   0008 RETURN V1
  .php   0009 VERIFY_RETURN_TYPE
  .php        1: 0002 - 0007 (new)
  .php        1: 0007 - 0008 (tmp/var)
  .php        ; (lines=15, args=2, vars=3, tmps=3)
  .php   0002 V3 = FE_RESET_R CV0($items) 0011
  .php   0003 FE_FETCH_R V3 CV2($item) 0011
  .php   0004 T4 = FETCH_OBJ_R CV2($item) string("id")
  .php   0005 T5 = IS_IDENTICAL CV1($id) T4
  .php   0006 JMPZ T5 0010
  .php   0007 VERIFY_RETURN_TYPE CV2($item)
  .php   0008 FE_FREE V3 loop-end(+3)
  .php   0009 RETURN CV2($item)
  .php   0010 JMP 0003
  .php   0011 FE_FREE V3
  .php        3: 0003 - 0008 (loop)
  .php        3: 0010 - 0011 (loop)
  .php        ; (lines=5, args=1, vars=1, tmps=1)
  .php   0001 T1 = FETCH_DIM_R CV0($items) int(0)
  .php   0002 RETURN T1
  .php        ; (lines=8, args=1, vars=1, tmps=2)
  .php   0001 T1 = CAST (object) CV0($item)
  .php   0002 INIT_METHOD_CALL 0 T1 string("link")
  .php   0004 VERIFY_RETURN_TYPE V2
  .php   0005 RETURN V2
  .php   0006 VERIFY_RETURN_TYPE
  .php   0007 RETURN null
  .php        2: 0004 - 0005 (tmp/var)
  .php   0000 V0 = NEW 1 string("Paging\\PaginatedList")
  .php        ; (lines=19, args=0, vars=0, tmps=10)
  .php   0010 V6 = DO_FCALL
  .php   0011 T7 = CAST (object) V6
  .php   0012 INIT_METHOD_CALL 0 T7 string("first")
  .php   0013 V8 = DO_FCALL
  .php   0014 T9 = FETCH_OBJ_R V8 string("id")
  .php   0015 VERIFY_RETURN_TYPE T9
  .php   0016 RETURN T9
  .php   0017 VERIFY_RETURN_TYPE
  .php   0018 RETURN null
  .php        9: 0015 - 0016 (tmp/var)
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   0005 V1 = DO_UCALL
  .php   0006 T2 = JMP_NULL V1 0008
  .php   0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   0008 T3 = COALESCE T2 0010
  .php   0009 T3 = QM_ASSIGN int(0)
  .php   0010 VERIFY_RETURN_TYPE T3
  .php   0011 RETURN T3
  .php        1: 0006 - 0007 (tmp/var)
  .php        3: 0010 - 0011 (tmp/var)
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   0005 V1 = DO_UCALL
  .php   0006 T2 = JMP_NULL V1 0008
  .php   0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   0008 T3 = COALESCE T2 0010
  .php   0009 T3 = QM_ASSIGN int(0)
  .php   0010 VERIFY_RETURN_TYPE T3
  .php   0011 RETURN T3
  .php        1: 0006 - 0007 (tmp/var)
  .php        3: 0010 - 0011 (tmp/var)
  .php        ; (lines=12, args=0, vars=0, tmps=4)
  .php   0001 V0 = NEW 1 string("Paging\\Feed")
  .php   0002 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   0003 V1 = DO_UCALL
  .php   0004 SEND_VAR_NO_REF_EX V1 1
  .php   0005 DO_FCALL
  .php   0006 SEND_VAR V0 1
  .php   0007 V3 = DO_UCALL
  .php   0008 VERIFY_RETURN_TYPE V3
  .php   0009 RETURN V3
  .php        0: 0002 - 0006 (new)
  .php        3: 0008 - 0009 (tmp/var)
  .php   0008 V5 = DO_FCALL
  .php   0009 VERIFY_RETURN_TYPE V5
  .php   0010 RETURN V5
  .php   /** @implements Query<Order> */
  .php       /** @param list<Order> $orders */
  .php   /** @template TItem of DatabaseEntity */
  .php       /** @param list<TItem> $rows */
  .php       /** @return TItem */
  .php   /** @extends PaginatedList<Order> */
  .php       /** @param list<TItem> $rows */
  .php       /** @return TItem */
  .php       /** @template TItem of DatabaseEntity
  .php        *  @param Query<TItem> $query
  .php        *  @return PaginatedList<TItem> */
  .php   /** @template TItem of DatabaseEntity */
  .php       /** @param list<TItem> $items */
  .php       /** @return TItem */
  .php   /** @implements Validator<DatabaseEntity> */
  .php       /** @template TItem of DatabaseEntity
  .php        *  @param list<TItem> $items
  .php        *  @return TItem|null */
  .php       /** @var \Sharp\Map<string, DatabaseEntity> */
  .php       Property [ private Sharp\Map $saved ]
  .php       /** @var \Sharp\Map<string, class-string<DatabaseEntity>> */
  .php       Property [ private Sharp\Map $kinds ]
  .php       /** @template T
  .php        *  @param list<T> $items
  .php        *  @return T */
  .php       /** @template TItem of DatabaseEntity&Shareable
  .php        *  @param TItem $item */
  .php       /** @param class-string<DatabaseEntity> $type */
  .php       /** @param class-string<DatabaseEntity> $type */
  .php       /** @return class-string<DatabaseEntity> */
  .php       /** @return list<Order> */
  .php       /** @param Feed<Order> $feed */
  .php       /** @param Validator<DatabaseEntity> $validator */
Operators: different opcodes and lines in 73 op arrays, same signatures in 9 classes
  check  Billing\Operators::byPrice string("List<Billing.Line>")
  check  Billing\Operators::byCode string("List<string>")
  check  Billing\Operators::checkout string("Map<string, Any?>, Any?, Map<string, Any?>")
  check  Billing\Grade::ranked string("List<Billing.Grade>")
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
Traits: same opcodes and lines in 8 op arrays, same signatures in 7 classes
a check: same
a check and another opcode: different