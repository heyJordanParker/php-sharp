--TEST--
PHP# compiles each .sharp fixture to the opcodes, lines and signatures of its PHP twin
--SKIPIF--
<?php if (!getenv('TEST_PHPDBG_EXECUTABLE')) die('skip phpdbg is missing, as in the php-fpm image'); ?>
--FILE--
<?php

function compiled(string $file, string $prelude): string
{
    $opcodes = shell_exec(escapeshellarg(getenv('TEST_PHPDBG_EXECUTABLE')) . ' -n -q -p* ' . escapeshellarg($file) . ' 2>&1');
    // phpdbg prints no property hook. Opcache's dump before optimization prints every op array with code, without
    // op lines. -l compiles the file without running it, so its classes need no library to link against.
    $dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
        . ' -n -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x10000 -l ' . escapeshellarg($file) . ' 2>&1');
    // Only the file's own classes print, not the ones it autoloads to link against, and a file of one interface prints none.
    $classes = (string) shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -r ' . escapeshellarg(
        $prelude . '$declared = get_declared_classes(); require ' . var_export($file, true) . ';'
        . ' foreach (array_diff(get_declared_classes(), $declared) as $class)'
        . ' if ((new ReflectionClass($class))->getFileName() === ' . var_export($file, true) . ') echo new ReflectionClass($class);'
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

    // A method a class inherits prints with its parent's file, a .sharp file beside the fixture's or its twin.
    return preg_replace('~' . preg_quote(dirname($file), '~') . '/(\w+)\.(?:sharp|inc)\b~', '<folder>/$1', str_replace($file, '<file>', $listing . $classes));
}

/**
 * `$listing` without the two opcodes PHP# adds on purpose: a SHARP_RECV_TYPE_ARGS with no result, which checks the
 * parameters of a call from plain PHP that its PHP twin's types can't check, and a SHARP_RETURN_ARRAY, which gives a
 * plain PHP caller the PHP array of a Map or a Set its PHP twin returns as it is. Each op array loses them and counts
 * one line less for each, and every op number after one, in an op line, a jump, a live range or a try block, is one
 * lower. A SHARP_RETURN_ARRAY's result counts one temporary less, and its operand takes its place. Each check lands in
 * `$checks` by its op array, once for both listings.
 */
function without_checks(string $listing, array &$checks): string
{
    $lines = explode("\n", $listing);
    $header = -1;
    $dropped = [];
    $returned = [];
    $renumber = static function (array $op) use (&$dropped): string {
        return sprintf('%04d', (int) $op[0] - count(array_filter($dropped, static fn (int $at): bool => $at < (int) $op[0])));
    };
    $kept = [];
    foreach ($lines as $index => $line) {
        if (preg_match('~^(\S.*):$~', $line, $name) && preg_match('~^ {5}; \(lines=~', $lines[$index + 1] ?? '')) {
            $header = count($kept) + 1;
            $dropped = [];
            $returned = [];
            $op_array = $name[1];
        } elseif ($header >= 0 && preg_match('~^(?:L\d{4} )?(\d{4}) SHARP_RECV_TYPE_ARGS (.*)$~', $line, $check)) {
            $dropped[] = (int) $check[1];
            $checks[$op_array] = $check[2];
            $kept[$header] = preg_replace_callback('~lines=(\d+)~', static fn (array $count): string => 'lines=' . ($count[1] - 1), $kept[$header]);
            continue;
        } elseif ($header >= 0 && preg_match('~^(?:L\d{4} )?(\d{4}) (T\d+) = SHARP_RETURN_ARRAY (.*)$~', $line, $crossing)) {
            $dropped[] = (int) $crossing[1];
            $returned[$crossing[2]] = $crossing[3];
            $kept[$header] = preg_replace_callback('~lines=(\d+)(.*tmps=)(\d+)~', static fn (array $count): string
                => 'lines=' . ($count[1] - 1) . $count[2] . ($count[3] - 1), $kept[$header]);
            continue;
        }
        if ($returned) {
            $line = preg_replace_callback('~\bT\d+\b~', static fn (array $result): string => $returned[$result[0]] ?? $result[0], $line);
        }
        if ($dropped && preg_match('~^(?:L\d{4} )?\d{4} |^ +(?:\d+: )?\d{4}[ ,]~', $line)) {
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
    'Permalink' => '../../../sharp/composer/library/Sharp/Text/StringMembers.sharp',
    'Library' => [
        '../../../sharp/composer/library/Sharp/Encoding/Base64.sharp',
        '../../../sharp/composer/library/Sharp/Encoding/Compression.sharp',
        '../../../sharp/composer/library/Sharp/Encoding/Hex.sharp',
        '../../../sharp/composer/library/Sharp/Encoding/Json.sharp',
        '../../../sharp/composer/library/Sharp/Int.sharp',
        '../../../sharp/composer/library/Sharp/IO/Path.sharp',
        '../../../sharp/composer/library/Sharp/Math.sharp',
        '../../../sharp/composer/library/Sharp/Net/Ip.sharp',
        '../../../sharp/composer/library/Sharp/Net/MailAddress.sharp',
        '../../../sharp/composer/library/Sharp/Net/Url.sharp',
        '../../../sharp/composer/library/Sharp/Security/Hash.sharp',
        '../../../sharp/composer/library/Sharp/Security/Password.sharp',
        '../../../sharp/composer/library/Sharp/Text/Html.sharp',
        '../../../sharp/composer/library/Sharp/Text/Regex.sharp',
        '../../../sharp/composer/library/Sharp/Time/Instant.sharp',
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

// A fixture of several types is a folder of one file per type, each with its twin beside it, and each file links
// against the others through the fixture's autoloader, the PHP# files against PHP# files and the twins against twins.
$pairs = [];
foreach ($fixtures as $fixture => $library) {
    $prelude = implode('', array_map(static fn (string $file): string => 'require ' . var_export(__DIR__ . "/$file", true) . ';', (array) $library));
    if (!is_dir(__DIR__ . "/$fixture")) {
        $pairs[$fixture] = [$prelude, $prelude];
        continue;
    }
    $loader = 'require ' . var_export(__DIR__ . '/harness/fixture.inc', true) . ';';
    foreach (glob(__DIR__ . "/$fixture/*.sharp") as $file) {
        $pairs["$fixture/" . basename($file, '.sharp')] = [
            "$prelude{$loader}fixture('$fixture');",
            "$prelude{$loader}fixture('$fixture', 'inc');",
        ];
    }
}

foreach ($pairs as $fixture => [$sharp_prelude, $php_prelude]) {
    $checks = [];
    $sharp = without_checks(compiled(__DIR__ . "/$fixture.sharp", $sharp_prelude), $checks);
    $php = compiled(__DIR__ . "/$fixture.inc", $php_prelude);
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
Nulls: same opcodes and lines in 16 op arrays, same signatures in 1 classes
ControlFlow: different opcodes and lines in 6 op arrays, same signatures in 1 classes
  .sharp      ; (lines=36, args=1, vars=6, tmps=18)
  .sharp L0061 0004 T8 = SHARP_VERIFY_TYPE_ARGS V7 string("List<int>")
  .sharp L0061 0005 V9 = FE_RESET_R T8 0010
  .sharp L0061 0006 FE_FETCH_R V9 CV2($price) 0010
  .sharp L0062 0007 T10 = MUL CV2($price) CV0($factor)
  .sharp L0062 0008 ASSIGN_OP (ADD) CV1($total) T10
  .sharp L0061 0009 JMP 0006
  .sharp L0061 0010 FE_FREE V9
  .sharp L0064 0011 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .sharp L0064 0012 V12 = DO_FCALL
  .sharp L0064 0013 T13 = SHARP_VERIFY_TYPE_ARGS V12 string("Map<int, int>")
  .sharp L0064 0014 T14 = ASSIGN CV4($crossing#1) T13
  .sharp L0064 0015 T15 = TYPE_CHECK (array) T14
  .sharp L0064 0016 JMPZ T15 0023
  .sharp L0064 0017 INIT_STATIC_METHOD_CALL 2 string("Sharp\\Map") string("from")
  .sharp L0064 0018 SEND_VAR CV4($crossing#1) 1
  .sharp L0064 0019 SEND_VAL string("int, int") 2
  .sharp L0064 0020 V16 = DO_FCALL
  .sharp L0064 0021 T17 = QM_ASSIGN V16
  .sharp L0064 0022 JMP 0024
  .sharp L0064 0023 T17 = QM_ASSIGN CV4($crossing#1)
  .sharp L0064 0024 ASSIGN CV3($slots) T17
  .sharp L0065 0025 V19 = FE_RESET_R CV3($slots) 0031
  .sharp L0065 0026 T20 = FE_FETCH_R V19 CV2($price) 0031
  .sharp L0065 0027 ASSIGN CV5($slot) T20
  .sharp L0066 0028 ASSIGN_OP (ADD) CV1($total) CV5($slot)
  .sharp L0067 0029 ASSIGN CV2($price) int(0)
  .sharp L0065 0030 JMP 0026
  .sharp L0065 0031 FE_FREE V19
  .sharp L0069 0032 VERIFY_RETURN_TYPE CV1($total)
  .sharp L0069 0033 RETURN CV1($total)
  .sharp L0070 0034 VERIFY_RETURN_TYPE
  .sharp L0070 0035 RETURN null
  .sharp      ; (lines=36, args=1, vars=6, tmps=18)
  .sharp 0004 T8 = SHARP_VERIFY_TYPE_ARGS V7 string("List<int>")
  .sharp 0005 V9 = FE_RESET_R T8 0010
  .sharp 0006 FE_FETCH_R V9 CV2($price) 0010
  .sharp 0007 T10 = MUL CV2($price) CV0($factor)
  .sharp 0008 ASSIGN_OP (ADD) CV1($total) T10
  .sharp 0009 JMP 0006
  .sharp 0010 FE_FREE V9
  .sharp 0011 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .sharp 0012 V12 = DO_FCALL
  .sharp 0013 T13 = SHARP_VERIFY_TYPE_ARGS V12 string("Map<int, int>")
  .sharp 0014 T14 = ASSIGN CV4($crossing#1) T13
  .sharp 0015 T15 = TYPE_CHECK (array) T14
  .sharp 0016 JMPZ T15 0023
  .sharp 0017 INIT_STATIC_METHOD_CALL 2 string("Sharp\\Map") string("from")
  .sharp 0018 SEND_VAR CV4($crossing#1) 1
  .sharp 0019 SEND_VAL string("int, int") 2
  .sharp 0020 V16 = DO_FCALL
  .sharp 0021 T17 = QM_ASSIGN V16
  .sharp 0022 JMP 0024
  .sharp 0023 T17 = QM_ASSIGN CV4($crossing#1)
  .sharp 0024 ASSIGN CV3($slots) T17
  .sharp 0025 V19 = FE_RESET_R CV3($slots) 0031
  .sharp 0026 T20 = FE_FETCH_R V19 CV2($price) 0031
  .sharp 0027 ASSIGN CV5($slot) T20
  .sharp 0028 ASSIGN_OP (ADD) CV1($total) CV5($slot)
  .sharp 0029 ASSIGN CV2($price) int(0)
  .sharp 0030 JMP 0026
  .sharp 0031 FE_FREE V19
  .sharp 0032 VERIFY_RETURN_TYPE CV1($total)
  .sharp 0033 RETURN CV1($total)
  .sharp 0034 VERIFY_RETURN_TYPE
  .sharp 0035 RETURN null
  .sharp      9: 0006 - 0010 (loop)
  .sharp      19: 0026 - 0031 (loop)
  .php        ; (lines=33, args=1, vars=6, tmps=16)
  .php   L0061 0004 V8 = FE_RESET_R V7 0009
  .php   L0061 0005 FE_FETCH_R V8 CV2($price) 0009
  .php   L0062 0006 T9 = MUL CV2($price) CV0($factor)
  .php   L0062 0007 ASSIGN_OP (ADD) CV1($total) T9
  .php   L0061 0008 JMP 0005
  .php   L0061 0009 FE_FREE V8
  .php   L0064 0010 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .php   L0064 0011 V11 = DO_FCALL
  .php   L0064 0012 T12 = ASSIGN CV4($crossing#1) V11
  .php   L0064 0013 T13 = TYPE_CHECK (array) T12
  .php   L0064 0014 JMPZ T13 0020
  .php   L0064 0015 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Map") string("from")
  .php   L0064 0016 SEND_VAR CV4($crossing#1) 1
  .php   L0064 0017 V14 = DO_FCALL
  .php   L0064 0018 T15 = QM_ASSIGN V14
  .php   L0064 0019 JMP 0021
  .php   L0064 0020 T15 = QM_ASSIGN CV4($crossing#1)
  .php   L0064 0021 ASSIGN CV3($slots) T15
  .php   L0065 0022 V17 = FE_RESET_R CV3($slots) 0028
  .php   L0065 0023 T18 = FE_FETCH_R V17 CV2($price) 0028
  .php   L0065 0024 ASSIGN CV5($slot) T18
  .php   L0066 0025 ASSIGN_OP (ADD) CV1($total) CV5($slot)
  .php   L0067 0026 ASSIGN CV2($price) int(0)
  .php   L0065 0027 JMP 0023
  .php   L0065 0028 FE_FREE V17
  .php   L0069 0029 VERIFY_RETURN_TYPE CV1($total)
  .php   L0069 0030 RETURN CV1($total)
  .php   L0070 0031 VERIFY_RETURN_TYPE
  .php   L0070 0032 RETURN null
  .php        ; (lines=33, args=1, vars=6, tmps=16)
  .php   0004 V8 = FE_RESET_R V7 0009
  .php   0005 FE_FETCH_R V8 CV2($price) 0009
  .php   0006 T9 = MUL CV2($price) CV0($factor)
  .php   0007 ASSIGN_OP (ADD) CV1($total) T9
  .php   0008 JMP 0005
  .php   0009 FE_FREE V8
  .php   0010 INIT_STATIC_METHOD_CALL 0 string("Demo\\Prices") string("all")
  .php   0011 V11 = DO_FCALL
  .php   0012 T12 = ASSIGN CV4($crossing#1) V11
  .php   0013 T13 = TYPE_CHECK (array) T12
  .php   0014 JMPZ T13 0020
  .php   0015 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Map") string("from")
  .php   0016 SEND_VAR CV4($crossing#1) 1
  .php   0017 V14 = DO_FCALL
  .php   0018 T15 = QM_ASSIGN V14
  .php   0019 JMP 0021
  .php   0020 T15 = QM_ASSIGN CV4($crossing#1)
  .php   0021 ASSIGN CV3($slots) T15
  .php   0022 V17 = FE_RESET_R CV3($slots) 0028
  .php   0023 T18 = FE_FETCH_R V17 CV2($price) 0028
  .php   0024 ASSIGN CV5($slot) T18
  .php   0025 ASSIGN_OP (ADD) CV1($total) CV5($slot)
  .php   0026 ASSIGN CV2($price) int(0)
  .php   0027 JMP 0023
  .php   0028 FE_FREE V17
  .php   0029 VERIFY_RETURN_TYPE CV1($total)
  .php   0030 RETURN CV1($total)
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
  .php        8: 0005 - 0009 (loop)
  .php        17: 0023 - 0028 (loop)
Order/Order: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Order/Till: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Product: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Expressions: same opcodes and lines in 8 op arrays, same signatures in 1 classes
Checkout: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Page: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Stage: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Suit: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Rank: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Shipment: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Task: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Shapes/Measured: same opcodes and lines in 2 op arrays, same signatures in 0 classes
Shapes/Shape: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Shapes/Unit: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Members: same opcodes and lines in 10 op arrays, same signatures in 1 classes
Site/Page: same opcodes and lines in 9 op arrays, same signatures in 1 classes
Site/Shape: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Site/Square: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Collections/Cart: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Cart::fill string("List<Demo.Item>")
  check  Demo\Cart::withPrices string("List<int>, List<int>")
  .sharp     Property [ private Sharp\Map $counts = Closure({closure:<file>:8})() ]
  .php       Property [ private Sharp\Map $counts ]
Collections/Item: same opcodes and lines in 2 op arrays, same signatures in 1 classes
EnumKeys/Headcount: different opcodes and lines in 22 op arrays, same signatures in 1 classes
  check  Demo\Headcount::count string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::ensure string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::lookup string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::without string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::byName string("Map<Lib.Standing, int>")
  check  Demo\Headcount::skus string("Map<string, int>")
  check  Demo\Headcount::grouped string("List<int>")
  check  Demo\Headcount::groupNames string("List<int>")
  .sharp      ; (lines=37, args=2, vars=2, tmps=15)
  .sharp L0023 0003 JMPZ T2 0025
  .sharp L0023 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0023 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0023 0006 V5 = NEW 1 V3 T4
  .sharp L0023 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0023 0008 SEND_VAL array(...) 1
  .sharp L0023 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0023 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0023 0011 SEND_VAL T6 1
  .sharp L0023 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0023 0013 SEND_VAR CV0($counts) 1
  .sharp L0023 0014 V7 = DO_ICALL
  .sharp L0023 0015 SEND_VAR V7 2
  .sharp L0023 0016 SEND_VAR CV0($counts) 3
  .sharp L0023 0017 V8 = DO_ICALL
  .sharp L0023 0018 SEND_UNPACK V8
  .sharp L0023 0019 CHECK_UNDEF_ARGS
  .sharp L0023 0020 V9 = DO_ICALL
  .sharp L0023 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp L0023 0022 DO_FCALL
  .sharp L0023 0023 T11 = QM_ASSIGN V5
  .sharp L0023 0024 JMP 0026
  .sharp L0023 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp L0023 0026 ASSIGN CV0($counts) T11
  .sharp L0025 0027 T14 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp L0025 0028 T15 = COALESCE T14 0030
  .sharp L0025 0029 T15 = QM_ASSIGN int(0)
  .sharp L0025 0030 T16 = ADD T15 int(1)
  .sharp L0025 0031 ASSIGN_DIM CV0($counts) CV1($status)
  .sharp L0025 0032 OP_DATA T16
  .sharp L0026 0033 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0026 0034 RETURN CV0($counts)
  .sharp L0027 0035 VERIFY_RETURN_TYPE
  .sharp L0027 0036 RETURN null
  .sharp L0023 0000 CV0($key#1) = RECV 1
  .sharp L0023 0001 CV1($value#2) = RECV 2
  .sharp L0023 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0023 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=37, args=2, vars=2, tmps=14)
  .sharp L0029 0003 JMPZ T2 0025
  .sharp L0029 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0029 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0029 0006 V5 = NEW 1 V3 T4
  .sharp L0029 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0029 0008 SEND_VAL array(...) 1
  .sharp L0029 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0029 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0029 0011 SEND_VAL T6 1
  .sharp L0029 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0029 0013 SEND_VAR CV0($counts) 1
  .sharp L0029 0014 V7 = DO_ICALL
  .sharp L0029 0015 SEND_VAR V7 2
  .sharp L0029 0016 SEND_VAR CV0($counts) 3
  .sharp L0029 0017 V8 = DO_ICALL
  .sharp L0029 0018 SEND_UNPACK V8
  .sharp L0029 0019 CHECK_UNDEF_ARGS
  .sharp L0029 0020 V9 = DO_ICALL
  .sharp L0029 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp L0029 0022 DO_FCALL
  .sharp L0029 0023 T11 = QM_ASSIGN V5
  .sharp L0029 0024 JMP 0026
  .sharp L0029 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp L0029 0026 ASSIGN CV0($counts) T11
  .sharp L0031 0027 T13 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp L0031 0028 T14 = COALESCE T13 0032
  .sharp L0031 0029 T15 = ASSIGN_DIM CV0($counts) CV1($status)
  .sharp L0031 0030 OP_DATA int(0)
  .sharp L0031 0031 T14 = QM_ASSIGN T15
  .sharp L0031 0032 FREE T14
  .sharp L0032 0033 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0032 0034 RETURN CV0($counts)
  .sharp L0033 0035 VERIFY_RETURN_TYPE
  .sharp L0033 0036 RETURN null
  .sharp L0029 0000 CV0($key#1) = RECV 1
  .sharp L0029 0001 CV1($value#2) = RECV 2
  .sharp L0029 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0029 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=15, args=2, vars=2, tmps=5)
  .sharp L0037 0002 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0037 0003 T3 = SHARP_TYPE_ARGS string("Lib.Size, string")
  .sharp L0037 0004 V4 = NEW 1 V2 T3
  .sharp L0037 0005 T5 = INIT_ARRAY 4 (packed) CV0($small) NEXT
  .sharp L0037 0006 T5 = ADD_ARRAY_ELEMENT string("small") NEXT
  .sharp L0037 0007 T5 = ADD_ARRAY_ELEMENT CV1($large) NEXT
  .sharp L0037 0008 T5 = ADD_ARRAY_ELEMENT string("large") NEXT
  .sharp L0037 0009 SEND_VAL_EX T5 1
  .sharp L0037 0010 DO_FCALL
  .sharp L0037 0011 VERIFY_RETURN_TYPE V4
  .sharp L0037 0012 RETURN V4
  .sharp L0038 0013 VERIFY_RETURN_TYPE
  .sharp L0038 0014 RETURN null
  .sharp      ; (lines=12, args=1, vars=2, tmps=6)
  .sharp L0042 0001 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0042 0002 T3 = SHARP_TYPE_ARGS string("Lib.Code, int")
  .sharp L0042 0003 V4 = NEW 0 V2 T3
  .sharp L0042 0004 DO_FCALL
  .sharp L0042 0005 ASSIGN CV1($codes) V4
  .sharp L0043 0006 ASSIGN_DIM CV1($codes) CV0($code)
  .sharp L0043 0007 OP_DATA int(5)
  .sharp L0044 0008 VERIFY_RETURN_TYPE CV1($codes)
  .sharp L0044 0009 RETURN CV1($codes)
  .sharp L0045 0010 VERIFY_RETURN_TYPE
  .sharp L0045 0011 RETURN null
  .sharp L0047 0003 JMPZ T2 0025
  .sharp L0047 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0047 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0047 0006 V5 = NEW 1 V3 T4
  .sharp L0047 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0047 0008 SEND_VAL array(...) 1
  .sharp L0047 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0047 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0047 0011 SEND_VAL T6 1
  .sharp L0047 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0047 0013 SEND_VAR CV0($counts) 1
  .sharp L0047 0014 V7 = DO_ICALL
  .sharp L0047 0015 SEND_VAR V7 2
  .sharp L0047 0016 SEND_VAR CV0($counts) 3
  .sharp L0047 0017 V8 = DO_ICALL
  .sharp L0047 0018 SEND_UNPACK V8
  .sharp L0047 0019 CHECK_UNDEF_ARGS
  .sharp L0047 0020 V9 = DO_ICALL
  .sharp L0047 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp L0047 0022 DO_FCALL
  .sharp L0047 0023 T11 = QM_ASSIGN V5
  .sharp L0047 0024 JMP 0026
  .sharp L0047 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp L0047 0026 ASSIGN CV0($counts) T11
  .sharp L0049 0027 T13 = CAST (object) CV0($counts)
  .sharp L0049 0028 INIT_METHOD_CALL 1 T13 string("get")
  .sharp L0049 0029 SEND_VAR_EX CV1($status) 1
  .sharp L0049 0030 V14 = DO_FCALL
  .sharp L0049 0031 VERIFY_RETURN_TYPE V14
  .sharp L0049 0032 RETURN V14
  .sharp L0050 0033 VERIFY_RETURN_TYPE
  .sharp L0050 0034 RETURN null
  .sharp L0047 0000 CV0($key#1) = RECV 1
  .sharp L0047 0001 CV1($value#2) = RECV 2
  .sharp L0047 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0052 0003 JMPZ T2 0025
  .sharp L0052 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0052 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0052 0006 V5 = NEW 1 V3 T4
  .sharp L0052 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0052 0008 SEND_VAL array(...) 1
  .sharp L0052 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0052 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0052 0011 SEND_VAL T6 1
  .sharp L0052 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0052 0013 SEND_VAR CV0($counts) 1
  .sharp L0052 0014 V7 = DO_ICALL
  .sharp L0052 0015 SEND_VAR V7 2
  .sharp L0052 0016 SEND_VAR CV0($counts) 3
  .sharp L0052 0017 V8 = DO_ICALL
  .sharp L0052 0018 SEND_UNPACK V8
  .sharp L0052 0019 CHECK_UNDEF_ARGS
  .sharp L0052 0020 V9 = DO_ICALL
  .sharp L0052 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp L0052 0022 DO_FCALL
  .sharp L0052 0023 T11 = QM_ASSIGN V5
  .sharp L0052 0024 JMP 0026
  .sharp L0052 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp L0052 0026 ASSIGN CV0($counts) T11
  .sharp L0054 0027 T13 = CAST (object) CV0($counts)
  .sharp L0054 0028 INIT_METHOD_CALL 1 T13 string("remove")
  .sharp L0054 0029 SEND_VAR_EX CV1($status) 1
  .sharp L0054 0030 DO_FCALL
  .sharp L0055 0031 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0055 0032 RETURN CV0($counts)
  .sharp L0056 0033 VERIFY_RETURN_TYPE
  .sharp L0056 0034 RETURN null
  .sharp L0052 0000 CV0($key#1) = RECV 1
  .sharp L0052 0001 CV1($value#2) = RECV 2
  .sharp L0052 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0052 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=20, args=2, vars=4, tmps=12)
  .sharp L0060 0002 V4 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0060 0003 T5 = SHARP_TYPE_ARGS string("Lib.Standing, Map<Lib.Size, int>")
  .sharp L0060 0004 V6 = NEW 0 V4 T5
  .sharp L0060 0005 DO_FCALL
  .sharp L0060 0006 ASSIGN CV2($nested) V6
  .sharp L0061 0007 V9 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0061 0008 T10 = SHARP_TYPE_ARGS string("Lib.Size, int")
  .sharp L0061 0009 V11 = NEW 0 V9 T10
  .sharp L0061 0010 DO_FCALL
  .sharp L0061 0011 ASSIGN CV3($sizes) V11
  .sharp L0062 0012 ASSIGN_DIM CV3($sizes) CV1($size)
  .sharp L0062 0013 OP_DATA int(1)
  .sharp L0063 0014 ASSIGN_DIM CV2($nested) CV0($status)
  .sharp L0063 0015 OP_DATA CV3($sizes)
  .sharp L0064 0016 VERIFY_RETURN_TYPE CV2($nested)
  .sharp L0064 0017 RETURN CV2($nested)
  .sharp L0065 0018 VERIFY_RETURN_TYPE
  .sharp L0065 0019 RETURN null
  .sharp      ; (lines=43, args=1, vars=4, tmps=21)
  .sharp L0067 0002 JMPZ T4 0024
  .sharp L0067 0003 V5 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0067 0004 T6 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0067 0005 V7 = NEW 1 V5 T6
  .sharp L0067 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp L0067 0007 SEND_VAL array(...) 1
  .sharp L0067 0008 INIT_FCALL 3 128 string("array_map")
  .sharp L0067 0009 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0067 0010 SEND_VAL T8 1
  .sharp L0067 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp L0067 0012 SEND_VAR CV0($counts) 1
  .sharp L0067 0013 V9 = DO_ICALL
  .sharp L0067 0014 SEND_VAR V9 2
  .sharp L0067 0015 SEND_VAR CV0($counts) 3
  .sharp L0067 0016 V10 = DO_ICALL
  .sharp L0067 0017 SEND_UNPACK V10
  .sharp L0067 0018 CHECK_UNDEF_ARGS
  .sharp L0067 0019 V11 = DO_ICALL
  .sharp L0067 0020 SEND_VAR_NO_REF_EX V11 1
  .sharp L0067 0021 DO_FCALL
  .sharp L0067 0022 T13 = QM_ASSIGN V7
  .sharp L0067 0023 JMP 0025
  .sharp L0067 0024 T13 = QM_ASSIGN CV0($counts)
  .sharp L0067 0025 ASSIGN CV0($counts) T13
  .sharp L0069 0026 V15 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0069 0027 T16 = SHARP_TYPE_ARGS string("string, int")
  .sharp L0069 0028 V17 = NEW 0 V15 T16
  .sharp L0069 0029 DO_FCALL
  .sharp L0069 0030 ASSIGN CV1($named) V17
  .sharp L0070 0031 V20 = FE_RESET_R CV0($counts) 0038
  .sharp L0070 0032 T21 = FE_FETCH_R V20 CV2($n) 0038
  .sharp L0070 0033 ASSIGN CV3($status) T21
  .sharp L0071 0034 T23 = FETCH_OBJ_R CV3($status) string("name")
  .sharp L0071 0035 ASSIGN_DIM CV1($named) T23
  .sharp L0071 0036 OP_DATA CV2($n)
  .sharp L0070 0037 JMP 0032
  .sharp L0070 0038 FE_FREE V20
  .sharp L0073 0039 VERIFY_RETURN_TYPE CV1($named)
  .sharp L0073 0040 RETURN CV1($named)
  .sharp L0074 0041 VERIFY_RETURN_TYPE
  .sharp L0074 0042 RETURN null
  .sharp L0067 0000 CV0($key#1) = RECV 1
  .sharp L0067 0001 CV1($value#2) = RECV 2
  .sharp L0067 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0067 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=42, args=1, vars=4, tmps=18)
  .sharp L0076 0002 JMPZ T4 0024
  .sharp L0076 0003 V5 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0076 0004 T6 = SHARP_TYPE_ARGS string("string, int")
  .sharp L0076 0005 V7 = NEW 1 V5 T6
  .sharp L0076 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp L0076 0007 SEND_VAL array(...) 1
  .sharp L0076 0008 INIT_FCALL 3 128 string("array_map")
  .sharp L0076 0009 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0076 0010 SEND_VAL T8 1
  .sharp L0076 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp L0076 0012 SEND_VAR CV0($stock) 1
  .sharp L0076 0013 V9 = DO_ICALL
  .sharp L0076 0014 SEND_VAR V9 2
  .sharp L0076 0015 SEND_VAR CV0($stock) 3
  .sharp L0076 0016 V10 = DO_ICALL
  .sharp L0076 0017 SEND_UNPACK V10
  .sharp L0076 0018 CHECK_UNDEF_ARGS
  .sharp L0076 0019 V11 = DO_ICALL
  .sharp L0076 0020 SEND_VAR_NO_REF_EX V11 1
  .sharp L0076 0021 DO_FCALL
  .sharp L0076 0022 T13 = QM_ASSIGN V7
  .sharp L0076 0023 JMP 0025
  .sharp L0076 0024 T13 = QM_ASSIGN CV0($stock)
  .sharp L0076 0025 ASSIGN CV0($stock) T13
  .sharp L0078 0026 ASSIGN CV1($skus) array(...)
  .sharp L0079 0027 V16 = FE_RESET_R CV0($stock) 0037
  .sharp L0079 0028 T17 = FE_FETCH_R V16 CV2($n) 0037
  .sharp L0079 0029 ASSIGN CV3($sku) T17
  .sharp L0080 0030 T19 = IS_SMALLER int(0) CV2($n)
  .sharp L0080 0031 JMPZ T19 0036
  .sharp L0081 0032 T20 = CAST (object) CV1($skus)
  .sharp L0081 0033 INIT_METHOD_CALL 1 T20 string("add")
  .sharp L0081 0034 SEND_VAR_EX CV3($sku) 1
  .sharp L0081 0035 DO_FCALL
  .sharp L0079 0036 JMP 0028
  .sharp L0079 0037 FE_FREE V16
  .sharp L0084 0038 VERIFY_RETURN_TYPE CV1($skus)
  .sharp L0084 0039 RETURN CV1($skus)
  .sharp L0085 0040 VERIFY_RETURN_TYPE
  .sharp L0085 0041 RETURN null
  .sharp L0076 0000 CV0($key#1) = RECV 1
  .sharp L0076 0001 CV1($value#2) = RECV 2
  .sharp L0076 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0076 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=11, args=1, vars=1, tmps=3)
  .sharp L0089 0002 INIT_METHOD_CALL 2 T1 string("groupBy")
  .sharp L0089 0005 SEND_VAL_EX string("Lib.Standing, List<int>") 2
  .sharp L0089 0006 V3 = DO_FCALL
  .sharp L0089 0007 VERIFY_RETURN_TYPE V3
  .sharp L0089 0008 RETURN V3
  .sharp L0090 0009 VERIFY_RETURN_TYPE
  .sharp L0090 0010 RETURN null
  .sharp      ; (lines=23, args=1, vars=4, tmps=10)
  .sharp L0095 0003 INIT_METHOD_CALL 2 T5 string("groupBy")
  .sharp L0095 0006 SEND_VAL_EX string("Lib.Standing, List<int>") 2
  .sharp L0095 0007 V7 = DO_FCALL
  .sharp L0095 0008 V8 = FE_RESET_R V7 0018
  .sharp L0095 0009 T9 = FE_FETCH_R V8 CV2($group) 0018
  .sharp L0095 0010 ASSIGN CV3($status) T9
  .sharp L0096 0011 T11 = CAST (object) CV1($names)
  .sharp L0096 0012 INIT_METHOD_CALL 1 T11 string("add")
  .sharp L0096 0013 CHECK_FUNC_ARG 1
  .sharp L0096 0014 V12 = FETCH_OBJ_FUNC_ARG (ref) CV3($status) string("name")
  .sharp L0096 0015 SEND_FUNC_ARG V12 1
  .sharp L0096 0016 DO_FCALL
  .sharp L0095 0017 JMP 0009
  .sharp L0095 0018 FE_FREE V8
  .sharp L0098 0019 VERIFY_RETURN_TYPE CV1($names)
  .sharp L0098 0020 RETURN CV1($names)
  .sharp L0099 0021 VERIFY_RETURN_TYPE
  .sharp L0099 0022 RETURN null
  .sharp      ; (lines=37, args=2, vars=2, tmps=15)
  .sharp 0003 JMPZ T2 0025
  .sharp 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0006 V5 = NEW 1 V3 T4
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T6 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV0($counts) 1
  .sharp 0014 V7 = DO_ICALL
  .sharp 0015 SEND_VAR V7 2
  .sharp 0016 SEND_VAR CV0($counts) 3
  .sharp 0017 V8 = DO_ICALL
  .sharp 0018 SEND_UNPACK V8
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V9 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T11 = QM_ASSIGN V5
  .sharp 0024 JMP 0026
  .sharp 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp 0026 ASSIGN CV0($counts) T11
  .sharp 0027 T14 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp 0028 T15 = COALESCE T14 0030
  .sharp 0029 T15 = QM_ASSIGN int(0)
  .sharp 0030 T16 = ADD T15 int(1)
  .sharp 0031 ASSIGN_DIM CV0($counts) CV1($status)
  .sharp 0032 OP_DATA T16
  .sharp 0033 VERIFY_RETURN_TYPE CV0($counts)
  .sharp 0034 RETURN CV0($counts)
  .sharp 0035 VERIFY_RETURN_TYPE
  .sharp 0036 RETURN null
  .sharp      5: 0007 - 0023 (new)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=37, args=2, vars=2, tmps=14)
  .sharp 0003 JMPZ T2 0025
  .sharp 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0006 V5 = NEW 1 V3 T4
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T6 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV0($counts) 1
  .sharp 0014 V7 = DO_ICALL
  .sharp 0015 SEND_VAR V7 2
  .sharp 0016 SEND_VAR CV0($counts) 3
  .sharp 0017 V8 = DO_ICALL
  .sharp 0018 SEND_UNPACK V8
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V9 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T11 = QM_ASSIGN V5
  .sharp 0024 JMP 0026
  .sharp 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp 0026 ASSIGN CV0($counts) T11
  .sharp 0027 T13 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp 0028 T14 = COALESCE T13 0032
  .sharp 0029 T15 = ASSIGN_DIM CV0($counts) CV1($status)
  .sharp 0030 OP_DATA int(0)
  .sharp 0031 T14 = QM_ASSIGN T15
  .sharp 0032 FREE T14
  .sharp 0033 VERIFY_RETURN_TYPE CV0($counts)
  .sharp 0034 RETURN CV0($counts)
  .sharp 0035 VERIFY_RETURN_TYPE
  .sharp 0036 RETURN null
  .sharp      5: 0007 - 0023 (new)
  .sharp      15: 0030 - 0031 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=15, args=2, vars=2, tmps=5)
  .sharp 0002 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0003 T3 = SHARP_TYPE_ARGS string("Lib.Size, string")
  .sharp 0004 V4 = NEW 1 V2 T3
  .sharp 0005 T5 = INIT_ARRAY 4 (packed) CV0($small) NEXT
  .sharp 0006 T5 = ADD_ARRAY_ELEMENT string("small") NEXT
  .sharp 0007 T5 = ADD_ARRAY_ELEMENT CV1($large) NEXT
  .sharp 0008 T5 = ADD_ARRAY_ELEMENT string("large") NEXT
  .sharp 0009 SEND_VAL_EX T5 1
  .sharp 0010 DO_FCALL
  .sharp 0011 VERIFY_RETURN_TYPE V4
  .sharp 0012 RETURN V4
  .sharp 0013 VERIFY_RETURN_TYPE
  .sharp 0014 RETURN null
  .sharp      4: 0005 - 0011 (new)
  .sharp      5: 0006 - 0009 (tmp/var)
  .sharp      4: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=12, args=1, vars=2, tmps=6)
  .sharp 0001 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0002 T3 = SHARP_TYPE_ARGS string("Lib.Code, int")
  .sharp 0003 V4 = NEW 0 V2 T3
  .sharp 0004 DO_FCALL
  .sharp 0005 ASSIGN CV1($codes) V4
  .sharp 0006 ASSIGN_DIM CV1($codes) CV0($code)
  .sharp 0007 OP_DATA int(5)
  .sharp 0008 VERIFY_RETURN_TYPE CV1($codes)
  .sharp 0009 RETURN CV1($codes)
  .sharp 0010 VERIFY_RETURN_TYPE
  .sharp 0011 RETURN null
  .sharp      4: 0004 - 0005 (new)
  .sharp 0003 JMPZ T2 0025
  .sharp 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0006 V5 = NEW 1 V3 T4
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T6 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV0($counts) 1
  .sharp 0014 V7 = DO_ICALL
  .sharp 0015 SEND_VAR V7 2
  .sharp 0016 SEND_VAR CV0($counts) 3
  .sharp 0017 V8 = DO_ICALL
  .sharp 0018 SEND_UNPACK V8
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V9 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T11 = QM_ASSIGN V5
  .sharp 0024 JMP 0026
  .sharp 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp 0026 ASSIGN CV0($counts) T11
  .sharp 0027 T13 = CAST (object) CV0($counts)
  .sharp 0028 INIT_METHOD_CALL 1 T13 string("get")
  .sharp 0029 SEND_VAR_EX CV1($status) 1
  .sharp 0030 V14 = DO_FCALL
  .sharp 0031 VERIFY_RETURN_TYPE V14
  .sharp 0032 RETURN V14
  .sharp      5: 0007 - 0023 (new)
  .sharp      14: 0031 - 0032 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0003 JMPZ T2 0025
  .sharp 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T4 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0006 V5 = NEW 1 V3 T4
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T6 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV0($counts) 1
  .sharp 0014 V7 = DO_ICALL
  .sharp 0015 SEND_VAR V7 2
  .sharp 0016 SEND_VAR CV0($counts) 3
  .sharp 0017 V8 = DO_ICALL
  .sharp 0018 SEND_UNPACK V8
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V9 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V9 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T11 = QM_ASSIGN V5
  .sharp 0024 JMP 0026
  .sharp 0025 T11 = QM_ASSIGN CV0($counts)
  .sharp 0026 ASSIGN CV0($counts) T11
  .sharp 0027 T13 = CAST (object) CV0($counts)
  .sharp 0028 INIT_METHOD_CALL 1 T13 string("remove")
  .sharp 0029 SEND_VAR_EX CV1($status) 1
  .sharp 0030 DO_FCALL
  .sharp      5: 0007 - 0023 (new)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=20, args=2, vars=4, tmps=12)
  .sharp 0002 V4 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0003 T5 = SHARP_TYPE_ARGS string("Lib.Standing, Map<Lib.Size, int>")
  .sharp 0004 V6 = NEW 0 V4 T5
  .sharp 0005 DO_FCALL
  .sharp 0006 ASSIGN CV2($nested) V6
  .sharp 0007 V9 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0008 T10 = SHARP_TYPE_ARGS string("Lib.Size, int")
  .sharp 0009 V11 = NEW 0 V9 T10
  .sharp 0010 DO_FCALL
  .sharp 0011 ASSIGN CV3($sizes) V11
  .sharp 0012 ASSIGN_DIM CV3($sizes) CV1($size)
  .sharp 0013 OP_DATA int(1)
  .sharp 0014 ASSIGN_DIM CV2($nested) CV0($status)
  .sharp 0015 OP_DATA CV3($sizes)
  .sharp 0016 VERIFY_RETURN_TYPE CV2($nested)
  .sharp 0017 RETURN CV2($nested)
  .sharp 0018 VERIFY_RETURN_TYPE
  .sharp 0019 RETURN null
  .sharp      6: 0005 - 0006 (new)
  .sharp      11: 0010 - 0011 (new)
  .sharp      ; (lines=43, args=1, vars=4, tmps=21)
  .sharp 0002 JMPZ T4 0024
  .sharp 0003 V5 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0004 T6 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0005 V7 = NEW 1 V5 T6
  .sharp 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp 0007 SEND_VAL array(...) 1
  .sharp 0008 INIT_FCALL 3 128 string("array_map")
  .sharp 0009 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0010 SEND_VAL T8 1
  .sharp 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp 0012 SEND_VAR CV0($counts) 1
  .sharp 0013 V9 = DO_ICALL
  .sharp 0014 SEND_VAR V9 2
  .sharp 0015 SEND_VAR CV0($counts) 3
  .sharp 0016 V10 = DO_ICALL
  .sharp 0017 SEND_UNPACK V10
  .sharp 0018 CHECK_UNDEF_ARGS
  .sharp 0019 V11 = DO_ICALL
  .sharp 0020 SEND_VAR_NO_REF_EX V11 1
  .sharp 0021 DO_FCALL
  .sharp 0022 T13 = QM_ASSIGN V7
  .sharp 0023 JMP 0025
  .sharp 0024 T13 = QM_ASSIGN CV0($counts)
  .sharp 0025 ASSIGN CV0($counts) T13
  .sharp 0026 V15 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0027 T16 = SHARP_TYPE_ARGS string("string, int")
  .sharp 0028 V17 = NEW 0 V15 T16
  .sharp 0029 DO_FCALL
  .sharp 0030 ASSIGN CV1($named) V17
  .sharp 0031 V20 = FE_RESET_R CV0($counts) 0038
  .sharp 0032 T21 = FE_FETCH_R V20 CV2($n) 0038
  .sharp 0033 ASSIGN CV3($status) T21
  .sharp 0034 T23 = FETCH_OBJ_R CV3($status) string("name")
  .sharp 0035 ASSIGN_DIM CV1($named) T23
  .sharp 0036 OP_DATA CV2($n)
  .sharp 0037 JMP 0032
  .sharp 0038 FE_FREE V20
  .sharp 0039 VERIFY_RETURN_TYPE CV1($named)
  .sharp 0040 RETURN CV1($named)
  .sharp 0041 VERIFY_RETURN_TYPE
  .sharp 0042 RETURN null
  .sharp      7: 0006 - 0022 (new)
  .sharp      17: 0029 - 0030 (new)
  .sharp      20: 0032 - 0038 (loop)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=42, args=1, vars=4, tmps=18)
  .sharp 0002 JMPZ T4 0024
  .sharp 0003 V5 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0004 T6 = SHARP_TYPE_ARGS string("string, int")
  .sharp 0005 V7 = NEW 1 V5 T6
  .sharp 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp 0007 SEND_VAL array(...) 1
  .sharp 0008 INIT_FCALL 3 128 string("array_map")
  .sharp 0009 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0010 SEND_VAL T8 1
  .sharp 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp 0012 SEND_VAR CV0($stock) 1
  .sharp 0013 V9 = DO_ICALL
  .sharp 0014 SEND_VAR V9 2
  .sharp 0015 SEND_VAR CV0($stock) 3
  .sharp 0016 V10 = DO_ICALL
  .sharp 0017 SEND_UNPACK V10
  .sharp 0018 CHECK_UNDEF_ARGS
  .sharp 0019 V11 = DO_ICALL
  .sharp 0020 SEND_VAR_NO_REF_EX V11 1
  .sharp 0021 DO_FCALL
  .sharp 0022 T13 = QM_ASSIGN V7
  .sharp 0023 JMP 0025
  .sharp 0024 T13 = QM_ASSIGN CV0($stock)
  .sharp 0025 ASSIGN CV0($stock) T13
  .sharp 0026 ASSIGN CV1($skus) array(...)
  .sharp 0027 V16 = FE_RESET_R CV0($stock) 0037
  .sharp 0028 T17 = FE_FETCH_R V16 CV2($n) 0037
  .sharp 0029 ASSIGN CV3($sku) T17
  .sharp 0030 T19 = IS_SMALLER int(0) CV2($n)
  .sharp 0031 JMPZ T19 0036
  .sharp 0032 T20 = CAST (object) CV1($skus)
  .sharp 0033 INIT_METHOD_CALL 1 T20 string("add")
  .sharp 0034 SEND_VAR_EX CV3($sku) 1
  .sharp 0035 DO_FCALL
  .sharp 0036 JMP 0028
  .sharp 0037 FE_FREE V16
  .sharp 0038 VERIFY_RETURN_TYPE CV1($skus)
  .sharp 0039 RETURN CV1($skus)
  .sharp 0040 VERIFY_RETURN_TYPE
  .sharp 0041 RETURN null
  .sharp      7: 0006 - 0022 (new)
  .sharp      16: 0028 - 0037 (loop)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=11, args=1, vars=1, tmps=3)
  .sharp 0002 INIT_METHOD_CALL 2 T1 string("groupBy")
  .sharp 0005 SEND_VAL_EX string("Lib.Standing, List<int>") 2
  .sharp 0006 V3 = DO_FCALL
  .sharp 0007 VERIFY_RETURN_TYPE V3
  .sharp 0008 RETURN V3
  .sharp 0009 VERIFY_RETURN_TYPE
  .sharp 0010 RETURN null
  .sharp      3: 0007 - 0008 (tmp/var)
  .sharp      ; (lines=23, args=1, vars=4, tmps=10)
  .sharp 0003 INIT_METHOD_CALL 2 T5 string("groupBy")
  .sharp 0006 SEND_VAL_EX string("Lib.Standing, List<int>") 2
  .sharp 0007 V7 = DO_FCALL
  .sharp 0008 V8 = FE_RESET_R V7 0018
  .sharp 0009 T9 = FE_FETCH_R V8 CV2($group) 0018
  .sharp 0010 ASSIGN CV3($status) T9
  .sharp 0011 T11 = CAST (object) CV1($names)
  .sharp 0012 INIT_METHOD_CALL 1 T11 string("add")
  .sharp 0013 CHECK_FUNC_ARG 1
  .sharp 0014 V12 = FETCH_OBJ_FUNC_ARG (ref) CV3($status) string("name")
  .sharp 0015 SEND_FUNC_ARG V12 1
  .sharp 0016 DO_FCALL
  .sharp 0017 JMP 0009
  .sharp 0018 FE_FREE V8
  .sharp 0019 VERIFY_RETURN_TYPE CV1($names)
  .sharp 0020 RETURN CV1($names)
  .sharp 0021 VERIFY_RETURN_TYPE
  .sharp 0022 RETURN null
  .sharp      8: 0009 - 0018 (loop)
  .sharp     Property [ private Sharp\Map $counts = Closure({closure:<file>:9})() ]
  .sharp     Property [ private Sharp\Map $seeded = Closure({closure:<file>:10})() ]
  .php   L0023 0003 JMPZ T2 0023
  .php   L0023 0004 V3 = NEW 1 string("Sharp\\Map")
  .php   L0023 0005 INIT_FCALL 1 96 string("array_merge")
  .php   L0023 0006 SEND_VAL array(...) 1
  .php   L0023 0007 INIT_FCALL 3 128 string("array_map")
  .php   L0023 0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0023 0009 SEND_VAL T4 1
  .php   L0023 0010 INIT_FCALL 1 96 string("array_keys")
  .php   L0023 0011 SEND_VAR CV0($counts) 1
  .php   L0023 0012 V5 = DO_ICALL
  .php   L0023 0013 SEND_VAR V5 2
  .php   L0023 0014 SEND_VAR CV0($counts) 3
  .php   L0023 0015 V6 = DO_ICALL
  .php   L0023 0016 SEND_UNPACK V6
  .php   L0023 0017 CHECK_UNDEF_ARGS
  .php   L0023 0018 V7 = DO_ICALL
  .php   L0023 0019 SEND_VAR_NO_REF_EX V7 1
  .php   L0023 0020 DO_FCALL
  .php   L0023 0021 T9 = QM_ASSIGN V3
  .php   L0023 0022 JMP 0024
  .php   L0023 0023 T9 = QM_ASSIGN CV0($counts)
  .php   L0023 0024 ASSIGN CV0($counts) T9
  .php   L0025 0025 T12 = FETCH_DIM_IS CV0($counts) CV1($status)
  .php   L0025 0026 T13 = COALESCE T12 0028
  .php   L0025 0027 T13 = QM_ASSIGN int(0)
  .php   L0025 0028 T14 = ADD T13 int(1)
  .php   L0025 0029 ASSIGN_DIM CV0($counts) CV1($status)
  .php   L0025 0030 OP_DATA T14
  .php   L0026 0031 VERIFY_RETURN_TYPE CV0($counts)
  .php   L0026 0032 RETURN CV0($counts)
  .php   L0027 0033 VERIFY_RETURN_TYPE
  .php   L0027 0034 RETURN null
  .php   L0023 0000 CV0($key) = RECV 1
  .php   L0023 0001 CV1($value) = RECV 2
  .php   L0023 0003 T2 = CAST (string) CV0($key)
  .php   L0023 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=35, args=2, vars=2, tmps=12)
  .php   L0029 0003 JMPZ T2 0023
  .php   L0029 0004 V3 = NEW 1 string("Sharp\\Map")
  .php   L0029 0005 INIT_FCALL 1 96 string("array_merge")
  .php   L0029 0006 SEND_VAL array(...) 1
  .php   L0029 0007 INIT_FCALL 3 128 string("array_map")
  .php   L0029 0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0029 0009 SEND_VAL T4 1
  .php   L0029 0010 INIT_FCALL 1 96 string("array_keys")
  .php   L0029 0011 SEND_VAR CV0($counts) 1
  .php   L0029 0012 V5 = DO_ICALL
  .php   L0029 0013 SEND_VAR V5 2
  .php   L0029 0014 SEND_VAR CV0($counts) 3
  .php   L0029 0015 V6 = DO_ICALL
  .php   L0029 0016 SEND_UNPACK V6
  .php   L0029 0017 CHECK_UNDEF_ARGS
  .php   L0029 0018 V7 = DO_ICALL
  .php   L0029 0019 SEND_VAR_NO_REF_EX V7 1
  .php   L0029 0020 DO_FCALL
  .php   L0029 0021 T9 = QM_ASSIGN V3
  .php   L0029 0022 JMP 0024
  .php   L0029 0023 T9 = QM_ASSIGN CV0($counts)
  .php   L0029 0024 ASSIGN CV0($counts) T9
  .php   L0031 0025 T11 = FETCH_DIM_IS CV0($counts) CV1($status)
  .php   L0031 0026 T12 = COALESCE T11 0030
  .php   L0031 0027 T13 = ASSIGN_DIM CV0($counts) CV1($status)
  .php   L0031 0028 OP_DATA int(0)
  .php   L0031 0029 T12 = QM_ASSIGN T13
  .php   L0031 0030 FREE T12
  .php   L0032 0031 VERIFY_RETURN_TYPE CV0($counts)
  .php   L0032 0032 RETURN CV0($counts)
  .php   L0033 0033 VERIFY_RETURN_TYPE
  .php   L0033 0034 RETURN null
  .php   L0029 0000 CV0($key) = RECV 1
  .php   L0029 0001 CV1($value) = RECV 2
  .php   L0029 0003 T2 = CAST (string) CV0($key)
  .php   L0029 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=13, args=2, vars=2, tmps=3)
  .php   L0037 0002 V2 = NEW 1 string("Sharp\\Map")
  .php   L0037 0003 T3 = INIT_ARRAY 4 (packed) CV0($small) NEXT
  .php   L0037 0004 T3 = ADD_ARRAY_ELEMENT string("small") NEXT
  .php   L0037 0005 T3 = ADD_ARRAY_ELEMENT CV1($large) NEXT
  .php   L0037 0006 T3 = ADD_ARRAY_ELEMENT string("large") NEXT
  .php   L0037 0007 SEND_VAL_EX T3 1
  .php   L0037 0008 DO_FCALL
  .php   L0037 0009 VERIFY_RETURN_TYPE V2
  .php   L0037 0010 RETURN V2
  .php   L0038 0011 VERIFY_RETURN_TYPE
  .php   L0038 0012 RETURN null
  .php        ; (lines=10, args=1, vars=2, tmps=4)
  .php   L0042 0001 V2 = NEW 0 string("Sharp\\Map")
  .php   L0042 0002 DO_FCALL
  .php   L0042 0003 ASSIGN CV1($codes) V2
  .php   L0043 0004 ASSIGN_DIM CV1($codes) CV0($code)
  .php   L0043 0005 OP_DATA int(5)
  .php   L0044 0006 VERIFY_RETURN_TYPE CV1($codes)
  .php   L0044 0007 RETURN CV1($codes)
  .php   L0045 0008 VERIFY_RETURN_TYPE
  .php   L0045 0009 RETURN null
  .php        ; (lines=33, args=2, vars=2, tmps=11)
  .php   L0047 0003 JMPZ T2 0023
  .php   L0047 0004 V3 = NEW 1 string("Sharp\\Map")
  .php   L0047 0005 INIT_FCALL 1 96 string("array_merge")
  .php   L0047 0006 SEND_VAL array(...) 1
  .php   L0047 0007 INIT_FCALL 3 128 string("array_map")
  .php   L0047 0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0047 0009 SEND_VAL T4 1
  .php   L0047 0010 INIT_FCALL 1 96 string("array_keys")
  .php   L0047 0011 SEND_VAR CV0($counts) 1
  .php   L0047 0012 V5 = DO_ICALL
  .php   L0047 0013 SEND_VAR V5 2
  .php   L0047 0014 SEND_VAR CV0($counts) 3
  .php   L0047 0015 V6 = DO_ICALL
  .php   L0047 0016 SEND_UNPACK V6
  .php   L0047 0017 CHECK_UNDEF_ARGS
  .php   L0047 0018 V7 = DO_ICALL
  .php   L0047 0019 SEND_VAR_NO_REF_EX V7 1
  .php   L0047 0020 DO_FCALL
  .php   L0047 0021 T9 = QM_ASSIGN V3
  .php   L0047 0022 JMP 0024
  .php   L0047 0023 T9 = QM_ASSIGN CV0($counts)
  .php   L0047 0024 ASSIGN CV0($counts) T9
  .php   L0049 0025 T11 = CAST (object) CV0($counts)
  .php   L0049 0026 INIT_METHOD_CALL 1 T11 string("get")
  .php   L0049 0027 SEND_VAR_EX CV1($status) 1
  .php   L0049 0028 V12 = DO_FCALL
  .php   L0049 0029 VERIFY_RETURN_TYPE V12
  .php   L0049 0030 RETURN V12
  .php   L0050 0031 VERIFY_RETURN_TYPE
  .php   L0050 0032 RETURN null
  .php   L0047 0000 CV0($key) = RECV 1
  .php   L0047 0001 CV1($value) = RECV 2
  .php   L0047 0003 T2 = CAST (string) CV0($key)
  .php   L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=33, args=2, vars=2, tmps=11)
  .php   L0052 0003 JMPZ T2 0023
  .php   L0052 0004 V3 = NEW 1 string("Sharp\\Map")
  .php   L0052 0005 INIT_FCALL 1 96 string("array_merge")
  .php   L0052 0006 SEND_VAL array(...) 1
  .php   L0052 0007 INIT_FCALL 3 128 string("array_map")
  .php   L0052 0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0052 0009 SEND_VAL T4 1
  .php   L0052 0010 INIT_FCALL 1 96 string("array_keys")
  .php   L0052 0011 SEND_VAR CV0($counts) 1
  .php   L0052 0012 V5 = DO_ICALL
  .php   L0052 0013 SEND_VAR V5 2
  .php   L0052 0014 SEND_VAR CV0($counts) 3
  .php   L0052 0015 V6 = DO_ICALL
  .php   L0052 0016 SEND_UNPACK V6
  .php   L0052 0017 CHECK_UNDEF_ARGS
  .php   L0052 0018 V7 = DO_ICALL
  .php   L0052 0019 SEND_VAR_NO_REF_EX V7 1
  .php   L0052 0020 DO_FCALL
  .php   L0052 0021 T9 = QM_ASSIGN V3
  .php   L0052 0022 JMP 0024
  .php   L0052 0023 T9 = QM_ASSIGN CV0($counts)
  .php   L0052 0024 ASSIGN CV0($counts) T9
  .php   L0054 0025 T11 = CAST (object) CV0($counts)
  .php   L0054 0026 INIT_METHOD_CALL 1 T11 string("remove")
  .php   L0054 0027 SEND_VAR_EX CV1($status) 1
  .php   L0054 0028 DO_FCALL
  .php   L0055 0029 VERIFY_RETURN_TYPE CV0($counts)
  .php   L0055 0030 RETURN CV0($counts)
  .php   L0056 0031 VERIFY_RETURN_TYPE
  .php   L0056 0032 RETURN null
  .php   L0052 0000 CV0($key) = RECV 1
  .php   L0052 0001 CV1($value) = RECV 2
  .php   L0052 0003 T2 = CAST (string) CV0($key)
  .php   L0052 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=16, args=2, vars=4, tmps=8)
  .php   L0060 0002 V4 = NEW 0 string("Sharp\\Map")
  .php   L0060 0003 DO_FCALL
  .php   L0060 0004 ASSIGN CV2($nested) V4
  .php   L0061 0005 V7 = NEW 0 string("Sharp\\Map")
  .php   L0061 0006 DO_FCALL
  .php   L0061 0007 ASSIGN CV3($sizes) V7
  .php   L0062 0008 ASSIGN_DIM CV3($sizes) CV1($size)
  .php   L0062 0009 OP_DATA int(1)
  .php   L0063 0010 ASSIGN_DIM CV2($nested) CV0($status)
  .php   L0063 0011 OP_DATA CV3($sizes)
  .php   L0064 0012 VERIFY_RETURN_TYPE CV2($nested)
  .php   L0064 0013 RETURN CV2($nested)
  .php   L0065 0014 VERIFY_RETURN_TYPE
  .php   L0065 0015 RETURN null
  .php        ; (lines=39, args=1, vars=4, tmps=17)
  .php   L0067 0002 JMPZ T4 0022
  .php   L0067 0003 V5 = NEW 1 string("Sharp\\Map")
  .php   L0067 0004 INIT_FCALL 1 96 string("array_merge")
  .php   L0067 0005 SEND_VAL array(...) 1
  .php   L0067 0006 INIT_FCALL 3 128 string("array_map")
  .php   L0067 0007 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0067 0008 SEND_VAL T6 1
  .php   L0067 0009 INIT_FCALL 1 96 string("array_keys")
  .php   L0067 0010 SEND_VAR CV0($counts) 1
  .php   L0067 0011 V7 = DO_ICALL
  .php   L0067 0012 SEND_VAR V7 2
  .php   L0067 0013 SEND_VAR CV0($counts) 3
  .php   L0067 0014 V8 = DO_ICALL
  .php   L0067 0015 SEND_UNPACK V8
  .php   L0067 0016 CHECK_UNDEF_ARGS
  .php   L0067 0017 V9 = DO_ICALL
  .php   L0067 0018 SEND_VAR_NO_REF_EX V9 1
  .php   L0067 0019 DO_FCALL
  .php   L0067 0020 T11 = QM_ASSIGN V5
  .php   L0067 0021 JMP 0023
  .php   L0067 0022 T11 = QM_ASSIGN CV0($counts)
  .php   L0067 0023 ASSIGN CV0($counts) T11
  .php   L0069 0024 V13 = NEW 0 string("Sharp\\Map")
  .php   L0069 0025 DO_FCALL
  .php   L0069 0026 ASSIGN CV1($named) V13
  .php   L0070 0027 V16 = FE_RESET_R CV0($counts) 0034
  .php   L0070 0028 T17 = FE_FETCH_R V16 CV2($n) 0034
  .php   L0070 0029 ASSIGN CV3($status) T17
  .php   L0071 0030 T19 = FETCH_OBJ_R CV3($status) string("name")
  .php   L0071 0031 ASSIGN_DIM CV1($named) T19
  .php   L0071 0032 OP_DATA CV2($n)
  .php   L0070 0033 JMP 0028
  .php   L0070 0034 FE_FREE V16
  .php   L0073 0035 VERIFY_RETURN_TYPE CV1($named)
  .php   L0073 0036 RETURN CV1($named)
  .php   L0074 0037 VERIFY_RETURN_TYPE
  .php   L0074 0038 RETURN null
  .php   L0067 0000 CV0($key) = RECV 1
  .php   L0067 0001 CV1($value) = RECV 2
  .php   L0067 0003 T2 = CAST (string) CV0($key)
  .php   L0067 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=40, args=1, vars=4, tmps=16)
  .php   L0076 0002 JMPZ T4 0022
  .php   L0076 0003 V5 = NEW 1 string("Sharp\\Map")
  .php   L0076 0004 INIT_FCALL 1 96 string("array_merge")
  .php   L0076 0005 SEND_VAL array(...) 1
  .php   L0076 0006 INIT_FCALL 3 128 string("array_map")
  .php   L0076 0007 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0076 0008 SEND_VAL T6 1
  .php   L0076 0009 INIT_FCALL 1 96 string("array_keys")
  .php   L0076 0010 SEND_VAR CV0($stock) 1
  .php   L0076 0011 V7 = DO_ICALL
  .php   L0076 0012 SEND_VAR V7 2
  .php   L0076 0013 SEND_VAR CV0($stock) 3
  .php   L0076 0014 V8 = DO_ICALL
  .php   L0076 0015 SEND_UNPACK V8
  .php   L0076 0016 CHECK_UNDEF_ARGS
  .php   L0076 0017 V9 = DO_ICALL
  .php   L0076 0018 SEND_VAR_NO_REF_EX V9 1
  .php   L0076 0019 DO_FCALL
  .php   L0076 0020 T11 = QM_ASSIGN V5
  .php   L0076 0021 JMP 0023
  .php   L0076 0022 T11 = QM_ASSIGN CV0($stock)
  .php   L0076 0023 ASSIGN CV0($stock) T11
  .php   L0078 0024 ASSIGN CV1($skus) array(...)
  .php   L0079 0025 V14 = FE_RESET_R CV0($stock) 0035
  .php   L0079 0026 T15 = FE_FETCH_R V14 CV2($n) 0035
  .php   L0079 0027 ASSIGN CV3($sku) T15
  .php   L0080 0028 T17 = IS_SMALLER int(0) CV2($n)
  .php   L0080 0029 JMPZ T17 0034
  .php   L0081 0030 T18 = CAST (object) CV1($skus)
  .php   L0081 0031 INIT_METHOD_CALL 1 T18 string("add")
  .php   L0081 0032 SEND_VAR_EX CV3($sku) 1
  .php   L0081 0033 DO_FCALL
  .php   L0079 0034 JMP 0026
  .php   L0079 0035 FE_FREE V14
  .php   L0084 0036 VERIFY_RETURN_TYPE CV1($skus)
  .php   L0084 0037 RETURN CV1($skus)
  .php   L0085 0038 VERIFY_RETURN_TYPE
  .php   L0085 0039 RETURN null
  .php   L0076 0000 CV0($key) = RECV 1
  .php   L0076 0001 CV1($value) = RECV 2
  .php   L0076 0002 T2 = CAST (string) CV0($key)
  .php   L0076 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=10, args=1, vars=1, tmps=3)
  .php   L0089 0002 INIT_METHOD_CALL 1 T1 string("groupBy")
  .php   L0089 0005 V3 = DO_FCALL
  .php   L0089 0006 VERIFY_RETURN_TYPE V3
  .php   L0089 0007 RETURN V3
  .php   L0090 0008 VERIFY_RETURN_TYPE
  .php   L0090 0009 RETURN null
  .php        ; (lines=22, args=1, vars=4, tmps=10)
  .php   L0095 0003 INIT_METHOD_CALL 1 T5 string("groupBy")
  .php   L0095 0006 V7 = DO_FCALL
  .php   L0095 0007 V8 = FE_RESET_R V7 0017
  .php   L0095 0008 T9 = FE_FETCH_R V8 CV2($group) 0017
  .php   L0095 0009 ASSIGN CV3($status) T9
  .php   L0096 0010 T11 = CAST (object) CV1($names)
  .php   L0096 0011 INIT_METHOD_CALL 1 T11 string("add")
  .php   L0096 0012 CHECK_FUNC_ARG 1
  .php   L0096 0013 V12 = FETCH_OBJ_FUNC_ARG (ref) CV3($status) string("name")
  .php   L0096 0014 SEND_FUNC_ARG V12 1
  .php   L0096 0015 DO_FCALL
  .php   L0095 0016 JMP 0008
  .php   L0095 0017 FE_FREE V8
  .php   L0098 0018 VERIFY_RETURN_TYPE CV1($names)
  .php   L0098 0019 RETURN CV1($names)
  .php   L0099 0020 VERIFY_RETURN_TYPE
  .php   L0099 0021 RETURN null
  .php   0003 JMPZ T2 0023
  .php   0004 V3 = NEW 1 string("Sharp\\Map")
  .php   0005 INIT_FCALL 1 96 string("array_merge")
  .php   0006 SEND_VAL array(...) 1
  .php   0007 INIT_FCALL 3 128 string("array_map")
  .php   0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   0009 SEND_VAL T4 1
  .php   0010 INIT_FCALL 1 96 string("array_keys")
  .php   0011 SEND_VAR CV0($counts) 1
  .php   0012 V5 = DO_ICALL
  .php   0013 SEND_VAR V5 2
  .php   0014 SEND_VAR CV0($counts) 3
  .php   0015 V6 = DO_ICALL
  .php   0016 SEND_UNPACK V6
  .php   0017 CHECK_UNDEF_ARGS
  .php   0018 V7 = DO_ICALL
  .php   0019 SEND_VAR_NO_REF_EX V7 1
  .php   0020 DO_FCALL
  .php   0021 T9 = QM_ASSIGN V3
  .php   0022 JMP 0024
  .php   0023 T9 = QM_ASSIGN CV0($counts)
  .php   0024 ASSIGN CV0($counts) T9
  .php   0025 T12 = FETCH_DIM_IS CV0($counts) CV1($status)
  .php   0026 T13 = COALESCE T12 0028
  .php   0027 T13 = QM_ASSIGN int(0)
  .php   0028 T14 = ADD T13 int(1)
  .php   0029 ASSIGN_DIM CV0($counts) CV1($status)
  .php   0030 OP_DATA T14
  .php        3: 0005 - 0021 (new)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=35, args=2, vars=2, tmps=12)
  .php   0003 JMPZ T2 0023
  .php   0004 V3 = NEW 1 string("Sharp\\Map")
  .php   0005 INIT_FCALL 1 96 string("array_merge")
  .php   0006 SEND_VAL array(...) 1
  .php   0007 INIT_FCALL 3 128 string("array_map")
  .php   0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   0009 SEND_VAL T4 1
  .php   0010 INIT_FCALL 1 96 string("array_keys")
  .php   0011 SEND_VAR CV0($counts) 1
  .php   0012 V5 = DO_ICALL
  .php   0013 SEND_VAR V5 2
  .php   0014 SEND_VAR CV0($counts) 3
  .php   0015 V6 = DO_ICALL
  .php   0016 SEND_UNPACK V6
  .php   0017 CHECK_UNDEF_ARGS
  .php   0018 V7 = DO_ICALL
  .php   0019 SEND_VAR_NO_REF_EX V7 1
  .php   0020 DO_FCALL
  .php   0021 T9 = QM_ASSIGN V3
  .php   0022 JMP 0024
  .php   0023 T9 = QM_ASSIGN CV0($counts)
  .php   0024 ASSIGN CV0($counts) T9
  .php   0025 T11 = FETCH_DIM_IS CV0($counts) CV1($status)
  .php   0026 T12 = COALESCE T11 0030
  .php   0027 T13 = ASSIGN_DIM CV0($counts) CV1($status)
  .php   0028 OP_DATA int(0)
  .php   0029 T12 = QM_ASSIGN T13
  .php   0030 FREE T12
  .php        3: 0005 - 0021 (new)
  .php        13: 0028 - 0029 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=13, args=2, vars=2, tmps=3)
  .php   0002 V2 = NEW 1 string("Sharp\\Map")
  .php   0003 T3 = INIT_ARRAY 4 (packed) CV0($small) NEXT
  .php   0004 T3 = ADD_ARRAY_ELEMENT string("small") NEXT
  .php   0005 T3 = ADD_ARRAY_ELEMENT CV1($large) NEXT
  .php   0006 T3 = ADD_ARRAY_ELEMENT string("large") NEXT
  .php   0007 SEND_VAL_EX T3 1
  .php   0008 DO_FCALL
  .php   0009 VERIFY_RETURN_TYPE V2
  .php   0010 RETURN V2
  .php   0011 VERIFY_RETURN_TYPE
  .php   0012 RETURN null
  .php        2: 0003 - 0009 (new)
  .php        3: 0004 - 0007 (tmp/var)
  .php        2: 0009 - 0010 (tmp/var)
  .php        ; (lines=10, args=1, vars=2, tmps=4)
  .php   0001 V2 = NEW 0 string("Sharp\\Map")
  .php   0002 DO_FCALL
  .php   0003 ASSIGN CV1($codes) V2
  .php   0004 ASSIGN_DIM CV1($codes) CV0($code)
  .php   0005 OP_DATA int(5)
  .php   0006 VERIFY_RETURN_TYPE CV1($codes)
  .php   0007 RETURN CV1($codes)
  .php   0008 VERIFY_RETURN_TYPE
  .php        2: 0002 - 0003 (new)
  .php        ; (lines=33, args=2, vars=2, tmps=11)
  .php   0003 JMPZ T2 0023
  .php   0004 V3 = NEW 1 string("Sharp\\Map")
  .php   0005 INIT_FCALL 1 96 string("array_merge")
  .php   0006 SEND_VAL array(...) 1
  .php   0007 INIT_FCALL 3 128 string("array_map")
  .php   0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   0009 SEND_VAL T4 1
  .php   0010 INIT_FCALL 1 96 string("array_keys")
  .php   0011 SEND_VAR CV0($counts) 1
  .php   0012 V5 = DO_ICALL
  .php   0013 SEND_VAR V5 2
  .php   0014 SEND_VAR CV0($counts) 3
  .php   0015 V6 = DO_ICALL
  .php   0016 SEND_UNPACK V6
  .php   0017 CHECK_UNDEF_ARGS
  .php   0018 V7 = DO_ICALL
  .php   0019 SEND_VAR_NO_REF_EX V7 1
  .php   0020 DO_FCALL
  .php   0021 T9 = QM_ASSIGN V3
  .php   0022 JMP 0024
  .php   0023 T9 = QM_ASSIGN CV0($counts)
  .php   0024 ASSIGN CV0($counts) T9
  .php   0025 T11 = CAST (object) CV0($counts)
  .php   0026 INIT_METHOD_CALL 1 T11 string("get")
  .php   0027 SEND_VAR_EX CV1($status) 1
  .php   0028 V12 = DO_FCALL
  .php   0029 VERIFY_RETURN_TYPE V12
  .php   0030 RETURN V12
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
  .php        3: 0005 - 0021 (new)
  .php        12: 0029 - 0030 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=33, args=2, vars=2, tmps=11)
  .php   0003 JMPZ T2 0023
  .php   0004 V3 = NEW 1 string("Sharp\\Map")
  .php   0005 INIT_FCALL 1 96 string("array_merge")
  .php   0006 SEND_VAL array(...) 1
  .php   0007 INIT_FCALL 3 128 string("array_map")
  .php   0008 T4 = DECLARE_LAMBDA_FUNCTION 0
  .php   0009 SEND_VAL T4 1
  .php   0010 INIT_FCALL 1 96 string("array_keys")
  .php   0011 SEND_VAR CV0($counts) 1
  .php   0012 V5 = DO_ICALL
  .php   0013 SEND_VAR V5 2
  .php   0014 SEND_VAR CV0($counts) 3
  .php   0015 V6 = DO_ICALL
  .php   0016 SEND_UNPACK V6
  .php   0017 CHECK_UNDEF_ARGS
  .php   0018 V7 = DO_ICALL
  .php   0019 SEND_VAR_NO_REF_EX V7 1
  .php   0020 DO_FCALL
  .php   0021 T9 = QM_ASSIGN V3
  .php   0022 JMP 0024
  .php   0023 T9 = QM_ASSIGN CV0($counts)
  .php   0024 ASSIGN CV0($counts) T9
  .php   0025 T11 = CAST (object) CV0($counts)
  .php   0026 INIT_METHOD_CALL 1 T11 string("remove")
  .php   0027 SEND_VAR_EX CV1($status) 1
  .php   0028 DO_FCALL
  .php   0029 VERIFY_RETURN_TYPE CV0($counts)
  .php   0030 RETURN CV0($counts)
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
  .php        3: 0005 - 0021 (new)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=16, args=2, vars=4, tmps=8)
  .php   0002 V4 = NEW 0 string("Sharp\\Map")
  .php   0003 DO_FCALL
  .php   0004 ASSIGN CV2($nested) V4
  .php   0005 V7 = NEW 0 string("Sharp\\Map")
  .php   0006 DO_FCALL
  .php   0007 ASSIGN CV3($sizes) V7
  .php   0008 ASSIGN_DIM CV3($sizes) CV1($size)
  .php   0009 OP_DATA int(1)
  .php   0010 ASSIGN_DIM CV2($nested) CV0($status)
  .php   0011 OP_DATA CV3($sizes)
  .php   0012 VERIFY_RETURN_TYPE CV2($nested)
  .php   0013 RETURN CV2($nested)
  .php   0014 VERIFY_RETURN_TYPE
  .php   0015 RETURN null
  .php        4: 0003 - 0004 (new)
  .php        7: 0006 - 0007 (new)
  .php        ; (lines=39, args=1, vars=4, tmps=17)
  .php   0002 JMPZ T4 0022
  .php   0003 V5 = NEW 1 string("Sharp\\Map")
  .php   0004 INIT_FCALL 1 96 string("array_merge")
  .php   0005 SEND_VAL array(...) 1
  .php   0006 INIT_FCALL 3 128 string("array_map")
  .php   0007 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   0008 SEND_VAL T6 1
  .php   0009 INIT_FCALL 1 96 string("array_keys")
  .php   0010 SEND_VAR CV0($counts) 1
  .php   0011 V7 = DO_ICALL
  .php   0012 SEND_VAR V7 2
  .php   0013 SEND_VAR CV0($counts) 3
  .php   0014 V8 = DO_ICALL
  .php   0015 SEND_UNPACK V8
  .php   0016 CHECK_UNDEF_ARGS
  .php   0017 V9 = DO_ICALL
  .php   0018 SEND_VAR_NO_REF_EX V9 1
  .php   0019 DO_FCALL
  .php   0020 T11 = QM_ASSIGN V5
  .php   0021 JMP 0023
  .php   0022 T11 = QM_ASSIGN CV0($counts)
  .php   0023 ASSIGN CV0($counts) T11
  .php   0024 V13 = NEW 0 string("Sharp\\Map")
  .php   0025 DO_FCALL
  .php   0026 ASSIGN CV1($named) V13
  .php   0027 V16 = FE_RESET_R CV0($counts) 0034
  .php   0028 T17 = FE_FETCH_R V16 CV2($n) 0034
  .php   0029 ASSIGN CV3($status) T17
  .php   0030 T19 = FETCH_OBJ_R CV3($status) string("name")
  .php   0031 ASSIGN_DIM CV1($named) T19
  .php   0032 OP_DATA CV2($n)
  .php   0033 JMP 0028
  .php   0034 FE_FREE V16
  .php   0035 VERIFY_RETURN_TYPE CV1($named)
  .php   0036 RETURN CV1($named)
  .php   0037 VERIFY_RETURN_TYPE
  .php   0038 RETURN null
  .php        5: 0004 - 0020 (new)
  .php        13: 0025 - 0026 (new)
  .php        16: 0028 - 0034 (loop)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=40, args=1, vars=4, tmps=16)
  .php   0002 JMPZ T4 0022
  .php   0003 V5 = NEW 1 string("Sharp\\Map")
  .php   0004 INIT_FCALL 1 96 string("array_merge")
  .php   0005 SEND_VAL array(...) 1
  .php   0006 INIT_FCALL 3 128 string("array_map")
  .php   0007 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   0008 SEND_VAL T6 1
  .php   0009 INIT_FCALL 1 96 string("array_keys")
  .php   0010 SEND_VAR CV0($stock) 1
  .php   0011 V7 = DO_ICALL
  .php   0012 SEND_VAR V7 2
  .php   0013 SEND_VAR CV0($stock) 3
  .php   0014 V8 = DO_ICALL
  .php   0015 SEND_UNPACK V8
  .php   0016 CHECK_UNDEF_ARGS
  .php   0017 V9 = DO_ICALL
  .php   0018 SEND_VAR_NO_REF_EX V9 1
  .php   0019 DO_FCALL
  .php   0020 T11 = QM_ASSIGN V5
  .php   0021 JMP 0023
  .php   0022 T11 = QM_ASSIGN CV0($stock)
  .php   0023 ASSIGN CV0($stock) T11
  .php   0024 ASSIGN CV1($skus) array(...)
  .php   0025 V14 = FE_RESET_R CV0($stock) 0035
  .php   0026 T15 = FE_FETCH_R V14 CV2($n) 0035
  .php   0027 ASSIGN CV3($sku) T15
  .php   0028 T17 = IS_SMALLER int(0) CV2($n)
  .php   0029 JMPZ T17 0034
  .php   0030 T18 = CAST (object) CV1($skus)
  .php   0031 INIT_METHOD_CALL 1 T18 string("add")
  .php   0032 SEND_VAR_EX CV3($sku) 1
  .php   0033 DO_FCALL
  .php   0034 JMP 0026
  .php   0035 FE_FREE V14
  .php   0036 VERIFY_RETURN_TYPE CV1($skus)
  .php   0037 RETURN CV1($skus)
  .php   0038 VERIFY_RETURN_TYPE
  .php   0039 RETURN null
  .php        5: 0004 - 0020 (new)
  .php        14: 0026 - 0035 (loop)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=10, args=1, vars=1, tmps=3)
  .php   0002 INIT_METHOD_CALL 1 T1 string("groupBy")
  .php   0006 VERIFY_RETURN_TYPE V3
  .php   0007 RETURN V3
  .php   0008 VERIFY_RETURN_TYPE
  .php        3: 0006 - 0007 (tmp/var)
  .php        ; (lines=22, args=1, vars=4, tmps=10)
  .php   0003 INIT_METHOD_CALL 1 T5 string("groupBy")
  .php   0006 V7 = DO_FCALL
  .php   0007 V8 = FE_RESET_R V7 0017
  .php   0008 T9 = FE_FETCH_R V8 CV2($group) 0017
  .php   0009 ASSIGN CV3($status) T9
  .php   0010 T11 = CAST (object) CV1($names)
  .php   0011 INIT_METHOD_CALL 1 T11 string("add")
  .php   0012 CHECK_FUNC_ARG 1
  .php   0013 V12 = FETCH_OBJ_FUNC_ARG (ref) CV3($status) string("name")
  .php   0014 SEND_FUNC_ARG V12 1
  .php   0015 DO_FCALL
  .php   0016 JMP 0008
  .php   0017 FE_FREE V8
  .php   0018 VERIFY_RETURN_TYPE CV1($names)
  .php   0019 RETURN CV1($names)
  .php   0020 VERIFY_RETURN_TYPE
  .php   0021 RETURN null
  .php        8: 0008 - 0017 (loop)
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
  .sharp      ; (lines=123, args=2, vars=5, tmps=53)
  .sharp L0045 0003 JMPZ T5 0025
  .sharp L0045 0004 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0045 0005 T7 = SHARP_TYPE_ARGS string("string, int")
  .sharp L0045 0006 V8 = NEW 1 V6 T7
  .sharp L0045 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0045 0008 SEND_VAL array(...) 1
  .sharp L0045 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0045 0010 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0045 0011 SEND_VAL T9 1
  .sharp L0045 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0045 0013 SEND_VAR CV1($prices) 1
  .sharp L0045 0014 V10 = DO_ICALL
  .sharp L0045 0015 SEND_VAR V10 2
  .sharp L0045 0016 SEND_VAR CV1($prices) 3
  .sharp L0045 0017 V11 = DO_ICALL
  .sharp L0045 0018 SEND_UNPACK V11
  .sharp L0045 0019 CHECK_UNDEF_ARGS
  .sharp L0045 0020 V12 = DO_ICALL
  .sharp L0045 0021 SEND_VAR_NO_REF_EX V12 1
  .sharp L0045 0022 DO_FCALL
  .sharp L0045 0023 T14 = QM_ASSIGN V8
  .sharp L0045 0024 JMP 0026
  .sharp L0045 0025 T14 = QM_ASSIGN CV1($prices)
  .sharp L0045 0026 ASSIGN CV1($prices) T14
  .sharp L0047 0027 T16 = CAST (object) CV0($numbers)
  .sharp L0047 0028 INIT_METHOD_CALL 1 T16 string("filter")
  .sharp L0047 0029 T17 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0047 0030 SEND_VAL_EX T17 1
  .sharp L0047 0031 V18 = DO_FCALL
  .sharp L0047 0032 ASSIGN CV2($odd) V18
  .sharp L0048 0033 T20 = CAST (object) CV2($odd)
  .sharp L0048 0034 INIT_METHOD_CALL 1 T20 string("map")
  .sharp L0048 0035 T21 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0048 0036 SEND_VAL_EX T21 1
  .sharp L0048 0037 V22 = DO_FCALL
  .sharp L0048 0038 ASSIGN CV3($labels) V22
  .sharp L0049 0039 T24 = CAST (object) CV0($numbers)
  .sharp L0049 0040 INIT_METHOD_CALL 2 T24 string("groupBy")
  .sharp L0049 0041 T25 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0049 0042 SEND_VAL_EX T25 1
  .sharp L0049 0043 SEND_VAL_EX string("string, List<int>") 2
  .sharp L0049 0044 V26 = DO_FCALL
  .sharp L0049 0045 ASSIGN CV4($groups) V26
  .sharp L0051 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0051 0047 SEND_VAL_EX string(",") 1
  .sharp L0051 0048 SEND_VAR_EX CV3($labels) 2
  .sharp L0051 0049 V28 = DO_FCALL
  .sharp L0051 0050 T29 = INIT_ARRAY 7 (packed) V28 NEXT
  .sharp L0052 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0052 0052 SEND_VAL_EX string(",") 1
  .sharp L0052 0053 T30 = CAST (object) CV0($numbers)
  .sharp L0052 0054 INIT_METHOD_CALL 1 T30 string("sortedBy")
  .sharp L0052 0055 T31 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0052 0056 SEND_VAL_EX T31 1
  .sharp L0052 0057 V32 = DO_FCALL
  .sharp L0052 0058 SEND_VAR_NO_REF_EX V32 2
  .sharp L0052 0059 V33 = DO_FCALL
  .sharp L0052 0060 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp L0053 0061 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0053 0062 SEND_VAL_EX string(",") 1
  .sharp L0053 0063 T34 = FETCH_DIM_IS CV4($groups) string("odd")
  .sharp L0053 0064 T35 = COALESCE T34 0066
  .sharp L0053 0065 T35 = QM_ASSIGN array(...)
  .sharp L0053 0066 SEND_VAL_EX T35 2
  .sharp L0053 0067 V36 = DO_FCALL
  .sharp L0053 0068 T29 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp L0054 0069 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0054 0070 SEND_VAL_EX string(",") 1
  .sharp L0054 0071 T37 = CAST (object) CV3($labels)
  .sharp L0054 0072 INIT_METHOD_CALL 2 T37 string("associateBy")
  .sharp L0054 0073 T38 = DECLARE_LAMBDA_FUNCTION 5
  .sharp L0054 0074 SEND_VAL_EX T38 1
  .sharp L0054 0075 SEND_VAL_EX string("string, string") 2
  .sharp L0054 0076 V39 = DO_FCALL
  .sharp L0054 0077 T40 = CAST (object) V39
  .sharp L0054 0078 INIT_METHOD_CALL 0 T40 string("keys")
  .sharp L0054 0079 V41 = DO_FCALL
  .sharp L0054 0080 SEND_VAR_NO_REF_EX V41 2
  .sharp L0054 0081 V42 = DO_FCALL
  .sharp L0054 0082 T29 = ADD_ARRAY_ELEMENT V42 NEXT
  .sharp L0055 0083 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0055 0084 SEND_VAL_EX string(",") 1
  .sharp L0055 0085 T43 = CAST (object) CV1($prices)
  .sharp L0055 0086 INIT_METHOD_CALL 1 T43 string("filter")
  .sharp L0055 0087 T44 = DECLARE_LAMBDA_FUNCTION 6
  .sharp L0055 0088 SEND_VAL_EX T44 1
  .sharp L0055 0089 V45 = DO_FCALL
  .sharp L0055 0090 T46 = CAST (object) V45
  .sharp L0055 0091 INIT_METHOD_CALL 0 T46 string("keys")
  .sharp L0055 0092 V47 = DO_FCALL
  .sharp L0055 0093 SEND_VAR_NO_REF_EX V47 2
  .sharp L0055 0094 V48 = DO_FCALL
  .sharp L0055 0095 T29 = ADD_ARRAY_ELEMENT V48 NEXT
  .sharp L0056 0096 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0056 0097 SEND_VAL_EX string(",") 1
  .sharp L0056 0098 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp L0056 0099 T49 = CAST (object) CV1($prices)
  .sharp L0056 0100 INIT_METHOD_CALL 1 T49 string("filter")
  .sharp L0056 0101 T50 = DECLARE_LAMBDA_FUNCTION 7
  .sharp L0056 0102 SEND_VAL_EX T50 1
  .sharp L0056 0104 SEND_VAR V51 1
  .sharp L0056 0105 V52 = DO_ICALL
  .sharp L0056 0106 SEND_VAR_NO_REF_EX V52 2
  .sharp L0056 0107 V53 = DO_FCALL
  .sharp L0056 0108 T29 = ADD_ARRAY_ELEMENT V53 NEXT
  .sharp L0057 0109 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0057 0110 SEND_VAL_EX string(",") 1
  .sharp L0057 0111 T54 = CAST (object) CV1($prices)
  .sharp L0057 0112 INIT_METHOD_CALL 1 T54 string("map")
  .sharp L0057 0113 T55 = DECLARE_LAMBDA_FUNCTION 8
  .sharp L0057 0114 SEND_VAL_EX T55 1
  .sharp L0057 0115 V56 = DO_FCALL
  .sharp L0057 0116 SEND_VAR_NO_REF_EX V56 2
  .sharp L0057 0117 V57 = DO_FCALL
  .sharp L0057 0118 T29 = ADD_ARRAY_ELEMENT V57 NEXT
  .sharp L0057 0119 VERIFY_RETURN_TYPE T29
  .sharp L0057 0120 RETURN T29
  .sharp L0059 0121 VERIFY_RETURN_TYPE
  .sharp L0059 0122 RETURN null
  .sharp L0045 0000 CV0($key#1) = RECV 1
  .sharp L0045 0001 CV1($value#2) = RECV 2
  .sharp L0045 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0045 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=123, args=2, vars=5, tmps=53)
  .sharp 0003 JMPZ T5 0025
  .sharp 0004 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T7 = SHARP_TYPE_ARGS string("string, int")
  .sharp 0006 V8 = NEW 1 V6 T7
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T9 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV1($prices) 1
  .sharp 0014 V10 = DO_ICALL
  .sharp 0015 SEND_VAR V10 2
  .sharp 0016 SEND_VAR CV1($prices) 3
  .sharp 0017 V11 = DO_ICALL
  .sharp 0018 SEND_UNPACK V11
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V12 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V12 1
  .sharp 0023 T14 = QM_ASSIGN V8
  .sharp 0024 JMP 0026
  .sharp 0025 T14 = QM_ASSIGN CV1($prices)
  .sharp 0026 ASSIGN CV1($prices) T14
  .sharp 0027 T16 = CAST (object) CV0($numbers)
  .sharp 0028 INIT_METHOD_CALL 1 T16 string("filter")
  .sharp 0029 T17 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0030 SEND_VAL_EX T17 1
  .sharp 0031 V18 = DO_FCALL
  .sharp 0032 ASSIGN CV2($odd) V18
  .sharp 0033 T20 = CAST (object) CV2($odd)
  .sharp 0034 INIT_METHOD_CALL 1 T20 string("map")
  .sharp 0035 T21 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0036 SEND_VAL_EX T21 1
  .sharp 0037 V22 = DO_FCALL
  .sharp 0038 ASSIGN CV3($labels) V22
  .sharp 0039 T24 = CAST (object) CV0($numbers)
  .sharp 0040 INIT_METHOD_CALL 2 T24 string("groupBy")
  .sharp 0041 T25 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0042 SEND_VAL_EX T25 1
  .sharp 0043 SEND_VAL_EX string("string, List<int>") 2
  .sharp 0044 V26 = DO_FCALL
  .sharp 0045 ASSIGN CV4($groups) V26
  .sharp 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0047 SEND_VAL_EX string(",") 1
  .sharp 0048 SEND_VAR_EX CV3($labels) 2
  .sharp 0049 V28 = DO_FCALL
  .sharp 0050 T29 = INIT_ARRAY 7 (packed) V28 NEXT
  .sharp 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0052 SEND_VAL_EX string(",") 1
  .sharp 0053 T30 = CAST (object) CV0($numbers)
  .sharp 0054 INIT_METHOD_CALL 1 T30 string("sortedBy")
  .sharp 0055 T31 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0056 SEND_VAL_EX T31 1
  .sharp 0057 V32 = DO_FCALL
  .sharp 0058 SEND_VAR_NO_REF_EX V32 2
  .sharp 0059 V33 = DO_FCALL
  .sharp 0060 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp 0061 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0062 SEND_VAL_EX string(",") 1
  .sharp 0063 T34 = FETCH_DIM_IS CV4($groups) string("odd")
  .sharp 0064 T35 = COALESCE T34 0066
  .sharp 0065 T35 = QM_ASSIGN array(...)
  .sharp 0066 SEND_VAL_EX T35 2
  .sharp 0067 V36 = DO_FCALL
  .sharp 0068 T29 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp 0069 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0070 SEND_VAL_EX string(",") 1
  .sharp 0071 T37 = CAST (object) CV3($labels)
  .sharp 0072 INIT_METHOD_CALL 2 T37 string("associateBy")
  .sharp 0073 T38 = DECLARE_LAMBDA_FUNCTION 5
  .sharp 0074 SEND_VAL_EX T38 1
  .sharp 0075 SEND_VAL_EX string("string, string") 2
  .sharp 0076 V39 = DO_FCALL
  .sharp 0077 T40 = CAST (object) V39
  .sharp 0078 INIT_METHOD_CALL 0 T40 string("keys")
  .sharp 0079 V41 = DO_FCALL
  .sharp 0080 SEND_VAR_NO_REF_EX V41 2
  .sharp 0081 V42 = DO_FCALL
  .sharp 0082 T29 = ADD_ARRAY_ELEMENT V42 NEXT
  .sharp 0083 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0084 SEND_VAL_EX string(",") 1
  .sharp 0085 T43 = CAST (object) CV1($prices)
  .sharp 0086 INIT_METHOD_CALL 1 T43 string("filter")
  .sharp 0087 T44 = DECLARE_LAMBDA_FUNCTION 6
  .sharp 0088 SEND_VAL_EX T44 1
  .sharp 0089 V45 = DO_FCALL
  .sharp 0090 T46 = CAST (object) V45
  .sharp 0091 INIT_METHOD_CALL 0 T46 string("keys")
  .sharp 0092 V47 = DO_FCALL
  .sharp 0093 SEND_VAR_NO_REF_EX V47 2
  .sharp 0094 V48 = DO_FCALL
  .sharp 0095 T29 = ADD_ARRAY_ELEMENT V48 NEXT
  .sharp 0096 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0097 SEND_VAL_EX string(",") 1
  .sharp 0098 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp 0099 T49 = CAST (object) CV1($prices)
  .sharp 0100 INIT_METHOD_CALL 1 T49 string("filter")
  .sharp 0101 T50 = DECLARE_LAMBDA_FUNCTION 7
  .sharp 0102 SEND_VAL_EX T50 1
  .sharp 0104 SEND_VAR V51 1
  .sharp 0105 V52 = DO_ICALL
  .sharp 0106 SEND_VAR_NO_REF_EX V52 2
  .sharp 0107 V53 = DO_FCALL
  .sharp 0108 T29 = ADD_ARRAY_ELEMENT V53 NEXT
  .sharp 0109 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0110 SEND_VAL_EX string(",") 1
  .sharp 0111 T54 = CAST (object) CV1($prices)
  .sharp 0112 INIT_METHOD_CALL 1 T54 string("map")
  .sharp 0113 T55 = DECLARE_LAMBDA_FUNCTION 8
  .sharp 0114 SEND_VAL_EX T55 1
  .sharp 0115 V56 = DO_FCALL
  .sharp 0116 SEND_VAR_NO_REF_EX V56 2
  .sharp 0117 V57 = DO_FCALL
  .sharp 0118 T29 = ADD_ARRAY_ELEMENT V57 NEXT
  .sharp 0119 VERIFY_RETURN_TYPE T29
  .sharp 0120 RETURN T29
  .sharp 0121 VERIFY_RETURN_TYPE
  .sharp 0122 RETURN null
  .sharp      8: 0007 - 0023 (new)
  .sharp      29: 0051 - 0120 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=119, args=2, vars=5, tmps=51)
  .php   L0045 0003 JMPZ T5 0023
  .php   L0045 0004 V6 = NEW 1 string("Sharp\\Map")
  .php   L0045 0005 INIT_FCALL 1 96 string("array_merge")
  .php   L0045 0006 SEND_VAL array(...) 1
  .php   L0045 0007 INIT_FCALL 3 128 string("array_map")
  .php   L0045 0008 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0045 0009 SEND_VAL T7 1
  .php   L0045 0010 INIT_FCALL 1 96 string("array_keys")
  .php   L0045 0011 SEND_VAR CV1($prices) 1
  .php   L0045 0012 V8 = DO_ICALL
  .php   L0045 0013 SEND_VAR V8 2
  .php   L0045 0014 SEND_VAR CV1($prices) 3
  .php   L0045 0015 V9 = DO_ICALL
  .php   L0045 0016 SEND_UNPACK V9
  .php   L0045 0017 CHECK_UNDEF_ARGS
  .php   L0045 0018 V10 = DO_ICALL
  .php   L0045 0019 SEND_VAR_NO_REF_EX V10 1
  .php   L0045 0020 DO_FCALL
  .php   L0045 0021 T12 = QM_ASSIGN V6
  .php   L0045 0022 JMP 0024
  .php   L0045 0023 T12 = QM_ASSIGN CV1($prices)
  .php   L0045 0024 ASSIGN CV1($prices) T12
  .php   L0047 0025 T14 = CAST (object) CV0($numbers)
  .php   L0047 0026 INIT_METHOD_CALL 1 T14 string("filter")
  .php   L0047 0027 T15 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0047 0028 SEND_VAL_EX T15 1
  .php   L0047 0029 V16 = DO_FCALL
  .php   L0047 0030 ASSIGN CV2($odd) V16
  .php   L0048 0031 T18 = CAST (object) CV2($odd)
  .php   L0048 0032 INIT_METHOD_CALL 1 T18 string("map")
  .php   L0048 0033 T19 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0048 0034 SEND_VAL_EX T19 1
  .php   L0048 0035 V20 = DO_FCALL
  .php   L0048 0036 ASSIGN CV3($labels) V20
  .php   L0049 0037 T22 = CAST (object) CV0($numbers)
  .php   L0049 0038 INIT_METHOD_CALL 1 T22 string("groupBy")
  .php   L0049 0039 T23 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0049 0040 SEND_VAL_EX T23 1
  .php   L0049 0041 V24 = DO_FCALL
  .php   L0049 0042 ASSIGN CV4($groups) V24
  .php   L0051 0043 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0051 0044 SEND_VAL_EX string(",") 1
  .php   L0051 0045 SEND_VAR_EX CV3($labels) 2
  .php   L0051 0046 V26 = DO_FCALL
  .php   L0051 0047 T27 = INIT_ARRAY 7 (packed) V26 NEXT
  .php   L0052 0048 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0052 0049 SEND_VAL_EX string(",") 1
  .php   L0052 0050 T28 = CAST (object) CV0($numbers)
  .php   L0052 0051 INIT_METHOD_CALL 1 T28 string("sortedBy")
  .php   L0052 0052 T29 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0052 0053 SEND_VAL_EX T29 1
  .php   L0052 0054 V30 = DO_FCALL
  .php   L0052 0055 SEND_VAR_NO_REF_EX V30 2
  .php   L0052 0056 V31 = DO_FCALL
  .php   L0052 0057 T27 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   L0053 0058 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0053 0059 SEND_VAL_EX string(",") 1
  .php   L0053 0060 T32 = FETCH_DIM_IS CV4($groups) string("odd")
  .php   L0053 0061 T33 = COALESCE T32 0063
  .php   L0053 0062 T33 = QM_ASSIGN array(...)
  .php   L0053 0063 SEND_VAL_EX T33 2
  .php   L0053 0064 V34 = DO_FCALL
  .php   L0053 0065 T27 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   L0054 0066 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0054 0067 SEND_VAL_EX string(",") 1
  .php   L0054 0068 T35 = CAST (object) CV3($labels)
  .php   L0054 0069 INIT_METHOD_CALL 1 T35 string("associateBy")
  .php   L0054 0070 T36 = DECLARE_LAMBDA_FUNCTION 5
  .php   L0054 0071 SEND_VAL_EX T36 1
  .php   L0054 0072 V37 = DO_FCALL
  .php   L0054 0073 T38 = CAST (object) V37
  .php   L0054 0074 INIT_METHOD_CALL 0 T38 string("keys")
  .php   L0054 0075 V39 = DO_FCALL
  .php   L0054 0076 SEND_VAR_NO_REF_EX V39 2
  .php   L0054 0077 V40 = DO_FCALL
  .php   L0054 0078 T27 = ADD_ARRAY_ELEMENT V40 NEXT
  .php   L0055 0079 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0055 0080 SEND_VAL_EX string(",") 1
  .php   L0055 0081 T41 = CAST (object) CV1($prices)
  .php   L0055 0082 INIT_METHOD_CALL 1 T41 string("filter")
  .php   L0055 0083 T42 = DECLARE_LAMBDA_FUNCTION 6
  .php   L0055 0084 SEND_VAL_EX T42 1
  .php   L0055 0085 V43 = DO_FCALL
  .php   L0055 0086 T44 = CAST (object) V43
  .php   L0055 0087 INIT_METHOD_CALL 0 T44 string("keys")
  .php   L0055 0088 V45 = DO_FCALL
  .php   L0055 0089 SEND_VAR_NO_REF_EX V45 2
  .php   L0055 0090 V46 = DO_FCALL
  .php   L0055 0091 T27 = ADD_ARRAY_ELEMENT V46 NEXT
  .php   L0056 0092 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0056 0093 SEND_VAL_EX string(",") 1
  .php   L0056 0094 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   L0056 0095 T47 = CAST (object) CV1($prices)
  .php   L0056 0096 INIT_METHOD_CALL 1 T47 string("filter")
  .php   L0056 0097 T48 = DECLARE_LAMBDA_FUNCTION 7
  .php   L0056 0098 SEND_VAL_EX T48 1
  .php   L0056 0099 V49 = DO_FCALL
  .php   L0056 0100 SEND_VAR V49 1
  .php   L0056 0101 V50 = DO_ICALL
  .php   L0056 0102 SEND_VAR_NO_REF_EX V50 2
  .php   L0056 0104 T27 = ADD_ARRAY_ELEMENT V51 NEXT
  .php   L0057 0105 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0057 0106 SEND_VAL_EX string(",") 1
  .php   L0057 0107 T52 = CAST (object) CV1($prices)
  .php   L0057 0108 INIT_METHOD_CALL 1 T52 string("map")
  .php   L0057 0109 T53 = DECLARE_LAMBDA_FUNCTION 8
  .php   L0057 0110 SEND_VAL_EX T53 1
  .php   L0057 0111 V54 = DO_FCALL
  .php   L0057 0112 SEND_VAR_NO_REF_EX V54 2
  .php   L0057 0113 V55 = DO_FCALL
  .php   L0057 0114 T27 = ADD_ARRAY_ELEMENT V55 NEXT
  .php   L0057 0115 VERIFY_RETURN_TYPE T27
  .php   L0057 0116 RETURN T27
  .php   L0059 0117 VERIFY_RETURN_TYPE
  .php   L0059 0118 RETURN null
  .php   L0045 0000 CV0($key) = RECV 1
  .php   L0045 0001 CV1($value) = RECV 2
  .php   L0045 0002 T2 = CAST (string) CV0($key)
  .php   L0045 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=119, args=2, vars=5, tmps=51)
  .php   0003 JMPZ T5 0023
  .php   0004 V6 = NEW 1 string("Sharp\\Map")
  .php   0005 INIT_FCALL 1 96 string("array_merge")
  .php   0006 SEND_VAL array(...) 1
  .php   0007 INIT_FCALL 3 128 string("array_map")
  .php   0008 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   0009 SEND_VAL T7 1
  .php   0010 INIT_FCALL 1 96 string("array_keys")
  .php   0011 SEND_VAR CV1($prices) 1
  .php   0012 V8 = DO_ICALL
  .php   0013 SEND_VAR V8 2
  .php   0014 SEND_VAR CV1($prices) 3
  .php   0015 V9 = DO_ICALL
  .php   0016 SEND_UNPACK V9
  .php   0017 CHECK_UNDEF_ARGS
  .php   0018 V10 = DO_ICALL
  .php   0019 SEND_VAR_NO_REF_EX V10 1
  .php   0021 T12 = QM_ASSIGN V6
  .php   0022 JMP 0024
  .php   0023 T12 = QM_ASSIGN CV1($prices)
  .php   0024 ASSIGN CV1($prices) T12
  .php   0025 T14 = CAST (object) CV0($numbers)
  .php   0026 INIT_METHOD_CALL 1 T14 string("filter")
  .php   0027 T15 = DECLARE_LAMBDA_FUNCTION 1
  .php   0028 SEND_VAL_EX T15 1
  .php   0029 V16 = DO_FCALL
  .php   0030 ASSIGN CV2($odd) V16
  .php   0031 T18 = CAST (object) CV2($odd)
  .php   0032 INIT_METHOD_CALL 1 T18 string("map")
  .php   0033 T19 = DECLARE_LAMBDA_FUNCTION 2
  .php   0034 SEND_VAL_EX T19 1
  .php   0035 V20 = DO_FCALL
  .php   0036 ASSIGN CV3($labels) V20
  .php   0037 T22 = CAST (object) CV0($numbers)
  .php   0038 INIT_METHOD_CALL 1 T22 string("groupBy")
  .php   0039 T23 = DECLARE_LAMBDA_FUNCTION 3
  .php   0040 SEND_VAL_EX T23 1
  .php   0041 V24 = DO_FCALL
  .php   0042 ASSIGN CV4($groups) V24
  .php   0043 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0044 SEND_VAL_EX string(",") 1
  .php   0045 SEND_VAR_EX CV3($labels) 2
  .php   0046 V26 = DO_FCALL
  .php   0047 T27 = INIT_ARRAY 7 (packed) V26 NEXT
  .php   0048 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0049 SEND_VAL_EX string(",") 1
  .php   0050 T28 = CAST (object) CV0($numbers)
  .php   0051 INIT_METHOD_CALL 1 T28 string("sortedBy")
  .php   0052 T29 = DECLARE_LAMBDA_FUNCTION 4
  .php   0053 SEND_VAL_EX T29 1
  .php   0054 V30 = DO_FCALL
  .php   0055 SEND_VAR_NO_REF_EX V30 2
  .php   0056 V31 = DO_FCALL
  .php   0057 T27 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   0058 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0059 SEND_VAL_EX string(",") 1
  .php   0060 T32 = FETCH_DIM_IS CV4($groups) string("odd")
  .php   0061 T33 = COALESCE T32 0063
  .php   0062 T33 = QM_ASSIGN array(...)
  .php   0063 SEND_VAL_EX T33 2
  .php   0064 V34 = DO_FCALL
  .php   0065 T27 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   0066 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0067 SEND_VAL_EX string(",") 1
  .php   0068 T35 = CAST (object) CV3($labels)
  .php   0069 INIT_METHOD_CALL 1 T35 string("associateBy")
  .php   0070 T36 = DECLARE_LAMBDA_FUNCTION 5
  .php   0071 SEND_VAL_EX T36 1
  .php   0072 V37 = DO_FCALL
  .php   0073 T38 = CAST (object) V37
  .php   0074 INIT_METHOD_CALL 0 T38 string("keys")
  .php   0075 V39 = DO_FCALL
  .php   0076 SEND_VAR_NO_REF_EX V39 2
  .php   0077 V40 = DO_FCALL
  .php   0078 T27 = ADD_ARRAY_ELEMENT V40 NEXT
  .php   0079 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0080 SEND_VAL_EX string(",") 1
  .php   0081 T41 = CAST (object) CV1($prices)
  .php   0082 INIT_METHOD_CALL 1 T41 string("filter")
  .php   0083 T42 = DECLARE_LAMBDA_FUNCTION 6
  .php   0084 SEND_VAL_EX T42 1
  .php   0085 V43 = DO_FCALL
  .php   0086 T44 = CAST (object) V43
  .php   0087 INIT_METHOD_CALL 0 T44 string("keys")
  .php   0088 V45 = DO_FCALL
  .php   0089 SEND_VAR_NO_REF_EX V45 2
  .php   0090 V46 = DO_FCALL
  .php   0091 T27 = ADD_ARRAY_ELEMENT V46 NEXT
  .php   0092 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0093 SEND_VAL_EX string(",") 1
  .php   0094 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   0095 T47 = CAST (object) CV1($prices)
  .php   0096 INIT_METHOD_CALL 1 T47 string("filter")
  .php   0097 T48 = DECLARE_LAMBDA_FUNCTION 7
  .php   0098 SEND_VAL_EX T48 1
  .php   0099 V49 = DO_FCALL
  .php   0100 SEND_VAR V49 1
  .php   0101 V50 = DO_ICALL
  .php   0102 SEND_VAR_NO_REF_EX V50 2
  .php   0104 T27 = ADD_ARRAY_ELEMENT V51 NEXT
  .php   0105 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0106 SEND_VAL_EX string(",") 1
  .php   0107 T52 = CAST (object) CV1($prices)
  .php   0108 INIT_METHOD_CALL 1 T52 string("map")
  .php   0109 T53 = DECLARE_LAMBDA_FUNCTION 8
  .php   0110 SEND_VAL_EX T53 1
  .php   0111 V54 = DO_FCALL
  .php   0112 SEND_VAR_NO_REF_EX V54 2
  .php   0113 V55 = DO_FCALL
  .php   0114 T27 = ADD_ARRAY_ELEMENT V55 NEXT
  .php   0115 VERIFY_RETURN_TYPE T27
  .php   0116 RETURN T27
  .php   0117 VERIFY_RETURN_TYPE
  .php   0118 RETURN null
  .php        6: 0005 - 0021 (new)
  .php        27: 0048 - 0116 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Cashier: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Cashier::receipt string("List<int>, Any?, Any?")
  .sharp      ; (lines=67, args=3, vars=7, tmps=30)
  .sharp L0023 0035 T24 = SHARP_VERIFY_TYPE_ARGS V23 string("List<int>")
  .sharp L0023 0036 ASSIGN CV6($recorded) T24
  .sharp L0025 0037 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0025 0038 SEND_VAL_EX string(",") 1
  .sharp L0025 0039 T26 = CAST (object) CV4($paid)
  .sharp L0025 0040 INIT_METHOD_CALL 1 T26 string("map")
  .sharp L0025 0041 INIT_METHOD_CALL 0 THIS string("format")
  .sharp L0025 0042 T27 = CALLABLE_CONVERT
  .sharp L0025 0043 SEND_VAL_EX T27 1
  .sharp L0025 0044 V28 = DO_FCALL
  .sharp L0025 0045 SEND_VAR_NO_REF_EX V28 2
  .sharp L0025 0046 V29 = DO_FCALL
  .sharp L0025 0047 T30 = INIT_ARRAY 5 (packed) V29 NEXT
  .sharp L0026 0048 T31 = CAST (string) CV5($total)
  .sharp L0026 0049 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp L0027 0050 T32 = CAST (string) CV3($count)
  .sharp L0027 0051 T30 = ADD_ARRAY_ELEMENT T32 NEXT
  .sharp L0028 0052 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0028 0053 SEND_VAL_EX string(",") 1
  .sharp L0028 0054 SEND_VAR_EX CV6($recorded) 2
  .sharp L0028 0055 V33 = DO_FCALL
  .sharp L0028 0056 T30 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp L0029 0057 T34 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp L0029 0058 INIT_DYNAMIC_CALL 1 T34
  .sharp L0029 0059 SEND_VAR_EX CV5($total) 1
  .sharp L0029 0060 V35 = DO_FCALL
  .sharp L0029 0061 T36 = CAST (string) V35
  .sharp L0029 0062 T30 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp L0029 0063 VERIFY_RETURN_TYPE T30
  .sharp L0029 0064 RETURN T30
  .sharp L0031 0065 VERIFY_RETURN_TYPE
  .sharp L0031 0066 RETURN null
  .sharp      ; (lines=67, args=3, vars=7, tmps=30)
  .sharp 0035 T24 = SHARP_VERIFY_TYPE_ARGS V23 string("List<int>")
  .sharp 0036 ASSIGN CV6($recorded) T24
  .sharp 0037 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0038 SEND_VAL_EX string(",") 1
  .sharp 0039 T26 = CAST (object) CV4($paid)
  .sharp 0040 INIT_METHOD_CALL 1 T26 string("map")
  .sharp 0041 INIT_METHOD_CALL 0 THIS string("format")
  .sharp 0042 T27 = CALLABLE_CONVERT
  .sharp 0043 SEND_VAL_EX T27 1
  .sharp 0044 V28 = DO_FCALL
  .sharp 0045 SEND_VAR_NO_REF_EX V28 2
  .sharp 0046 V29 = DO_FCALL
  .sharp 0047 T30 = INIT_ARRAY 5 (packed) V29 NEXT
  .sharp 0048 T31 = CAST (string) CV5($total)
  .sharp 0049 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp 0050 T32 = CAST (string) CV3($count)
  .sharp 0051 T30 = ADD_ARRAY_ELEMENT T32 NEXT
  .sharp 0052 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0053 SEND_VAL_EX string(",") 1
  .sharp 0054 SEND_VAR_EX CV6($recorded) 2
  .sharp 0055 V33 = DO_FCALL
  .sharp 0056 T30 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp 0057 T34 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp 0058 INIT_DYNAMIC_CALL 1 T34
  .sharp 0059 SEND_VAR_EX CV5($total) 1
  .sharp 0060 V35 = DO_FCALL
  .sharp 0061 T36 = CAST (string) V35
  .sharp 0062 T30 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp 0063 VERIFY_RETURN_TYPE T30
  .sharp 0064 RETURN T30
  .sharp 0065 VERIFY_RETURN_TYPE
  .sharp 0066 RETURN null
  .sharp      30: 0048 - 0064 (tmp/var)
  .php        ; (lines=66, args=3, vars=7, tmps=29)
  .php   L0023 0035 ASSIGN CV6($recorded) V23
  .php   L0025 0036 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0025 0037 SEND_VAL_EX string(",") 1
  .php   L0025 0038 T25 = CAST (object) CV4($paid)
  .php   L0025 0039 INIT_METHOD_CALL 1 T25 string("map")
  .php   L0025 0040 INIT_METHOD_CALL 0 THIS string("format")
  .php   L0025 0041 T26 = CALLABLE_CONVERT
  .php   L0025 0042 SEND_VAL_EX T26 1
  .php   L0025 0043 V27 = DO_FCALL
  .php   L0025 0044 SEND_VAR_NO_REF_EX V27 2
  .php   L0025 0045 V28 = DO_FCALL
  .php   L0025 0046 T29 = INIT_ARRAY 5 (packed) V28 NEXT
  .php   L0026 0047 T30 = CAST (string) CV5($total)
  .php   L0026 0048 T29 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   L0027 0049 T31 = CAST (string) CV3($count)
  .php   L0027 0050 T29 = ADD_ARRAY_ELEMENT T31 NEXT
  .php   L0028 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0028 0052 SEND_VAL_EX string(",") 1
  .php   L0028 0053 SEND_VAR_EX CV6($recorded) 2
  .php   L0028 0054 V32 = DO_FCALL
  .php   L0028 0055 T29 = ADD_ARRAY_ELEMENT V32 NEXT
  .php   L0029 0056 T33 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   L0029 0057 INIT_DYNAMIC_CALL 1 T33
  .php   L0029 0058 SEND_VAR_EX CV5($total) 1
  .php   L0029 0059 V34 = DO_FCALL
  .php   L0029 0060 T35 = CAST (string) V34
  .php   L0029 0061 T29 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   L0029 0062 VERIFY_RETURN_TYPE T29
  .php   L0029 0063 RETURN T29
  .php   L0031 0064 VERIFY_RETURN_TYPE
  .php   L0031 0065 RETURN null
  .php        ; (lines=66, args=3, vars=7, tmps=29)
  .php   0035 ASSIGN CV6($recorded) V23
  .php   0036 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0037 SEND_VAL_EX string(",") 1
  .php   0038 T25 = CAST (object) CV4($paid)
  .php   0039 INIT_METHOD_CALL 1 T25 string("map")
  .php   0040 INIT_METHOD_CALL 0 THIS string("format")
  .php   0041 T26 = CALLABLE_CONVERT
  .php   0042 SEND_VAL_EX T26 1
  .php   0043 V27 = DO_FCALL
  .php   0044 SEND_VAR_NO_REF_EX V27 2
  .php   0045 V28 = DO_FCALL
  .php   0046 T29 = INIT_ARRAY 5 (packed) V28 NEXT
  .php   0047 T30 = CAST (string) CV5($total)
  .php   0048 T29 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   0049 T31 = CAST (string) CV3($count)
  .php   0050 T29 = ADD_ARRAY_ELEMENT T31 NEXT
  .php   0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0052 SEND_VAL_EX string(",") 1
  .php   0053 SEND_VAR_EX CV6($recorded) 2
  .php   0054 V32 = DO_FCALL
  .php   0055 T29 = ADD_ARRAY_ELEMENT V32 NEXT
  .php   0056 T33 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   0057 INIT_DYNAMIC_CALL 1 T33
  .php   0058 SEND_VAR_EX CV5($total) 1
  .php   0059 V34 = DO_FCALL
  .php   0060 T35 = CAST (string) V34
  .php   0061 T29 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   0062 VERIFY_RETURN_TYPE T29
  .php   0063 RETURN T29
  .php   0064 VERIFY_RETURN_TYPE
  .php   0065 RETURN null
  .php        29: 0047 - 0063 (tmp/var)
Roster: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Roster::report string("Map<Lib.Standing, int>")
  .sharp      ; (lines=116, args=1, vars=5, tmps=57)
  .sharp L0008 0002 JMPZ T5 0024
  .sharp L0008 0003 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0008 0004 T7 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0008 0005 V8 = NEW 1 V6 T7
  .sharp L0008 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp L0008 0007 SEND_VAL array(...) 1
  .sharp L0008 0008 INIT_FCALL 3 128 string("array_map")
  .sharp L0008 0009 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0008 0010 SEND_VAL T9 1
  .sharp L0008 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp L0008 0012 SEND_VAR CV0($members) 1
  .sharp L0008 0013 V10 = DO_ICALL
  .sharp L0008 0014 SEND_VAR V10 2
  .sharp L0008 0015 SEND_VAR CV0($members) 3
  .sharp L0008 0016 V11 = DO_ICALL
  .sharp L0008 0017 SEND_UNPACK V11
  .sharp L0008 0018 CHECK_UNDEF_ARGS
  .sharp L0008 0019 V12 = DO_ICALL
  .sharp L0008 0020 SEND_VAR_NO_REF_EX V12 1
  .sharp L0008 0021 DO_FCALL
  .sharp L0008 0022 T14 = QM_ASSIGN V8
  .sharp L0008 0023 JMP 0025
  .sharp L0008 0024 T14 = QM_ASSIGN CV0($members)
  .sharp L0008 0025 ASSIGN CV0($members) T14
  .sharp L0010 0026 T16 = CAST (object) CV0($members)
  .sharp L0010 0027 INIT_METHOD_CALL 1 T16 string("sortedBy")
  .sharp L0010 0028 T17 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0010 0029 SEND_VAL_EX T17 1
  .sharp L0010 0030 V18 = DO_FCALL
  .sharp L0010 0031 ASSIGN CV1($ranked) V18
  .sharp L0011 0032 T20 = CAST (object) CV0($members)
  .sharp L0011 0033 INIT_METHOD_CALL 1 T20 string("map")
  .sharp L0011 0034 T21 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0011 0035 SEND_VAL_EX T21 1
  .sharp L0011 0036 V22 = DO_FCALL
  .sharp L0011 0037 ASSIGN CV2($tens) V22
  .sharp L0012 0038 T24 = CAST (object) CV0($members)
  .sharp L0012 0039 INIT_METHOD_CALL 1 T24 string("filter")
  .sharp L0012 0040 T25 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0012 0041 SEND_VAL_EX T25 1
  .sharp L0012 0042 V26 = DO_FCALL
  .sharp L0012 0043 ASSIGN CV3($busy) V26
  .sharp L0013 0044 T28 = CAST (object) CV0($members)
  .sharp L0013 0045 INIT_METHOD_CALL 1 T28 string("filter")
  .sharp L0013 0046 T29 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0013 0047 BIND_LEXICAL (ref) T29 CV0($members)
  .sharp L0013 0048 SEND_VAL_EX T29 1
  .sharp L0013 0049 V30 = DO_FCALL
  .sharp L0013 0050 ASSIGN CV4($kept) V30
  .sharp L0014 0051 T32 = CAST (object) CV0($members)
  .sharp L0014 0052 INIT_METHOD_CALL 1 T32 string("remove")
  .sharp L0014 0053 T33 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0014 0054 SEND_VAL_EX T33 1
  .sharp L0014 0055 DO_FCALL
  .sharp L0016 0056 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0016 0057 SEND_VAL_EX string(",") 1
  .sharp L0016 0058 SEND_VAR_EX CV1($ranked) 2
  .sharp L0016 0059 V35 = DO_FCALL
  .sharp L0016 0060 T36 = INIT_ARRAY 7 (packed) V35 NEXT
  .sharp L0017 0061 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0017 0062 SEND_VAL_EX string(",") 1
  .sharp L0017 0063 SEND_VAR_EX CV2($tens) 2
  .sharp L0017 0064 V37 = DO_FCALL
  .sharp L0017 0065 T36 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp L0018 0066 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0018 0067 SEND_VAL_EX string(",") 1
  .sharp L0018 0068 T38 = CAST (object) CV4($kept)
  .sharp L0018 0069 INIT_METHOD_CALL 1 T38 string("map")
  .sharp L0018 0070 T39 = DECLARE_LAMBDA_FUNCTION 5
  .sharp L0018 0071 SEND_VAL_EX T39 1
  .sharp L0018 0072 V40 = DO_FCALL
  .sharp L0018 0073 SEND_VAR_NO_REF_EX V40 2
  .sharp L0018 0074 V41 = DO_FCALL
  .sharp L0018 0075 T36 = ADD_ARRAY_ELEMENT V41 NEXT
  .sharp L0019 0076 T42 = CAST (object) CV3($busy)
  .sharp L0019 0077 INIT_METHOD_CALL 1 T42 string("get")
  .sharp L0019 0078 T43 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0019 0079 SEND_VAL_EX T43 1
  .sharp L0019 0080 V44 = DO_FCALL
  .sharp L0019 0081 T45 = COALESCE V44 0083
  .sharp L0019 0082 T45 = QM_ASSIGN int(0)
  .sharp L0019 0083 T46 = CAST (string) T45
  .sharp L0019 0084 T36 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp L0020 0085 T47 = CAST (object) CV3($busy)
  .sharp L0020 0086 INIT_METHOD_CALL 1 T47 string("get")
  .sharp L0020 0087 T48 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0020 0088 SEND_VAL_EX T48 1
  .sharp L0020 0089 V49 = DO_FCALL
  .sharp L0020 0090 T50 = COALESCE V49 0092
  .sharp L0020 0091 T50 = QM_ASSIGN int(0)
  .sharp L0020 0092 T51 = CAST (string) T50
  .sharp L0020 0093 T36 = ADD_ARRAY_ELEMENT T51 NEXT
  .sharp L0021 0094 T52 = CAST (object) CV0($members)
  .sharp L0021 0095 INIT_METHOD_CALL 1 T52 string("get")
  .sharp L0021 0096 T53 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0021 0097 SEND_VAL_EX T53 1
  .sharp L0021 0098 V54 = DO_FCALL
  .sharp L0021 0099 T55 = COALESCE V54 0101
  .sharp L0021 0100 T55 = QM_ASSIGN int(0)
  .sharp L0021 0101 T56 = CAST (string) T55
  .sharp L0021 0102 T36 = ADD_ARRAY_ELEMENT T56 NEXT
  .sharp L0022 0103 T57 = CAST (object) CV0($members)
  .sharp L0022 0104 INIT_METHOD_CALL 1 T57 string("get")
  .sharp L0022 0105 T58 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0022 0106 SEND_VAL_EX T58 1
  .sharp L0022 0107 V59 = DO_FCALL
  .sharp L0022 0108 T60 = COALESCE V59 0110
  .sharp L0022 0109 T60 = QM_ASSIGN int(0)
  .sharp L0022 0110 T61 = CAST (string) T60
  .sharp L0022 0111 T36 = ADD_ARRAY_ELEMENT T61 NEXT
  .sharp L0022 0112 VERIFY_RETURN_TYPE T36
  .sharp L0022 0113 RETURN T36
  .sharp L0024 0114 VERIFY_RETURN_TYPE
  .sharp L0024 0115 RETURN null
  .sharp L0008 0000 CV0($key#1) = RECV 1
  .sharp L0008 0001 CV1($value#2) = RECV 2
  .sharp L0008 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0008 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=116, args=1, vars=5, tmps=57)
  .sharp 0002 JMPZ T5 0024
  .sharp 0003 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0004 T7 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0005 V8 = NEW 1 V6 T7
  .sharp 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp 0007 SEND_VAL array(...) 1
  .sharp 0008 INIT_FCALL 3 128 string("array_map")
  .sharp 0009 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0010 SEND_VAL T9 1
  .sharp 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp 0012 SEND_VAR CV0($members) 1
  .sharp 0013 V10 = DO_ICALL
  .sharp 0014 SEND_VAR V10 2
  .sharp 0015 SEND_VAR CV0($members) 3
  .sharp 0016 V11 = DO_ICALL
  .sharp 0017 SEND_UNPACK V11
  .sharp 0018 CHECK_UNDEF_ARGS
  .sharp 0019 V12 = DO_ICALL
  .sharp 0020 SEND_VAR_NO_REF_EX V12 1
  .sharp 0021 DO_FCALL
  .sharp 0022 T14 = QM_ASSIGN V8
  .sharp 0023 JMP 0025
  .sharp 0024 T14 = QM_ASSIGN CV0($members)
  .sharp 0025 ASSIGN CV0($members) T14
  .sharp 0026 T16 = CAST (object) CV0($members)
  .sharp 0027 INIT_METHOD_CALL 1 T16 string("sortedBy")
  .sharp 0028 T17 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0029 SEND_VAL_EX T17 1
  .sharp 0030 V18 = DO_FCALL
  .sharp 0031 ASSIGN CV1($ranked) V18
  .sharp 0032 T20 = CAST (object) CV0($members)
  .sharp 0033 INIT_METHOD_CALL 1 T20 string("map")
  .sharp 0034 T21 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0035 SEND_VAL_EX T21 1
  .sharp 0036 V22 = DO_FCALL
  .sharp 0037 ASSIGN CV2($tens) V22
  .sharp 0038 T24 = CAST (object) CV0($members)
  .sharp 0039 INIT_METHOD_CALL 1 T24 string("filter")
  .sharp 0040 T25 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0041 SEND_VAL_EX T25 1
  .sharp 0042 V26 = DO_FCALL
  .sharp 0043 ASSIGN CV3($busy) V26
  .sharp 0044 T28 = CAST (object) CV0($members)
  .sharp 0045 INIT_METHOD_CALL 1 T28 string("filter")
  .sharp 0046 T29 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0047 BIND_LEXICAL (ref) T29 CV0($members)
  .sharp 0048 SEND_VAL_EX T29 1
  .sharp 0049 V30 = DO_FCALL
  .sharp 0050 ASSIGN CV4($kept) V30
  .sharp 0051 T32 = CAST (object) CV0($members)
  .sharp 0052 INIT_METHOD_CALL 1 T32 string("remove")
  .sharp 0053 T33 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0054 SEND_VAL_EX T33 1
  .sharp 0055 DO_FCALL
  .sharp 0056 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0057 SEND_VAL_EX string(",") 1
  .sharp 0058 SEND_VAR_EX CV1($ranked) 2
  .sharp 0059 V35 = DO_FCALL
  .sharp 0060 T36 = INIT_ARRAY 7 (packed) V35 NEXT
  .sharp 0061 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0062 SEND_VAL_EX string(",") 1
  .sharp 0063 SEND_VAR_EX CV2($tens) 2
  .sharp 0064 V37 = DO_FCALL
  .sharp 0065 T36 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp 0066 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0067 SEND_VAL_EX string(",") 1
  .sharp 0068 T38 = CAST (object) CV4($kept)
  .sharp 0069 INIT_METHOD_CALL 1 T38 string("map")
  .sharp 0070 T39 = DECLARE_LAMBDA_FUNCTION 5
  .sharp 0071 SEND_VAL_EX T39 1
  .sharp 0072 V40 = DO_FCALL
  .sharp 0073 SEND_VAR_NO_REF_EX V40 2
  .sharp 0074 V41 = DO_FCALL
  .sharp 0075 T36 = ADD_ARRAY_ELEMENT V41 NEXT
  .sharp 0076 T42 = CAST (object) CV3($busy)
  .sharp 0077 INIT_METHOD_CALL 1 T42 string("get")
  .sharp 0078 T43 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0079 SEND_VAL_EX T43 1
  .sharp 0080 V44 = DO_FCALL
  .sharp 0081 T45 = COALESCE V44 0083
  .sharp 0082 T45 = QM_ASSIGN int(0)
  .sharp 0083 T46 = CAST (string) T45
  .sharp 0084 T36 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp 0085 T47 = CAST (object) CV3($busy)
  .sharp 0086 INIT_METHOD_CALL 1 T47 string("get")
  .sharp 0087 T48 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0088 SEND_VAL_EX T48 1
  .sharp 0089 V49 = DO_FCALL
  .sharp 0090 T50 = COALESCE V49 0092
  .sharp 0091 T50 = QM_ASSIGN int(0)
  .sharp 0092 T51 = CAST (string) T50
  .sharp 0093 T36 = ADD_ARRAY_ELEMENT T51 NEXT
  .sharp 0094 T52 = CAST (object) CV0($members)
  .sharp 0095 INIT_METHOD_CALL 1 T52 string("get")
  .sharp 0096 T53 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0097 SEND_VAL_EX T53 1
  .sharp 0098 V54 = DO_FCALL
  .sharp 0099 T55 = COALESCE V54 0101
  .sharp 0100 T55 = QM_ASSIGN int(0)
  .sharp 0101 T56 = CAST (string) T55
  .sharp 0102 T36 = ADD_ARRAY_ELEMENT T56 NEXT
  .sharp 0103 T57 = CAST (object) CV0($members)
  .sharp 0104 INIT_METHOD_CALL 1 T57 string("get")
  .sharp 0105 T58 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0106 SEND_VAL_EX T58 1
  .sharp 0107 V59 = DO_FCALL
  .sharp 0108 T60 = COALESCE V59 0110
  .sharp 0109 T60 = QM_ASSIGN int(0)
  .sharp 0110 T61 = CAST (string) T60
  .sharp 0111 T36 = ADD_ARRAY_ELEMENT T61 NEXT
  .sharp 0112 VERIFY_RETURN_TYPE T36
  .sharp 0113 RETURN T36
  .sharp 0114 VERIFY_RETURN_TYPE
  .sharp 0115 RETURN null
  .sharp      8: 0006 - 0022 (new)
  .sharp      29: 0047 - 0048 (tmp/var)
  .sharp      36: 0061 - 0113 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=114, args=1, vars=5, tmps=55)
  .php   L0008 0002 JMPZ T5 0022
  .php   L0008 0003 V6 = NEW 1 string("Sharp\\Map")
  .php   L0008 0004 INIT_FCALL 1 96 string("array_merge")
  .php   L0008 0005 SEND_VAL array(...) 1
  .php   L0008 0006 INIT_FCALL 3 128 string("array_map")
  .php   L0008 0007 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0008 0008 SEND_VAL T7 1
  .php   L0008 0009 INIT_FCALL 1 96 string("array_keys")
  .php   L0008 0010 SEND_VAR CV0($members) 1
  .php   L0008 0011 V8 = DO_ICALL
  .php   L0008 0012 SEND_VAR V8 2
  .php   L0008 0013 SEND_VAR CV0($members) 3
  .php   L0008 0014 V9 = DO_ICALL
  .php   L0008 0015 SEND_UNPACK V9
  .php   L0008 0016 CHECK_UNDEF_ARGS
  .php   L0008 0017 V10 = DO_ICALL
  .php   L0008 0018 SEND_VAR_NO_REF_EX V10 1
  .php   L0008 0019 DO_FCALL
  .php   L0008 0020 T12 = QM_ASSIGN V6
  .php   L0008 0021 JMP 0023
  .php   L0008 0022 T12 = QM_ASSIGN CV0($members)
  .php   L0008 0023 ASSIGN CV0($members) T12
  .php   L0010 0024 T14 = CAST (object) CV0($members)
  .php   L0010 0025 INIT_METHOD_CALL 1 T14 string("sortedBy")
  .php   L0010 0026 T15 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0010 0027 SEND_VAL_EX T15 1
  .php   L0010 0028 V16 = DO_FCALL
  .php   L0010 0029 ASSIGN CV1($ranked) V16
  .php   L0011 0030 T18 = CAST (object) CV0($members)
  .php   L0011 0031 INIT_METHOD_CALL 1 T18 string("map")
  .php   L0011 0032 T19 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0011 0033 SEND_VAL_EX T19 1
  .php   L0011 0034 V20 = DO_FCALL
  .php   L0011 0035 ASSIGN CV2($tens) V20
  .php   L0012 0036 T22 = CAST (object) CV0($members)
  .php   L0012 0037 INIT_METHOD_CALL 1 T22 string("filter")
  .php   L0012 0038 T23 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0012 0039 SEND_VAL_EX T23 1
  .php   L0012 0040 V24 = DO_FCALL
  .php   L0012 0041 ASSIGN CV3($busy) V24
  .php   L0013 0042 T26 = CAST (object) CV0($members)
  .php   L0013 0043 INIT_METHOD_CALL 1 T26 string("filter")
  .php   L0013 0044 T27 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0013 0045 BIND_LEXICAL (ref) T27 CV0($members)
  .php   L0013 0046 SEND_VAL_EX T27 1
  .php   L0013 0047 V28 = DO_FCALL
  .php   L0013 0048 ASSIGN CV4($kept) V28
  .php   L0014 0049 T30 = CAST (object) CV0($members)
  .php   L0014 0050 INIT_METHOD_CALL 1 T30 string("remove")
  .php   L0014 0051 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0014 0052 SEND_VAL_EX T31 1
  .php   L0014 0053 DO_FCALL
  .php   L0016 0054 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0016 0055 SEND_VAL_EX string(",") 1
  .php   L0016 0056 SEND_VAR_EX CV1($ranked) 2
  .php   L0016 0057 V33 = DO_FCALL
  .php   L0016 0058 T34 = INIT_ARRAY 7 (packed) V33 NEXT
  .php   L0017 0059 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0017 0060 SEND_VAL_EX string(",") 1
  .php   L0017 0061 SEND_VAR_EX CV2($tens) 2
  .php   L0017 0062 V35 = DO_FCALL
  .php   L0017 0063 T34 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   L0018 0064 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0018 0065 SEND_VAL_EX string(",") 1
  .php   L0018 0066 T36 = CAST (object) CV4($kept)
  .php   L0018 0067 INIT_METHOD_CALL 1 T36 string("map")
  .php   L0018 0068 T37 = DECLARE_LAMBDA_FUNCTION 5
  .php   L0018 0069 SEND_VAL_EX T37 1
  .php   L0018 0070 V38 = DO_FCALL
  .php   L0018 0071 SEND_VAR_NO_REF_EX V38 2
  .php   L0018 0072 V39 = DO_FCALL
  .php   L0018 0073 T34 = ADD_ARRAY_ELEMENT V39 NEXT
  .php   L0019 0074 T40 = CAST (object) CV3($busy)
  .php   L0019 0075 INIT_METHOD_CALL 1 T40 string("get")
  .php   L0019 0076 T41 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0019 0077 SEND_VAL_EX T41 1
  .php   L0019 0078 V42 = DO_FCALL
  .php   L0019 0079 T43 = COALESCE V42 0081
  .php   L0019 0080 T43 = QM_ASSIGN int(0)
  .php   L0019 0081 T44 = CAST (string) T43
  .php   L0019 0082 T34 = ADD_ARRAY_ELEMENT T44 NEXT
  .php   L0020 0083 T45 = CAST (object) CV3($busy)
  .php   L0020 0084 INIT_METHOD_CALL 1 T45 string("get")
  .php   L0020 0085 T46 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0020 0086 SEND_VAL_EX T46 1
  .php   L0020 0087 V47 = DO_FCALL
  .php   L0020 0088 T48 = COALESCE V47 0090
  .php   L0020 0089 T48 = QM_ASSIGN int(0)
  .php   L0020 0090 T49 = CAST (string) T48
  .php   L0020 0091 T34 = ADD_ARRAY_ELEMENT T49 NEXT
  .php   L0021 0092 T50 = CAST (object) CV0($members)
  .php   L0021 0093 INIT_METHOD_CALL 1 T50 string("get")
  .php   L0021 0094 T51 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0021 0095 SEND_VAL_EX T51 1
  .php   L0021 0096 V52 = DO_FCALL
  .php   L0021 0097 T53 = COALESCE V52 0099
  .php   L0021 0098 T53 = QM_ASSIGN int(0)
  .php   L0021 0099 T54 = CAST (string) T53
  .php   L0021 0100 T34 = ADD_ARRAY_ELEMENT T54 NEXT
  .php   L0022 0101 T55 = CAST (object) CV0($members)
  .php   L0022 0102 INIT_METHOD_CALL 1 T55 string("get")
  .php   L0022 0103 T56 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0022 0104 SEND_VAL_EX T56 1
  .php   L0022 0105 V57 = DO_FCALL
  .php   L0022 0106 T58 = COALESCE V57 0108
  .php   L0022 0107 T58 = QM_ASSIGN int(0)
  .php   L0022 0108 T59 = CAST (string) T58
  .php   L0022 0109 T34 = ADD_ARRAY_ELEMENT T59 NEXT
  .php   L0022 0110 VERIFY_RETURN_TYPE T34
  .php   L0022 0111 RETURN T34
  .php   L0024 0112 VERIFY_RETURN_TYPE
  .php   L0024 0113 RETURN null
  .php   L0008 0000 CV0($key) = RECV 1
  .php   L0008 0001 CV1($value) = RECV 2
  .php   L0008 0003 T2 = CAST (string) CV0($key)
  .php   L0008 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=114, args=1, vars=5, tmps=55)
  .php   0002 JMPZ T5 0022
  .php   0003 V6 = NEW 1 string("Sharp\\Map")
  .php   0004 INIT_FCALL 1 96 string("array_merge")
  .php   0005 SEND_VAL array(...) 1
  .php   0006 INIT_FCALL 3 128 string("array_map")
  .php   0007 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   0008 SEND_VAL T7 1
  .php   0009 INIT_FCALL 1 96 string("array_keys")
  .php   0010 SEND_VAR CV0($members) 1
  .php   0011 V8 = DO_ICALL
  .php   0012 SEND_VAR V8 2
  .php   0013 SEND_VAR CV0($members) 3
  .php   0014 V9 = DO_ICALL
  .php   0015 SEND_UNPACK V9
  .php   0016 CHECK_UNDEF_ARGS
  .php   0017 V10 = DO_ICALL
  .php   0018 SEND_VAR_NO_REF_EX V10 1
  .php   0019 DO_FCALL
  .php   0020 T12 = QM_ASSIGN V6
  .php   0021 JMP 0023
  .php   0022 T12 = QM_ASSIGN CV0($members)
  .php   0023 ASSIGN CV0($members) T12
  .php   0024 T14 = CAST (object) CV0($members)
  .php   0025 INIT_METHOD_CALL 1 T14 string("sortedBy")
  .php   0026 T15 = DECLARE_LAMBDA_FUNCTION 1
  .php   0027 SEND_VAL_EX T15 1
  .php   0028 V16 = DO_FCALL
  .php   0029 ASSIGN CV1($ranked) V16
  .php   0030 T18 = CAST (object) CV0($members)
  .php   0031 INIT_METHOD_CALL 1 T18 string("map")
  .php   0032 T19 = DECLARE_LAMBDA_FUNCTION 2
  .php   0033 SEND_VAL_EX T19 1
  .php   0034 V20 = DO_FCALL
  .php   0035 ASSIGN CV2($tens) V20
  .php   0036 T22 = CAST (object) CV0($members)
  .php   0037 INIT_METHOD_CALL 1 T22 string("filter")
  .php   0038 T23 = DECLARE_LAMBDA_FUNCTION 3
  .php   0039 SEND_VAL_EX T23 1
  .php   0040 V24 = DO_FCALL
  .php   0041 ASSIGN CV3($busy) V24
  .php   0042 T26 = CAST (object) CV0($members)
  .php   0043 INIT_METHOD_CALL 1 T26 string("filter")
  .php   0044 T27 = DECLARE_LAMBDA_FUNCTION 4
  .php   0045 BIND_LEXICAL (ref) T27 CV0($members)
  .php   0046 SEND_VAL_EX T27 1
  .php   0047 V28 = DO_FCALL
  .php   0048 ASSIGN CV4($kept) V28
  .php   0049 T30 = CAST (object) CV0($members)
  .php   0050 INIT_METHOD_CALL 1 T30 string("remove")
  .php   0051 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0052 SEND_VAL_EX T31 1
  .php   0053 DO_FCALL
  .php   0054 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0055 SEND_VAL_EX string(",") 1
  .php   0056 SEND_VAR_EX CV1($ranked) 2
  .php   0057 V33 = DO_FCALL
  .php   0058 T34 = INIT_ARRAY 7 (packed) V33 NEXT
  .php   0059 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0060 SEND_VAL_EX string(",") 1
  .php   0061 SEND_VAR_EX CV2($tens) 2
  .php   0062 V35 = DO_FCALL
  .php   0063 T34 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   0064 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0065 SEND_VAL_EX string(",") 1
  .php   0066 T36 = CAST (object) CV4($kept)
  .php   0067 INIT_METHOD_CALL 1 T36 string("map")
  .php   0068 T37 = DECLARE_LAMBDA_FUNCTION 5
  .php   0069 SEND_VAL_EX T37 1
  .php   0070 V38 = DO_FCALL
  .php   0071 SEND_VAR_NO_REF_EX V38 2
  .php   0072 V39 = DO_FCALL
  .php   0073 T34 = ADD_ARRAY_ELEMENT V39 NEXT
  .php   0074 T40 = CAST (object) CV3($busy)
  .php   0075 INIT_METHOD_CALL 1 T40 string("get")
  .php   0076 T41 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0077 SEND_VAL_EX T41 1
  .php   0078 V42 = DO_FCALL
  .php   0079 T43 = COALESCE V42 0081
  .php   0080 T43 = QM_ASSIGN int(0)
  .php   0081 T44 = CAST (string) T43
  .php   0082 T34 = ADD_ARRAY_ELEMENT T44 NEXT
  .php   0083 T45 = CAST (object) CV3($busy)
  .php   0084 INIT_METHOD_CALL 1 T45 string("get")
  .php   0085 T46 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0086 SEND_VAL_EX T46 1
  .php   0087 V47 = DO_FCALL
  .php   0088 T48 = COALESCE V47 0090
  .php   0089 T48 = QM_ASSIGN int(0)
  .php   0090 T49 = CAST (string) T48
  .php   0091 T34 = ADD_ARRAY_ELEMENT T49 NEXT
  .php   0092 T50 = CAST (object) CV0($members)
  .php   0093 INIT_METHOD_CALL 1 T50 string("get")
  .php   0094 T51 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0095 SEND_VAL_EX T51 1
  .php   0096 V52 = DO_FCALL
  .php   0097 T53 = COALESCE V52 0099
  .php   0098 T53 = QM_ASSIGN int(0)
  .php   0099 T54 = CAST (string) T53
  .php   0100 T34 = ADD_ARRAY_ELEMENT T54 NEXT
  .php   0101 T55 = CAST (object) CV0($members)
  .php   0102 INIT_METHOD_CALL 1 T55 string("get")
  .php   0103 T56 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0104 SEND_VAL_EX T56 1
  .php   0105 V57 = DO_FCALL
  .php   0106 T58 = COALESCE V57 0108
  .php   0107 T58 = QM_ASSIGN int(0)
  .php   0108 T59 = CAST (string) T58
  .php   0109 T34 = ADD_ARRAY_ELEMENT T59 NEXT
  .php   0110 VERIFY_RETURN_TYPE T34
  .php   0111 RETURN T34
  .php   0112 VERIFY_RETURN_TYPE
  .php   0113 RETURN null
  .php        6: 0004 - 0020 (new)
  .php        27: 0045 - 0046 (tmp/var)
  .php        34: 0059 - 0111 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Sets/Tagged: different opcodes and lines in 27 op arrays, same signatures in 1 classes
  check  Demo\Tagged::__construct string("Set<int>, Set<string>")
  check  Demo\Tagged::strings string("Set<string>")
  check  Demo\Tagged::reads string("Set<string>")
  check  Demo\Tagged::missing string("Set<string>")
  check  Demo\Tagged::ints string("Set<int>")
  check  Demo\Tagged::cases string("Set<Lib.Standing>")
  check  Demo\Tagged::once string("Set<string>?")
  check  Demo\Tagged::lambdas string("List<string>")
  check  {closure:Demo\Tagged::lambdas():96} string("Set<string>")
  check  {closure:Demo\Tagged::lambdas():97} string("Set<string>")
  check  Demo\Tagged::groups string("Set<string>")
  check  Demo\Tagged::maybe string("Set<string>?")
  .sharp      ; (lines=27, args=2, vars=2, tmps=14)
  .sharp L0015 0003 JMPZ T3 0011
  .sharp L0015 0004 V4 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0015 0005 T5 = SHARP_TYPE_ARGS string("int")
  .sharp L0015 0006 V6 = NEW 1 V4 T5
  .sharp L0015 0007 SEND_VAR_EX CV0($ids) 1
  .sharp L0015 0008 DO_FCALL
  .sharp L0015 0009 T8 = QM_ASSIGN V6
  .sharp L0015 0010 JMP 0012
  .sharp L0015 0011 T8 = QM_ASSIGN CV0($ids)
  .sharp L0015 0012 ASSIGN_OBJ THIS string("ids")
  .sharp L0015 0013 OP_DATA T8
  .sharp L0015 0014 T10 = TYPE_CHECK (array) CV1($labels)
  .sharp L0015 0015 JMPZ T10 0023
  .sharp L0015 0016 V11 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0015 0017 T12 = SHARP_TYPE_ARGS string("string")
  .sharp L0015 0018 V13 = NEW 1 V11 T12
  .sharp L0015 0019 SEND_VAR_EX CV1($labels) 1
  .sharp L0015 0020 DO_FCALL
  .sharp L0015 0021 T15 = QM_ASSIGN V13
  .sharp L0015 0022 JMP 0024
  .sharp L0015 0023 T15 = QM_ASSIGN CV1($labels)
  .sharp L0015 0024 ASSIGN_OBJ THIS string("labels")
  .sharp L0015 0025 OP_DATA T15
  .sharp L0017 0026 RETURN null
  .sharp      ; (lines=67, args=1, vars=7, tmps=33)
  .sharp L0019 0002 JMPZ T7 0010
  .sharp L0019 0003 V8 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0019 0004 T9 = SHARP_TYPE_ARGS string("string")
  .sharp L0019 0005 V10 = NEW 1 V8 T9
  .sharp L0019 0006 SEND_VAR_EX CV0($tags) 1
  .sharp L0019 0007 DO_FCALL
  .sharp L0019 0008 T12 = QM_ASSIGN V10
  .sharp L0019 0009 JMP 0011
  .sharp L0019 0010 T12 = QM_ASSIGN CV0($tags)
  .sharp L0019 0011 ASSIGN CV0($tags) T12
  .sharp L0021 0012 T14 = CAST (object) CV0($tags)
  .sharp L0021 0013 INIT_METHOD_CALL 1 T14 string("add")
  .sharp L0021 0014 SEND_VAL_EX string("vip") 1
  .sharp L0021 0015 V15 = DO_FCALL
  .sharp L0021 0016 ASSIGN CV1($added) V15
  .sharp L0022 0017 T17 = CAST (object) CV0($tags)
  .sharp L0022 0018 INIT_METHOD_CALL 1 T17 string("add")
  .sharp L0022 0019 SEND_VAL_EX string("vip") 1
  .sharp L0022 0020 V18 = DO_FCALL
  .sharp L0022 0021 ASSIGN CV2($again) V18
  .sharp L0023 0022 T20 = CAST (object) CV0($tags)
  .sharp L0023 0023 INIT_METHOD_CALL 1 T20 string("remove")
  .sharp L0023 0024 SEND_VAL_EX string("new") 1
  .sharp L0023 0025 V21 = DO_FCALL
  .sharp L0023 0026 ASSIGN CV3($removed) V21
  .sharp L0024 0027 T23 = CAST (object) CV0($tags)
  .sharp L0024 0028 INIT_METHOD_CALL 1 T23 string("remove")
  .sharp L0024 0029 SEND_VAL_EX string("none") 1
  .sharp L0024 0030 V24 = DO_FCALL
  .sharp L0024 0031 ASSIGN CV4($absent) V24
  .sharp L0025 0032 ASSIGN CV5($joined) string("")
  .sharp L0026 0033 V27 = FE_RESET_R CV0($tags) 0039
  .sharp L0026 0034 FE_FETCH_R V27 CV6($tag) 0039
  .sharp L0027 0035 T28 = CONCAT CV5($joined) CV6($tag)
  .sharp L0027 0036 T29 = CONCAT T28 string(";")
  .sharp L0027 0037 ASSIGN CV5($joined) T29
  .sharp L0026 0038 JMP 0034
  .sharp L0026 0039 FE_FREE V27
  .sharp L0029 0040 T31 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .sharp L0029 0041 T31 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp L0029 0042 T31 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp L0029 0043 T31 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .sharp L0029 0044 T32 = CAST (object) CV0($tags)
  .sharp L0029 0045 INIT_METHOD_CALL 1 T32 string("contains")
  .sharp L0029 0046 SEND_VAL_EX string("5") 1
  .sharp L0029 0047 V33 = DO_FCALL
  .sharp L0029 0048 T31 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp L0029 0049 T34 = CAST (object) CV0($tags)
  .sharp L0029 0050 INIT_METHOD_CALL 1 T34 string("contains")
  .sharp L0029 0051 SEND_VAL_EX string("new") 1
  .sharp L0029 0052 V35 = DO_FCALL
  .sharp L0029 0053 T31 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp L0029 0054 T36 = CAST (object) CV0($tags)
  .sharp L0029 0055 INIT_METHOD_CALL 0 T36 string("count")
  .sharp L0029 0056 V37 = DO_FCALL
  .sharp L0029 0057 T31 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp L0029 0058 T38 = CAST (object) CV0($tags)
  .sharp L0029 0059 INIT_METHOD_CALL 0 T38 string("toList")
  .sharp L0029 0060 V39 = DO_FCALL
  .sharp L0029 0061 T31 = ADD_ARRAY_ELEMENT V39 NEXT
  .sharp L0029 0062 T31 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .sharp L0029 0063 VERIFY_RETURN_TYPE T31
  .sharp L0029 0064 RETURN T31
  .sharp L0030 0065 VERIFY_RETURN_TYPE
  .sharp L0030 0066 RETURN null
  .sharp      ; (lines=57, args=1, vars=1, tmps=27)
  .sharp L0032 0002 JMPZ T1 0010
  .sharp L0032 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0032 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp L0032 0005 V4 = NEW 1 V2 T3
  .sharp L0032 0006 SEND_VAR_EX CV0($tags) 1
  .sharp L0032 0007 DO_FCALL
  .sharp L0032 0008 T6 = QM_ASSIGN V4
  .sharp L0032 0009 JMP 0011
  .sharp L0032 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp L0032 0011 ASSIGN CV0($tags) T6
  .sharp L0035 0012 T8 = CAST (object) CV0($tags)
  .sharp L0035 0013 INIT_METHOD_CALL 1 T8 string("filter")
  .sharp L0035 0014 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0035 0015 SEND_VAL_EX T9 1
  .sharp L0035 0016 V10 = DO_FCALL
  .sharp L0035 0017 T11 = CAST (object) V10
  .sharp L0035 0018 INIT_METHOD_CALL 0 T11 string("toList")
  .sharp L0035 0019 V12 = DO_FCALL
  .sharp L0035 0020 T13 = INIT_ARRAY 6 (packed) V12 NEXT
  .sharp L0036 0021 T14 = CAST (object) CV0($tags)
  .sharp L0036 0022 INIT_METHOD_CALL 1 T14 string("map")
  .sharp L0036 0023 T15 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0036 0024 SEND_VAL_EX T15 1
  .sharp L0036 0025 V16 = DO_FCALL
  .sharp L0036 0026 T13 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp L0037 0027 T17 = CAST (object) CV0($tags)
  .sharp L0037 0028 INIT_METHOD_CALL 1 T17 string("any")
  .sharp L0037 0029 T18 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0037 0030 SEND_VAL_EX T18 1
  .sharp L0037 0031 V19 = DO_FCALL
  .sharp L0037 0032 T13 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp L0038 0033 T20 = CAST (object) CV0($tags)
  .sharp L0038 0034 INIT_METHOD_CALL 1 T20 string("first")
  .sharp L0038 0035 T21 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0038 0036 SEND_VAL_EX T21 1
  .sharp L0038 0037 V22 = DO_FCALL
  .sharp L0038 0038 T13 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp L0039 0039 T23 = CAST (object) CV0($tags)
  .sharp L0039 0040 INIT_METHOD_CALL 1 T23 string("sortedBy")
  .sharp L0039 0041 T24 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0039 0042 SEND_VAL_EX T24 1
  .sharp L0039 0043 V25 = DO_FCALL
  .sharp L0039 0044 T13 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp L0040 0045 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0040 0046 SEND_VAL_EX string(",") 1
  .sharp L0040 0047 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp L0040 0048 SEND_VAR CV0($tags) 1
  .sharp L0040 0049 V26 = DO_ICALL
  .sharp L0040 0050 SEND_VAR_NO_REF_EX V26 2
  .sharp L0040 0051 V27 = DO_FCALL
  .sharp L0040 0052 T13 = ADD_ARRAY_ELEMENT V27 NEXT
  .sharp L0040 0053 VERIFY_RETURN_TYPE T13
  .sharp L0040 0054 RETURN T13
  .sharp L0042 0055 VERIFY_RETURN_TYPE
  .sharp L0042 0056 RETURN null
  .sharp      ; (lines=21, args=1, vars=1, tmps=10)
  .sharp L0044 0002 JMPZ T1 0010
  .sharp L0044 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0044 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp L0044 0005 V4 = NEW 1 V2 T3
  .sharp L0044 0006 SEND_VAR_EX CV0($tags) 1
  .sharp L0044 0007 DO_FCALL
  .sharp L0044 0008 T6 = QM_ASSIGN V4
  .sharp L0044 0009 JMP 0011
  .sharp L0044 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp L0044 0011 ASSIGN CV0($tags) T6
  .sharp L0044 0012 T8 = CAST (object) CV0($tags)
  .sharp L0044 0013 INIT_METHOD_CALL 1 T8 string("first")
  .sharp L0044 0014 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0044 0015 SEND_VAL_EX T9 1
  .sharp L0044 0016 V10 = DO_FCALL
  .sharp L0044 0017 VERIFY_RETURN_TYPE V10
  .sharp L0044 0018 RETURN V10
  .sharp L0044 0019 VERIFY_RETURN_TYPE
  .sharp L0044 0020 RETURN null
  .sharp      ; (lines=56, args=1, vars=2, tmps=28)
  .sharp L0046 0002 JMPZ T2 0010
  .sharp L0046 0003 V3 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0046 0004 T4 = SHARP_TYPE_ARGS string("int")
  .sharp L0046 0005 V5 = NEW 1 V3 T4
  .sharp L0046 0006 SEND_VAR_EX CV0($ids) 1
  .sharp L0046 0007 DO_FCALL
  .sharp L0046 0008 T7 = QM_ASSIGN V5
  .sharp L0046 0009 JMP 0011
  .sharp L0046 0010 T7 = QM_ASSIGN CV0($ids)
  .sharp L0046 0011 ASSIGN CV0($ids) T7
  .sharp L0048 0012 V9 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0048 0013 T10 = SHARP_TYPE_ARGS string("int")
  .sharp L0048 0014 V11 = NEW 1 V9 T10
  .sharp L0048 0015 SEND_VAL_EX array(...) 1
  .sharp L0048 0016 DO_FCALL
  .sharp L0048 0017 ASSIGN CV1($more) V11
  .sharp L0049 0018 T14 = CAST (object) CV0($ids)
  .sharp L0049 0019 INIT_METHOD_CALL 1 T14 string("add")
  .sharp L0049 0020 SEND_VAL_EX int(4) 1
  .sharp L0049 0021 V15 = DO_FCALL
  .sharp L0049 0022 T16 = INIT_ARRAY 7 (packed) V15 NEXT
  .sharp L0049 0023 T17 = CAST (object) CV0($ids)
  .sharp L0049 0024 INIT_METHOD_CALL 1 T17 string("add")
  .sharp L0049 0025 SEND_VAL_EX int(4) 1
  .sharp L0049 0026 V18 = DO_FCALL
  .sharp L0049 0027 T16 = ADD_ARRAY_ELEMENT V18 NEXT
  .sharp L0049 0028 T19 = CAST (object) CV0($ids)
  .sharp L0049 0029 INIT_METHOD_CALL 1 T19 string("remove")
  .sharp L0049 0030 SEND_VAL_EX int(7) 1
  .sharp L0049 0031 V20 = DO_FCALL
  .sharp L0049 0032 T16 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp L0049 0033 T21 = CAST (object) CV0($ids)
  .sharp L0049 0034 INIT_METHOD_CALL 1 T21 string("contains")
  .sharp L0049 0035 SEND_VAL_EX int(2) 1
  .sharp L0049 0036 V22 = DO_FCALL
  .sharp L0049 0037 T16 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp L0049 0038 T23 = CAST (object) CV0($ids)
  .sharp L0049 0039 INIT_METHOD_CALL 0 T23 string("toList")
  .sharp L0049 0040 V24 = DO_FCALL
  .sharp L0049 0041 T16 = ADD_ARRAY_ELEMENT V24 NEXT
  .sharp L0049 0042 T25 = CAST (object) CV1($more)
  .sharp L0049 0043 INIT_METHOD_CALL 0 T25 string("toList")
  .sharp L0049 0044 V26 = DO_FCALL
  .sharp L0049 0045 T16 = ADD_ARRAY_ELEMENT V26 NEXT
  .sharp L0049 0046 T27 = CAST (object) CV0($ids)
  .sharp L0049 0047 INIT_METHOD_CALL 1 T27 string("sumOf")
  .sharp L0049 0048 T28 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0049 0049 SEND_VAL_EX T28 1
  .sharp L0049 0050 V29 = DO_FCALL
  .sharp L0049 0051 T16 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp L0049 0052 VERIFY_RETURN_TYPE T16
  .sharp L0049 0053 RETURN T16
  .sharp L0050 0054 VERIFY_RETURN_TYPE
  .sharp L0050 0055 RETURN null
  .sharp      ; (lines=70, args=1, vars=6, tmps=40)
  .sharp L0052 0002 JMPZ T6 0010
  .sharp L0052 0003 V7 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0052 0004 T8 = SHARP_TYPE_ARGS string("Lib.Standing")
  .sharp L0052 0005 V9 = NEW 1 V7 T8
  .sharp L0052 0006 SEND_VAR_EX CV0($states) 1
  .sharp L0052 0007 DO_FCALL
  .sharp L0052 0008 T11 = QM_ASSIGN V9
  .sharp L0052 0009 JMP 0011
  .sharp L0052 0010 T11 = QM_ASSIGN CV0($states)
  .sharp L0052 0011 ASSIGN CV0($states) T11
  .sharp L0054 0012 V13 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0054 0013 T14 = SHARP_TYPE_ARGS string("Lib.Size")
  .sharp L0054 0014 V15 = NEW 1 V13 T14
  .sharp L0054 0015 T16 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .sharp L0054 0016 T17 = INIT_ARRAY 3 (packed) T16 NEXT
  .sharp L0054 0017 T18 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp L0054 0018 T17 = ADD_ARRAY_ELEMENT T18 NEXT
  .sharp L0054 0019 T19 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .sharp L0054 0020 T17 = ADD_ARRAY_ELEMENT T19 NEXT
  .sharp L0054 0021 SEND_VAL_EX T17 1
  .sharp L0054 0022 DO_FCALL
  .sharp L0054 0023 ASSIGN CV1($sizes) V15
  .sharp L0055 0024 T22 = CAST (object) CV0($states)
  .sharp L0055 0025 INIT_METHOD_CALL 1 T22 string("add")
  .sharp L0055 0026 T23 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0055 0027 SEND_VAL_EX T23 1
  .sharp L0055 0028 V24 = DO_FCALL
  .sharp L0055 0029 ASSIGN CV2($closed) V24
  .sharp L0056 0030 T26 = CAST (object) CV0($states)
  .sharp L0056 0031 INIT_METHOD_CALL 1 T26 string("add")
  .sharp L0056 0032 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0056 0033 SEND_VAL_EX T27 1
  .sharp L0056 0034 V28 = DO_FCALL
  .sharp L0056 0035 ASSIGN CV3($active) V28
  .sharp L0057 0036 T30 = CAST (object) CV0($states)
  .sharp L0057 0037 INIT_METHOD_CALL 1 T30 string("contains")
  .sharp L0057 0038 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0057 0039 SEND_VAL_EX T31 1
  .sharp L0057 0040 V32 = DO_FCALL
  .sharp L0057 0041 ASSIGN CV4($has) V32
  .sharp L0058 0042 T34 = CAST (object) CV0($states)
  .sharp L0058 0043 INIT_METHOD_CALL 1 T34 string("remove")
  .sharp L0058 0044 T35 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0058 0045 SEND_VAL_EX T35 1
  .sharp L0058 0046 V36 = DO_FCALL
  .sharp L0058 0047 ASSIGN CV5($removed) V36
  .sharp L0059 0048 T38 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .sharp L0059 0049 T38 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .sharp L0059 0050 T38 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .sharp L0059 0051 T38 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .sharp L0059 0052 T39 = CAST (object) CV0($states)
  .sharp L0059 0053 INIT_METHOD_CALL 0 T39 string("toList")
  .sharp L0059 0054 V40 = DO_FCALL
  .sharp L0059 0055 T38 = ADD_ARRAY_ELEMENT V40 NEXT
  .sharp L0059 0056 T41 = CAST (object) CV1($sizes)
  .sharp L0059 0057 INIT_METHOD_CALL 0 T41 string("toList")
  .sharp L0059 0058 V42 = DO_FCALL
  .sharp L0059 0059 T38 = ADD_ARRAY_ELEMENT V42 NEXT
  .sharp L0059 0060 T43 = CAST (object) CV1($sizes)
  .sharp L0059 0061 INIT_METHOD_CALL 1 T43 string("contains")
  .sharp L0059 0062 T44 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp L0059 0063 SEND_VAL_EX T44 1
  .sharp L0059 0064 V45 = DO_FCALL
  .sharp L0059 0065 T38 = ADD_ARRAY_ELEMENT V45 NEXT
  .sharp L0059 0066 VERIFY_RETURN_TYPE T38
  .sharp L0059 0067 RETURN T38
  .sharp L0060 0068 VERIFY_RETURN_TYPE
  .sharp L0060 0069 RETURN null
  .sharp      ; (lines=70, args=1, vars=6, tmps=34)
  .sharp L0074 0002 JMPZ T6 0010
  .sharp L0074 0003 V7 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0074 0004 T8 = SHARP_TYPE_ARGS string("string")
  .sharp L0074 0005 V9 = NEW 1 V7 T8
  .sharp L0074 0006 SEND_VAR_EX CV0($maybe) 1
  .sharp L0074 0007 DO_FCALL
  .sharp L0074 0008 T11 = QM_ASSIGN V9
  .sharp L0074 0009 JMP 0011
  .sharp L0074 0010 T11 = QM_ASSIGN CV0($maybe)
  .sharp L0074 0011 ASSIGN CV0($maybe) T11
  .sharp L0076 0012 INIT_METHOD_CALL 0 THIS string("copy")
  .sharp L0076 0013 V13 = DO_FCALL
  .sharp L0076 0014 ASSIGN CV1($copy) V13
  .sharp L0077 0015 T15 = CAST (object) CV1($copy)
  .sharp L0077 0016 INIT_METHOD_CALL 1 T15 string("add")
  .sharp L0077 0017 SEND_VAL_EX string("copy") 1
  .sharp L0077 0018 V16 = DO_FCALL
  .sharp L0077 0019 ASSIGN CV2($copied) V16
  .sharp L0078 0020 T18 = FETCH_OBJ_R THIS string("tags")
  .sharp L0078 0021 INIT_METHOD_CALL 1 T18 string("contains")
  .sharp L0078 0022 SEND_VAL_EX string("copy") 1
  .sharp L0078 0023 V19 = DO_FCALL
  .sharp L0078 0024 ASSIGN CV3($kept) V19
  .sharp L0079 0025 T21 = FETCH_OBJ_R THIS string("tags")
  .sharp L0079 0026 INIT_METHOD_CALL 1 T21 string("add")
  .sharp L0079 0027 INIT_METHOD_CALL 0 THIS string("next")
  .sharp L0079 0028 V22 = DO_FCALL
  .sharp L0079 0029 SEND_VAR_NO_REF_EX V22 1
  .sharp L0079 0030 V23 = DO_FCALL
  .sharp L0079 0031 ASSIGN CV4($added) V23
  .sharp L0080 0032 T25 = FETCH_OBJ_R THIS string("tags")
  .sharp L0080 0033 INIT_METHOD_CALL 0 T25 string("toList")
  .sharp L0080 0034 V26 = DO_FCALL
  .sharp L0080 0035 ASSIGN CV5($named) V26
  .sharp L0081 0036 T28 = FETCH_OBJ_R THIS string("tags")
  .sharp L0081 0037 INIT_METHOD_CALL 0 T28 string("clear")
  .sharp L0081 0038 DO_FCALL
  .sharp L0082 0039 T30 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .sharp L0082 0040 T30 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .sharp L0082 0041 T30 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .sharp L0082 0042 T31 = FETCH_OBJ_R THIS string("calls")
  .sharp L0082 0043 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp L0082 0044 T30 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .sharp L0082 0045 T32 = FETCH_OBJ_R THIS string("tags")
  .sharp L0082 0046 INIT_METHOD_CALL 0 T32 string("toList")
  .sharp L0082 0047 V33 = DO_FCALL
  .sharp L0082 0048 T30 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp L0082 0049 T34 = CAST (object) CV0($maybe)
  .sharp L0082 0050 V35 = JMP_NULL T34 0054
  .sharp L0082 0051 INIT_METHOD_CALL 1 T34 string("add")
  .sharp L0082 0052 SEND_VAL_EX string("x") 1
  .sharp L0082 0053 V35 = DO_FCALL
  .sharp L0082 0054 T30 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp L0082 0055 T36 = CAST (object) CV0($maybe)
  .sharp L0082 0056 V37 = JMP_NULL T36 0060
  .sharp L0082 0057 INIT_METHOD_CALL 1 T36 string("contains")
  .sharp L0082 0058 SEND_VAL_EX string("x") 1
  .sharp L0082 0059 V37 = DO_FCALL
  .sharp L0082 0060 T30 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp L0082 0061 T38 = CAST (object) CV0($maybe)
  .sharp L0082 0062 V39 = JMP_NULL T38 0065
  .sharp L0082 0063 INIT_METHOD_CALL 0 T38 string("count")
  .sharp L0082 0064 V39 = DO_FCALL
  .sharp L0082 0065 T30 = ADD_ARRAY_ELEMENT V39 NEXT
  .sharp L0082 0066 VERIFY_RETURN_TYPE T30
  .sharp L0082 0067 RETURN T30
  .sharp L0083 0068 VERIFY_RETURN_TYPE
  .sharp L0083 0069 RETURN null
  .sharp      ; (lines=39, args=1, vars=4, tmps=22)
  .sharp L0100 0008 V9 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0100 0009 T10 = SHARP_TYPE_ARGS string("string")
  .sharp L0100 0010 V11 = NEW 1 V9 T10
  .sharp L0100 0011 SEND_VAR_EX CV0($names) 1
  .sharp L0100 0012 DO_FCALL
  .sharp L0100 0013 SEND_VAR_NO_REF_EX V11 1
  .sharp L0100 0014 V13 = DO_FCALL
  .sharp L0100 0015 T14 = INIT_ARRAY 3 (packed) V13 NEXT
  .sharp L0100 0016 INIT_DYNAMIC_CALL 1 CV2($check)
  .sharp L0100 0017 V15 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0100 0018 T16 = SHARP_TYPE_ARGS string("string")
  .sharp L0100 0019 V17 = NEW 1 V15 T16
  .sharp L0100 0020 T18 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .sharp L0100 0021 SEND_VAL_EX T18 1
  .sharp L0100 0022 DO_FCALL
  .sharp L0100 0023 SEND_VAR_NO_REF_EX V17 1
  .sharp L0100 0024 V20 = DO_FCALL
  .sharp L0100 0025 T14 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp L0100 0026 INIT_DYNAMIC_CALL 1 CV3($size)
  .sharp L0100 0027 V21 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0100 0028 T22 = SHARP_TYPE_ARGS string("string")
  .sharp L0100 0029 V23 = NEW 1 V21 T22
  .sharp L0100 0030 SEND_VAL_EX array(...) 1
  .sharp L0100 0031 DO_FCALL
  .sharp L0100 0032 SEND_VAR_NO_REF_EX V23 1
  .sharp L0100 0033 V25 = DO_FCALL
  .sharp L0100 0034 T14 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp L0100 0035 VERIFY_RETURN_TYPE T14
  .sharp L0100 0036 RETURN T14
  .sharp L0101 0037 VERIFY_RETURN_TYPE
  .sharp L0101 0038 RETURN null
  .sharp      ; (lines=19, args=1, vars=2, tmps=9)
  .sharp L0096 0003 JMPZ T2 0011
  .sharp L0096 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0096 0005 T4 = SHARP_TYPE_ARGS string("string")
  .sharp L0096 0006 V5 = NEW 1 V3 T4
  .sharp L0096 0007 SEND_VAR_EX CV0($tags) 1
  .sharp L0096 0008 DO_FCALL
  .sharp L0096 0009 T7 = QM_ASSIGN V5
  .sharp L0096 0010 JMP 0012
  .sharp L0096 0011 T7 = QM_ASSIGN CV0($tags)
  .sharp L0096 0012 ASSIGN CV0($tags) T7
  .sharp L0096 0013 T9 = CAST (object) CV0($tags)
  .sharp L0096 0014 INIT_METHOD_CALL 1 T9 string("contains")
  .sharp L0096 0015 SEND_VAR_EX CV1($prefix) 1
  .sharp L0096 0016 V10 = DO_FCALL
  .sharp L0096 0017 RETURN V10
  .sharp L0096 0018 RETURN null
  .sharp      ; (lines=17, args=1, vars=1, tmps=9)
  .sharp L0097 0002 JMPZ T1 0010
  .sharp L0097 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0097 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp L0097 0005 V4 = NEW 1 V2 T3
  .sharp L0097 0006 SEND_VAR_EX CV0($tags) 1
  .sharp L0097 0007 DO_FCALL
  .sharp L0097 0008 T6 = QM_ASSIGN V4
  .sharp L0097 0009 JMP 0011
  .sharp L0097 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp L0097 0011 ASSIGN CV0($tags) T6
  .sharp L0098 0012 T8 = CAST (object) CV0($tags)
  .sharp L0098 0013 INIT_METHOD_CALL 0 T8 string("count")
  .sharp L0098 0014 V9 = DO_FCALL
  .sharp L0098 0015 RETURN V9
  .sharp L0099 0016 RETURN null
  .sharp      ; (lines=13, args=1, vars=1, tmps=6)
  .sharp L0103 0000 CV0($collection#1) = RECV 1
  .sharp L0103 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp L0103 0002 JMPZ T1 0010
  .sharp L0103 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0103 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp L0103 0005 V4 = NEW 1 V2 T3
  .sharp L0103 0006 SEND_VAR_EX CV0($collection#1) 1
  .sharp L0103 0007 DO_FCALL
  .sharp L0103 0008 T6 = QM_ASSIGN V4
  .sharp L0103 0009 JMP 0011
  .sharp L0103 0010 T6 = QM_ASSIGN CV0($collection#1)
  .sharp L0103 0011 RETURN T6
  .sharp L0103 0012 RETURN null
  .sharp      ; (lines=13, args=1, vars=1, tmps=6)
  .sharp L0112 0000 CV0($collection#1) = RECV 1
  .sharp L0112 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp L0112 0002 JMPZ T1 0010
  .sharp L0112 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0112 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp L0112 0005 V4 = NEW 1 V2 T3
  .sharp L0112 0006 SEND_VAR_EX CV0($collection#1) 1
  .sharp L0112 0007 DO_FCALL
  .sharp L0112 0008 T6 = QM_ASSIGN V4
  .sharp L0112 0009 JMP 0011
  .sharp L0112 0010 T6 = QM_ASSIGN CV0($collection#1)
  .sharp L0112 0011 RETURN T6
  .sharp L0112 0012 RETURN null
  .sharp      ; (lines=27, args=2, vars=2, tmps=14)
  .sharp 0003 JMPZ T3 0011
  .sharp 0004 V4 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0005 T5 = SHARP_TYPE_ARGS string("int")
  .sharp 0006 V6 = NEW 1 V4 T5
  .sharp 0007 SEND_VAR_EX CV0($ids) 1
  .sharp 0008 DO_FCALL
  .sharp 0009 T8 = QM_ASSIGN V6
  .sharp 0010 JMP 0012
  .sharp 0011 T8 = QM_ASSIGN CV0($ids)
  .sharp 0012 ASSIGN_OBJ THIS string("ids")
  .sharp 0013 OP_DATA T8
  .sharp 0014 T10 = TYPE_CHECK (array) CV1($labels)
  .sharp 0015 JMPZ T10 0023
  .sharp 0016 V11 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0017 T12 = SHARP_TYPE_ARGS string("string")
  .sharp 0018 V13 = NEW 1 V11 T12
  .sharp 0019 SEND_VAR_EX CV1($labels) 1
  .sharp 0020 DO_FCALL
  .sharp 0021 T15 = QM_ASSIGN V13
  .sharp 0022 JMP 0024
  .sharp 0023 T15 = QM_ASSIGN CV1($labels)
  .sharp 0024 ASSIGN_OBJ THIS string("labels")
  .sharp 0025 OP_DATA T15
  .sharp 0026 RETURN null
  .sharp      6: 0007 - 0009 (new)
  .sharp      13: 0019 - 0021 (new)
  .sharp      ; (lines=67, args=1, vars=7, tmps=33)
  .sharp 0002 JMPZ T7 0010
  .sharp 0003 V8 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T9 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V10 = NEW 1 V8 T9
  .sharp 0006 SEND_VAR_EX CV0($tags) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T12 = QM_ASSIGN V10
  .sharp 0009 JMP 0011
  .sharp 0010 T12 = QM_ASSIGN CV0($tags)
  .sharp 0011 ASSIGN CV0($tags) T12
  .sharp 0012 T14 = CAST (object) CV0($tags)
  .sharp 0013 INIT_METHOD_CALL 1 T14 string("add")
  .sharp 0014 SEND_VAL_EX string("vip") 1
  .sharp 0015 V15 = DO_FCALL
  .sharp 0016 ASSIGN CV1($added) V15
  .sharp 0017 T17 = CAST (object) CV0($tags)
  .sharp 0018 INIT_METHOD_CALL 1 T17 string("add")
  .sharp 0019 SEND_VAL_EX string("vip") 1
  .sharp 0020 V18 = DO_FCALL
  .sharp 0021 ASSIGN CV2($again) V18
  .sharp 0022 T20 = CAST (object) CV0($tags)
  .sharp 0023 INIT_METHOD_CALL 1 T20 string("remove")
  .sharp 0024 SEND_VAL_EX string("new") 1
  .sharp 0025 V21 = DO_FCALL
  .sharp 0026 ASSIGN CV3($removed) V21
  .sharp 0027 T23 = CAST (object) CV0($tags)
  .sharp 0028 INIT_METHOD_CALL 1 T23 string("remove")
  .sharp 0029 SEND_VAL_EX string("none") 1
  .sharp 0031 ASSIGN CV4($absent) V24
  .sharp 0032 ASSIGN CV5($joined) string("")
  .sharp 0033 V27 = FE_RESET_R CV0($tags) 0039
  .sharp 0034 FE_FETCH_R V27 CV6($tag) 0039
  .sharp 0035 T28 = CONCAT CV5($joined) CV6($tag)
  .sharp 0036 T29 = CONCAT T28 string(";")
  .sharp 0037 ASSIGN CV5($joined) T29
  .sharp 0038 JMP 0034
  .sharp 0039 FE_FREE V27
  .sharp 0040 T31 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .sharp 0041 T31 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp 0042 T31 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp 0043 T31 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .sharp 0044 T32 = CAST (object) CV0($tags)
  .sharp 0045 INIT_METHOD_CALL 1 T32 string("contains")
  .sharp 0046 SEND_VAL_EX string("5") 1
  .sharp 0047 V33 = DO_FCALL
  .sharp 0048 T31 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp 0049 T34 = CAST (object) CV0($tags)
  .sharp 0050 INIT_METHOD_CALL 1 T34 string("contains")
  .sharp 0051 SEND_VAL_EX string("new") 1
  .sharp 0052 V35 = DO_FCALL
  .sharp 0053 T31 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp 0054 T36 = CAST (object) CV0($tags)
  .sharp 0055 INIT_METHOD_CALL 0 T36 string("count")
  .sharp 0056 V37 = DO_FCALL
  .sharp 0057 T31 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp 0058 T38 = CAST (object) CV0($tags)
  .sharp 0059 INIT_METHOD_CALL 0 T38 string("toList")
  .sharp 0060 V39 = DO_FCALL
  .sharp 0061 T31 = ADD_ARRAY_ELEMENT V39 NEXT
  .sharp 0062 T31 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .sharp 0063 VERIFY_RETURN_TYPE T31
  .sharp 0064 RETURN T31
  .sharp 0065 VERIFY_RETURN_TYPE
  .sharp 0066 RETURN null
  .sharp      10: 0006 - 0008 (new)
  .sharp      27: 0034 - 0039 (loop)
  .sharp      31: 0041 - 0064 (tmp/var)
  .sharp      ; (lines=57, args=1, vars=1, tmps=27)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($tags) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp 0011 ASSIGN CV0($tags) T6
  .sharp 0012 T8 = CAST (object) CV0($tags)
  .sharp 0013 INIT_METHOD_CALL 1 T8 string("filter")
  .sharp 0014 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0015 SEND_VAL_EX T9 1
  .sharp 0017 T11 = CAST (object) V10
  .sharp 0018 INIT_METHOD_CALL 0 T11 string("toList")
  .sharp 0019 V12 = DO_FCALL
  .sharp 0020 T13 = INIT_ARRAY 6 (packed) V12 NEXT
  .sharp 0021 T14 = CAST (object) CV0($tags)
  .sharp 0022 INIT_METHOD_CALL 1 T14 string("map")
  .sharp 0023 T15 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0024 SEND_VAL_EX T15 1
  .sharp 0025 V16 = DO_FCALL
  .sharp 0026 T13 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp 0027 T17 = CAST (object) CV0($tags)
  .sharp 0028 INIT_METHOD_CALL 1 T17 string("any")
  .sharp 0029 T18 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0030 SEND_VAL_EX T18 1
  .sharp 0031 V19 = DO_FCALL
  .sharp 0032 T13 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp 0033 T20 = CAST (object) CV0($tags)
  .sharp 0034 INIT_METHOD_CALL 1 T20 string("first")
  .sharp 0035 T21 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0036 SEND_VAL_EX T21 1
  .sharp 0037 V22 = DO_FCALL
  .sharp 0038 T13 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp 0039 T23 = CAST (object) CV0($tags)
  .sharp 0040 INIT_METHOD_CALL 1 T23 string("sortedBy")
  .sharp 0041 T24 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0042 SEND_VAL_EX T24 1
  .sharp 0043 V25 = DO_FCALL
  .sharp 0044 T13 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp 0045 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0046 SEND_VAL_EX string(",") 1
  .sharp 0047 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp 0048 SEND_VAR CV0($tags) 1
  .sharp 0049 V26 = DO_ICALL
  .sharp 0050 SEND_VAR_NO_REF_EX V26 2
  .sharp 0051 V27 = DO_FCALL
  .sharp 0052 T13 = ADD_ARRAY_ELEMENT V27 NEXT
  .sharp 0053 VERIFY_RETURN_TYPE T13
  .sharp 0054 RETURN T13
  .sharp 0055 VERIFY_RETURN_TYPE
  .sharp 0056 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      13: 0021 - 0054 (tmp/var)
  .sharp      ; (lines=21, args=1, vars=1, tmps=10)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($tags) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp 0011 ASSIGN CV0($tags) T6
  .sharp 0012 T8 = CAST (object) CV0($tags)
  .sharp 0013 INIT_METHOD_CALL 1 T8 string("first")
  .sharp 0014 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0015 SEND_VAL_EX T9 1
  .sharp 0017 VERIFY_RETURN_TYPE V10
  .sharp 0018 RETURN V10
  .sharp 0019 VERIFY_RETURN_TYPE
  .sharp 0020 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      10: 0017 - 0018 (tmp/var)
  .sharp      ; (lines=56, args=1, vars=2, tmps=28)
  .sharp 0002 JMPZ T2 0010
  .sharp 0003 V3 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T4 = SHARP_TYPE_ARGS string("int")
  .sharp 0005 V5 = NEW 1 V3 T4
  .sharp 0006 SEND_VAR_EX CV0($ids) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T7 = QM_ASSIGN V5
  .sharp 0009 JMP 0011
  .sharp 0010 T7 = QM_ASSIGN CV0($ids)
  .sharp 0011 ASSIGN CV0($ids) T7
  .sharp 0012 V9 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0013 T10 = SHARP_TYPE_ARGS string("int")
  .sharp 0014 V11 = NEW 1 V9 T10
  .sharp 0015 SEND_VAL_EX array(...) 1
  .sharp 0017 ASSIGN CV1($more) V11
  .sharp 0018 T14 = CAST (object) CV0($ids)
  .sharp 0019 INIT_METHOD_CALL 1 T14 string("add")
  .sharp 0020 SEND_VAL_EX int(4) 1
  .sharp 0021 V15 = DO_FCALL
  .sharp 0022 T16 = INIT_ARRAY 7 (packed) V15 NEXT
  .sharp 0023 T17 = CAST (object) CV0($ids)
  .sharp 0024 INIT_METHOD_CALL 1 T17 string("add")
  .sharp 0025 SEND_VAL_EX int(4) 1
  .sharp 0026 V18 = DO_FCALL
  .sharp 0027 T16 = ADD_ARRAY_ELEMENT V18 NEXT
  .sharp 0028 T19 = CAST (object) CV0($ids)
  .sharp 0029 INIT_METHOD_CALL 1 T19 string("remove")
  .sharp 0030 SEND_VAL_EX int(7) 1
  .sharp 0031 V20 = DO_FCALL
  .sharp 0032 T16 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp 0033 T21 = CAST (object) CV0($ids)
  .sharp 0034 INIT_METHOD_CALL 1 T21 string("contains")
  .sharp 0035 SEND_VAL_EX int(2) 1
  .sharp 0036 V22 = DO_FCALL
  .sharp 0037 T16 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp 0038 T23 = CAST (object) CV0($ids)
  .sharp 0039 INIT_METHOD_CALL 0 T23 string("toList")
  .sharp 0040 V24 = DO_FCALL
  .sharp 0041 T16 = ADD_ARRAY_ELEMENT V24 NEXT
  .sharp 0042 T25 = CAST (object) CV1($more)
  .sharp 0043 INIT_METHOD_CALL 0 T25 string("toList")
  .sharp 0044 V26 = DO_FCALL
  .sharp 0045 T16 = ADD_ARRAY_ELEMENT V26 NEXT
  .sharp 0046 T27 = CAST (object) CV0($ids)
  .sharp 0047 INIT_METHOD_CALL 1 T27 string("sumOf")
  .sharp 0048 T28 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0049 SEND_VAL_EX T28 1
  .sharp 0050 V29 = DO_FCALL
  .sharp 0051 T16 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp 0052 VERIFY_RETURN_TYPE T16
  .sharp 0053 RETURN T16
  .sharp 0054 VERIFY_RETURN_TYPE
  .sharp 0055 RETURN null
  .sharp      5: 0006 - 0008 (new)
  .sharp      11: 0015 - 0017 (new)
  .sharp      16: 0023 - 0053 (tmp/var)
  .sharp      ; (lines=70, args=1, vars=6, tmps=40)
  .sharp 0002 JMPZ T6 0010
  .sharp 0003 V7 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T8 = SHARP_TYPE_ARGS string("Lib.Standing")
  .sharp 0005 V9 = NEW 1 V7 T8
  .sharp 0006 SEND_VAR_EX CV0($states) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T11 = QM_ASSIGN V9
  .sharp 0009 JMP 0011
  .sharp 0010 T11 = QM_ASSIGN CV0($states)
  .sharp 0011 ASSIGN CV0($states) T11
  .sharp 0012 V13 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0013 T14 = SHARP_TYPE_ARGS string("Lib.Size")
  .sharp 0014 V15 = NEW 1 V13 T14
  .sharp 0015 T16 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .sharp 0016 T17 = INIT_ARRAY 3 (packed) T16 NEXT
  .sharp 0017 T18 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp 0018 T17 = ADD_ARRAY_ELEMENT T18 NEXT
  .sharp 0019 T19 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .sharp 0020 T17 = ADD_ARRAY_ELEMENT T19 NEXT
  .sharp 0021 SEND_VAL_EX T17 1
  .sharp 0022 DO_FCALL
  .sharp 0023 ASSIGN CV1($sizes) V15
  .sharp 0024 T22 = CAST (object) CV0($states)
  .sharp 0025 INIT_METHOD_CALL 1 T22 string("add")
  .sharp 0026 T23 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0027 SEND_VAL_EX T23 1
  .sharp 0029 ASSIGN CV2($closed) V24
  .sharp 0030 T26 = CAST (object) CV0($states)
  .sharp 0031 INIT_METHOD_CALL 1 T26 string("add")
  .sharp 0032 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0033 SEND_VAL_EX T27 1
  .sharp 0034 V28 = DO_FCALL
  .sharp 0035 ASSIGN CV3($active) V28
  .sharp 0036 T30 = CAST (object) CV0($states)
  .sharp 0037 INIT_METHOD_CALL 1 T30 string("contains")
  .sharp 0038 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0039 SEND_VAL_EX T31 1
  .sharp 0040 V32 = DO_FCALL
  .sharp 0041 ASSIGN CV4($has) V32
  .sharp 0042 T34 = CAST (object) CV0($states)
  .sharp 0043 INIT_METHOD_CALL 1 T34 string("remove")
  .sharp 0044 T35 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0045 SEND_VAL_EX T35 1
  .sharp 0046 V36 = DO_FCALL
  .sharp 0047 ASSIGN CV5($removed) V36
  .sharp 0048 T38 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .sharp 0049 T38 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .sharp 0050 T38 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .sharp 0051 T38 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .sharp 0052 T39 = CAST (object) CV0($states)
  .sharp 0053 INIT_METHOD_CALL 0 T39 string("toList")
  .sharp 0054 V40 = DO_FCALL
  .sharp 0055 T38 = ADD_ARRAY_ELEMENT V40 NEXT
  .sharp 0056 T41 = CAST (object) CV1($sizes)
  .sharp 0057 INIT_METHOD_CALL 0 T41 string("toList")
  .sharp 0058 V42 = DO_FCALL
  .sharp 0059 T38 = ADD_ARRAY_ELEMENT V42 NEXT
  .sharp 0060 T43 = CAST (object) CV1($sizes)
  .sharp 0061 INIT_METHOD_CALL 1 T43 string("contains")
  .sharp 0062 T44 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp 0063 SEND_VAL_EX T44 1
  .sharp 0064 V45 = DO_FCALL
  .sharp 0065 T38 = ADD_ARRAY_ELEMENT V45 NEXT
  .sharp 0066 VERIFY_RETURN_TYPE T38
  .sharp 0067 RETURN T38
  .sharp 0068 VERIFY_RETURN_TYPE
  .sharp 0069 RETURN null
  .sharp      9: 0006 - 0008 (new)
  .sharp      15: 0015 - 0023 (new)
  .sharp      17: 0017 - 0021 (tmp/var)
  .sharp      38: 0049 - 0067 (tmp/var)
  .sharp      ; (lines=70, args=1, vars=6, tmps=34)
  .sharp 0002 JMPZ T6 0010
  .sharp 0003 V7 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T8 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V9 = NEW 1 V7 T8
  .sharp 0006 SEND_VAR_EX CV0($maybe) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T11 = QM_ASSIGN V9
  .sharp 0009 JMP 0011
  .sharp 0010 T11 = QM_ASSIGN CV0($maybe)
  .sharp 0011 ASSIGN CV0($maybe) T11
  .sharp 0012 INIT_METHOD_CALL 0 THIS string("copy")
  .sharp 0014 ASSIGN CV1($copy) V13
  .sharp 0015 T15 = CAST (object) CV1($copy)
  .sharp 0017 SEND_VAL_EX string("copy") 1
  .sharp 0019 ASSIGN CV2($copied) V16
  .sharp 0020 T18 = FETCH_OBJ_R THIS string("tags")
  .sharp 0021 INIT_METHOD_CALL 1 T18 string("contains")
  .sharp 0022 SEND_VAL_EX string("copy") 1
  .sharp 0024 ASSIGN CV3($kept) V19
  .sharp 0025 T21 = FETCH_OBJ_R THIS string("tags")
  .sharp 0026 INIT_METHOD_CALL 1 T21 string("add")
  .sharp 0027 INIT_METHOD_CALL 0 THIS string("next")
  .sharp 0029 SEND_VAR_NO_REF_EX V22 1
  .sharp 0030 V23 = DO_FCALL
  .sharp 0031 ASSIGN CV4($added) V23
  .sharp 0032 T25 = FETCH_OBJ_R THIS string("tags")
  .sharp 0033 INIT_METHOD_CALL 0 T25 string("toList")
  .sharp 0034 V26 = DO_FCALL
  .sharp 0035 ASSIGN CV5($named) V26
  .sharp 0036 T28 = FETCH_OBJ_R THIS string("tags")
  .sharp 0037 INIT_METHOD_CALL 0 T28 string("clear")
  .sharp 0038 DO_FCALL
  .sharp 0039 T30 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .sharp 0040 T30 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .sharp 0041 T30 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .sharp 0042 T31 = FETCH_OBJ_R THIS string("calls")
  .sharp 0043 T30 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp 0044 T30 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .sharp 0045 T32 = FETCH_OBJ_R THIS string("tags")
  .sharp 0046 INIT_METHOD_CALL 0 T32 string("toList")
  .sharp 0047 V33 = DO_FCALL
  .sharp 0048 T30 = ADD_ARRAY_ELEMENT V33 NEXT
  .sharp 0049 T34 = CAST (object) CV0($maybe)
  .sharp 0050 V35 = JMP_NULL T34 0054
  .sharp 0051 INIT_METHOD_CALL 1 T34 string("add")
  .sharp 0052 SEND_VAL_EX string("x") 1
  .sharp 0053 V35 = DO_FCALL
  .sharp 0054 T30 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp 0055 T36 = CAST (object) CV0($maybe)
  .sharp 0056 V37 = JMP_NULL T36 0060
  .sharp 0057 INIT_METHOD_CALL 1 T36 string("contains")
  .sharp 0058 SEND_VAL_EX string("x") 1
  .sharp 0059 V37 = DO_FCALL
  .sharp 0060 T30 = ADD_ARRAY_ELEMENT V37 NEXT
  .sharp 0061 T38 = CAST (object) CV0($maybe)
  .sharp 0062 V39 = JMP_NULL T38 0065
  .sharp 0063 INIT_METHOD_CALL 0 T38 string("count")
  .sharp 0064 V39 = DO_FCALL
  .sharp 0065 T30 = ADD_ARRAY_ELEMENT V39 NEXT
  .sharp 0066 VERIFY_RETURN_TYPE T30
  .sharp 0067 RETURN T30
  .sharp 0068 VERIFY_RETURN_TYPE
  .sharp 0069 RETURN null
  .sharp      9: 0006 - 0008 (new)
  .sharp      30: 0040 - 0067 (tmp/var)
  .sharp      34: 0050 - 0051 (tmp/var)
  .sharp      36: 0056 - 0057 (tmp/var)
  .sharp      38: 0062 - 0063 (tmp/var)
  .sharp      ; (lines=39, args=1, vars=4, tmps=22)
  .sharp 0008 V9 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0009 T10 = SHARP_TYPE_ARGS string("string")
  .sharp 0010 V11 = NEW 1 V9 T10
  .sharp 0011 SEND_VAR_EX CV0($names) 1
  .sharp 0013 SEND_VAR_NO_REF_EX V11 1
  .sharp 0014 V13 = DO_FCALL
  .sharp 0015 T14 = INIT_ARRAY 3 (packed) V13 NEXT
  .sharp 0016 INIT_DYNAMIC_CALL 1 CV2($check)
  .sharp 0017 V15 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0018 T16 = SHARP_TYPE_ARGS string("string")
  .sharp 0019 V17 = NEW 1 V15 T16
  .sharp 0020 T18 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .sharp 0021 SEND_VAL_EX T18 1
  .sharp 0022 DO_FCALL
  .sharp 0023 SEND_VAR_NO_REF_EX V17 1
  .sharp 0025 T14 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp 0026 INIT_DYNAMIC_CALL 1 CV3($size)
  .sharp 0027 V21 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0028 T22 = SHARP_TYPE_ARGS string("string")
  .sharp 0029 V23 = NEW 1 V21 T22
  .sharp 0030 SEND_VAL_EX array(...) 1
  .sharp 0031 DO_FCALL
  .sharp 0032 SEND_VAR_NO_REF_EX V23 1
  .sharp 0033 V25 = DO_FCALL
  .sharp 0034 T14 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp 0035 VERIFY_RETURN_TYPE T14
  .sharp 0036 RETURN T14
  .sharp 0037 VERIFY_RETURN_TYPE
  .sharp 0038 RETURN null
  .sharp      11: 0011 - 0013 (new)
  .sharp      14: 0016 - 0036 (tmp/var)
  .sharp      17: 0020 - 0023 (new)
  .sharp      23: 0030 - 0032 (new)
  .sharp      ; (lines=19, args=1, vars=2, tmps=9)
  .sharp 0003 JMPZ T2 0011
  .sharp 0004 V3 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0005 T4 = SHARP_TYPE_ARGS string("string")
  .sharp 0006 V5 = NEW 1 V3 T4
  .sharp 0007 SEND_VAR_EX CV0($tags) 1
  .sharp 0008 DO_FCALL
  .sharp 0009 T7 = QM_ASSIGN V5
  .sharp 0010 JMP 0012
  .sharp 0011 T7 = QM_ASSIGN CV0($tags)
  .sharp 0012 ASSIGN CV0($tags) T7
  .sharp 0013 T9 = CAST (object) CV0($tags)
  .sharp 0015 SEND_VAR_EX CV1($prefix) 1
  .sharp 0017 RETURN V10
  .sharp      5: 0007 - 0009 (new)
  .sharp      ; (lines=17, args=1, vars=1, tmps=9)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($tags) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($tags)
  .sharp 0011 ASSIGN CV0($tags) T6
  .sharp 0012 T8 = CAST (object) CV0($tags)
  .sharp 0013 INIT_METHOD_CALL 0 T8 string("count")
  .sharp 0014 V9 = DO_FCALL
  .sharp 0015 RETURN V9
  .sharp      4: 0006 - 0008 (new)
  .sharp      ; (lines=13, args=1, vars=1, tmps=6)
  .sharp 0000 CV0($collection#1) = RECV 1
  .sharp 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($collection#1) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($collection#1)
  .sharp 0011 RETURN T6
  .sharp 0012 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      ; (lines=13, args=1, vars=1, tmps=6)
  .sharp 0000 CV0($collection#1) = RECV 1
  .sharp 0001 T1 = TYPE_CHECK (array) CV0($collection#1)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($collection#1) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($collection#1)
  .sharp 0011 RETURN T6
  .sharp 0012 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      ; (lines=17, args=1, vars=1, tmps=9)
  .sharp 0002 JMPZ T1 0010
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("Lib.Standing")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 SEND_VAR_EX CV0($value) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T6 = QM_ASSIGN V4
  .sharp 0009 JMP 0011
  .sharp 0010 T6 = QM_ASSIGN CV0($value)
  .sharp 0011 ASSIGN CV0($value) T6
  .sharp 0012 ASSIGN_OBJ_OP (ADD) THIS string("writes")
  .sharp 0013 OP_DATA int(1)
  .sharp 0014 ASSIGN_OBJ THIS string("states")
  .sharp 0015 OP_DATA CV0($value)
  .sharp      4: 0006 - 0008 (new)
  .sharp     Property [ public Sharp\Set $tags = Closure({closure:<file>:9})() ]
  .sharp     Property [ public Sharp\Set $states = Closure({closure:<file>:10})() { get; set; } ]
  .sharp     Property [ private Sharp\Set $sizes = Closure({closure:<file>:12})() ]
  .php        ; (lines=23, args=2, vars=2, tmps=10)
  .php   L0015 0003 JMPZ T3 0009
  .php   L0015 0004 V4 = NEW 1 string("Sharp\\Set")
  .php   L0015 0005 SEND_VAR_EX CV0($ids) 1
  .php   L0015 0006 DO_FCALL
  .php   L0015 0007 T6 = QM_ASSIGN V4
  .php   L0015 0008 JMP 0010
  .php   L0015 0009 T6 = QM_ASSIGN CV0($ids)
  .php   L0015 0010 ASSIGN_OBJ THIS string("ids")
  .php   L0015 0011 OP_DATA T6
  .php   L0015 0012 T8 = TYPE_CHECK (array) CV1($labels)
  .php   L0015 0013 JMPZ T8 0019
  .php   L0015 0014 V9 = NEW 1 string("Sharp\\Set")
  .php   L0015 0015 SEND_VAR_EX CV1($labels) 1
  .php   L0015 0016 DO_FCALL
  .php   L0015 0017 T11 = QM_ASSIGN V9
  .php   L0015 0018 JMP 0020
  .php   L0015 0019 T11 = QM_ASSIGN CV1($labels)
  .php   L0015 0020 ASSIGN_OBJ THIS string("labels")
  .php   L0015 0021 OP_DATA T11
  .php   L0017 0022 RETURN null
  .php        ; (lines=65, args=1, vars=7, tmps=31)
  .php   L0019 0002 JMPZ T7 0008
  .php   L0019 0003 V8 = NEW 1 string("Sharp\\Set")
  .php   L0019 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0019 0005 DO_FCALL
  .php   L0019 0006 T10 = QM_ASSIGN V8
  .php   L0019 0007 JMP 0009
  .php   L0019 0008 T10 = QM_ASSIGN CV0($tags)
  .php   L0019 0009 ASSIGN CV0($tags) T10
  .php   L0021 0010 T12 = CAST (object) CV0($tags)
  .php   L0021 0011 INIT_METHOD_CALL 1 T12 string("add")
  .php   L0021 0012 SEND_VAL_EX string("vip") 1
  .php   L0021 0013 V13 = DO_FCALL
  .php   L0021 0014 ASSIGN CV1($added) V13
  .php   L0022 0015 T15 = CAST (object) CV0($tags)
  .php   L0022 0016 INIT_METHOD_CALL 1 T15 string("add")
  .php   L0022 0017 SEND_VAL_EX string("vip") 1
  .php   L0022 0018 V16 = DO_FCALL
  .php   L0022 0019 ASSIGN CV2($again) V16
  .php   L0023 0020 T18 = CAST (object) CV0($tags)
  .php   L0023 0021 INIT_METHOD_CALL 1 T18 string("remove")
  .php   L0023 0022 SEND_VAL_EX string("new") 1
  .php   L0023 0023 V19 = DO_FCALL
  .php   L0023 0024 ASSIGN CV3($removed) V19
  .php   L0024 0025 T21 = CAST (object) CV0($tags)
  .php   L0024 0026 INIT_METHOD_CALL 1 T21 string("remove")
  .php   L0024 0027 SEND_VAL_EX string("none") 1
  .php   L0024 0028 V22 = DO_FCALL
  .php   L0024 0029 ASSIGN CV4($absent) V22
  .php   L0025 0030 ASSIGN CV5($joined) string("")
  .php   L0026 0031 V25 = FE_RESET_R CV0($tags) 0037
  .php   L0026 0032 FE_FETCH_R V25 CV6($tag) 0037
  .php   L0027 0033 T26 = CONCAT CV5($joined) CV6($tag)
  .php   L0027 0034 T27 = CONCAT T26 string(";")
  .php   L0027 0035 ASSIGN CV5($joined) T27
  .php   L0026 0036 JMP 0032
  .php   L0026 0037 FE_FREE V25
  .php   L0029 0038 T29 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .php   L0029 0039 T29 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   L0029 0040 T29 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   L0029 0041 T29 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .php   L0029 0042 T30 = CAST (object) CV0($tags)
  .php   L0029 0043 INIT_METHOD_CALL 1 T30 string("contains")
  .php   L0029 0044 SEND_VAL_EX string("5") 1
  .php   L0029 0045 V31 = DO_FCALL
  .php   L0029 0046 T29 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   L0029 0047 T32 = CAST (object) CV0($tags)
  .php   L0029 0048 INIT_METHOD_CALL 1 T32 string("contains")
  .php   L0029 0049 SEND_VAL_EX string("new") 1
  .php   L0029 0050 V33 = DO_FCALL
  .php   L0029 0051 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   L0029 0052 T34 = CAST (object) CV0($tags)
  .php   L0029 0053 INIT_METHOD_CALL 0 T34 string("count")
  .php   L0029 0054 V35 = DO_FCALL
  .php   L0029 0055 T29 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   L0029 0056 T36 = CAST (object) CV0($tags)
  .php   L0029 0057 INIT_METHOD_CALL 0 T36 string("toList")
  .php   L0029 0058 V37 = DO_FCALL
  .php   L0029 0059 T29 = ADD_ARRAY_ELEMENT V37 NEXT
  .php   L0029 0060 T29 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .php   L0029 0061 VERIFY_RETURN_TYPE T29
  .php   L0029 0062 RETURN T29
  .php   L0030 0063 VERIFY_RETURN_TYPE
  .php   L0030 0064 RETURN null
  .php        ; (lines=55, args=1, vars=1, tmps=25)
  .php   L0032 0002 JMPZ T1 0008
  .php   L0032 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0032 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0032 0005 DO_FCALL
  .php   L0032 0006 T4 = QM_ASSIGN V2
  .php   L0032 0007 JMP 0009
  .php   L0032 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0032 0009 ASSIGN CV0($tags) T4
  .php   L0035 0010 T6 = CAST (object) CV0($tags)
  .php   L0035 0011 INIT_METHOD_CALL 1 T6 string("filter")
  .php   L0035 0012 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0035 0013 SEND_VAL_EX T7 1
  .php   L0035 0014 V8 = DO_FCALL
  .php   L0035 0015 T9 = CAST (object) V8
  .php   L0035 0016 INIT_METHOD_CALL 0 T9 string("toList")
  .php   L0035 0017 V10 = DO_FCALL
  .php   L0035 0018 T11 = INIT_ARRAY 6 (packed) V10 NEXT
  .php   L0036 0019 T12 = CAST (object) CV0($tags)
  .php   L0036 0020 INIT_METHOD_CALL 1 T12 string("map")
  .php   L0036 0021 T13 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0036 0022 SEND_VAL_EX T13 1
  .php   L0036 0023 V14 = DO_FCALL
  .php   L0036 0024 T11 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   L0037 0025 T15 = CAST (object) CV0($tags)
  .php   L0037 0026 INIT_METHOD_CALL 1 T15 string("any")
  .php   L0037 0027 T16 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0037 0028 SEND_VAL_EX T16 1
  .php   L0037 0029 V17 = DO_FCALL
  .php   L0037 0030 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .php   L0038 0031 T18 = CAST (object) CV0($tags)
  .php   L0038 0032 INIT_METHOD_CALL 1 T18 string("first")
  .php   L0038 0033 T19 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0038 0034 SEND_VAL_EX T19 1
  .php   L0038 0035 V20 = DO_FCALL
  .php   L0038 0036 T11 = ADD_ARRAY_ELEMENT V20 NEXT
  .php   L0039 0037 T21 = CAST (object) CV0($tags)
  .php   L0039 0038 INIT_METHOD_CALL 1 T21 string("sortedBy")
  .php   L0039 0039 T22 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0039 0040 SEND_VAL_EX T22 1
  .php   L0039 0041 V23 = DO_FCALL
  .php   L0039 0042 T11 = ADD_ARRAY_ELEMENT V23 NEXT
  .php   L0040 0043 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0040 0044 SEND_VAL_EX string(",") 1
  .php   L0040 0045 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   L0040 0046 SEND_VAR CV0($tags) 1
  .php   L0040 0047 V24 = DO_ICALL
  .php   L0040 0048 SEND_VAR_NO_REF_EX V24 2
  .php   L0040 0049 V25 = DO_FCALL
  .php   L0040 0050 T11 = ADD_ARRAY_ELEMENT V25 NEXT
  .php   L0040 0051 VERIFY_RETURN_TYPE T11
  .php   L0040 0052 RETURN T11
  .php   L0042 0053 VERIFY_RETURN_TYPE
  .php   L0042 0054 RETURN null
  .php        ; (lines=19, args=1, vars=1, tmps=8)
  .php   L0044 0002 JMPZ T1 0008
  .php   L0044 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0044 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0044 0005 DO_FCALL
  .php   L0044 0006 T4 = QM_ASSIGN V2
  .php   L0044 0007 JMP 0009
  .php   L0044 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0044 0009 ASSIGN CV0($tags) T4
  .php   L0044 0010 T6 = CAST (object) CV0($tags)
  .php   L0044 0011 INIT_METHOD_CALL 1 T6 string("first")
  .php   L0044 0012 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0044 0013 SEND_VAL_EX T7 1
  .php   L0044 0014 V8 = DO_FCALL
  .php   L0044 0015 VERIFY_RETURN_TYPE V8
  .php   L0044 0016 RETURN V8
  .php   L0044 0017 VERIFY_RETURN_TYPE
  .php   L0044 0018 RETURN null
  .php        ; (lines=52, args=1, vars=2, tmps=24)
  .php   L0046 0002 JMPZ T2 0008
  .php   L0046 0003 V3 = NEW 1 string("Sharp\\Set")
  .php   L0046 0004 SEND_VAR_EX CV0($ids) 1
  .php   L0046 0005 DO_FCALL
  .php   L0046 0006 T5 = QM_ASSIGN V3
  .php   L0046 0007 JMP 0009
  .php   L0046 0008 T5 = QM_ASSIGN CV0($ids)
  .php   L0046 0009 ASSIGN CV0($ids) T5
  .php   L0048 0010 V7 = NEW 1 string("Sharp\\Set")
  .php   L0048 0011 SEND_VAL_EX array(...) 1
  .php   L0048 0012 DO_FCALL
  .php   L0048 0013 ASSIGN CV1($more) V7
  .php   L0049 0014 T10 = CAST (object) CV0($ids)
  .php   L0049 0015 INIT_METHOD_CALL 1 T10 string("add")
  .php   L0049 0016 SEND_VAL_EX int(4) 1
  .php   L0049 0017 V11 = DO_FCALL
  .php   L0049 0018 T12 = INIT_ARRAY 7 (packed) V11 NEXT
  .php   L0049 0019 T13 = CAST (object) CV0($ids)
  .php   L0049 0020 INIT_METHOD_CALL 1 T13 string("add")
  .php   L0049 0021 SEND_VAL_EX int(4) 1
  .php   L0049 0022 V14 = DO_FCALL
  .php   L0049 0023 T12 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   L0049 0024 T15 = CAST (object) CV0($ids)
  .php   L0049 0025 INIT_METHOD_CALL 1 T15 string("remove")
  .php   L0049 0026 SEND_VAL_EX int(7) 1
  .php   L0049 0027 V16 = DO_FCALL
  .php   L0049 0028 T12 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   L0049 0029 T17 = CAST (object) CV0($ids)
  .php   L0049 0030 INIT_METHOD_CALL 1 T17 string("contains")
  .php   L0049 0031 SEND_VAL_EX int(2) 1
  .php   L0049 0032 V18 = DO_FCALL
  .php   L0049 0033 T12 = ADD_ARRAY_ELEMENT V18 NEXT
  .php   L0049 0034 T19 = CAST (object) CV0($ids)
  .php   L0049 0035 INIT_METHOD_CALL 0 T19 string("toList")
  .php   L0049 0036 V20 = DO_FCALL
  .php   L0049 0037 T12 = ADD_ARRAY_ELEMENT V20 NEXT
  .php   L0049 0038 T21 = CAST (object) CV1($more)
  .php   L0049 0039 INIT_METHOD_CALL 0 T21 string("toList")
  .php   L0049 0040 V22 = DO_FCALL
  .php   L0049 0041 T12 = ADD_ARRAY_ELEMENT V22 NEXT
  .php   L0049 0042 T23 = CAST (object) CV0($ids)
  .php   L0049 0043 INIT_METHOD_CALL 1 T23 string("sumOf")
  .php   L0049 0044 T24 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0049 0045 SEND_VAL_EX T24 1
  .php   L0049 0046 V25 = DO_FCALL
  .php   L0049 0047 T12 = ADD_ARRAY_ELEMENT V25 NEXT
  .php   L0049 0048 VERIFY_RETURN_TYPE T12
  .php   L0049 0049 RETURN T12
  .php   L0050 0050 VERIFY_RETURN_TYPE
  .php   L0050 0051 RETURN null
  .php        ; (lines=66, args=1, vars=6, tmps=36)
  .php   L0052 0002 JMPZ T6 0008
  .php   L0052 0003 V7 = NEW 1 string("Sharp\\Set")
  .php   L0052 0004 SEND_VAR_EX CV0($states) 1
  .php   L0052 0005 DO_FCALL
  .php   L0052 0006 T9 = QM_ASSIGN V7
  .php   L0052 0007 JMP 0009
  .php   L0052 0008 T9 = QM_ASSIGN CV0($states)
  .php   L0052 0009 ASSIGN CV0($states) T9
  .php   L0054 0010 V11 = NEW 1 string("Sharp\\Set")
  .php   L0054 0011 T12 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .php   L0054 0012 T13 = INIT_ARRAY 3 (packed) T12 NEXT
  .php   L0054 0013 T14 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   L0054 0014 T13 = ADD_ARRAY_ELEMENT T14 NEXT
  .php   L0054 0015 T15 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .php   L0054 0016 T13 = ADD_ARRAY_ELEMENT T15 NEXT
  .php   L0054 0017 SEND_VAL_EX T13 1
  .php   L0054 0018 DO_FCALL
  .php   L0054 0019 ASSIGN CV1($sizes) V11
  .php   L0055 0020 T18 = CAST (object) CV0($states)
  .php   L0055 0021 INIT_METHOD_CALL 1 T18 string("add")
  .php   L0055 0022 T19 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0055 0023 SEND_VAL_EX T19 1
  .php   L0055 0024 V20 = DO_FCALL
  .php   L0055 0025 ASSIGN CV2($closed) V20
  .php   L0056 0026 T22 = CAST (object) CV0($states)
  .php   L0056 0027 INIT_METHOD_CALL 1 T22 string("add")
  .php   L0056 0028 T23 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0056 0029 SEND_VAL_EX T23 1
  .php   L0056 0030 V24 = DO_FCALL
  .php   L0056 0031 ASSIGN CV3($active) V24
  .php   L0057 0032 T26 = CAST (object) CV0($states)
  .php   L0057 0033 INIT_METHOD_CALL 1 T26 string("contains")
  .php   L0057 0034 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0057 0035 SEND_VAL_EX T27 1
  .php   L0057 0036 V28 = DO_FCALL
  .php   L0057 0037 ASSIGN CV4($has) V28
  .php   L0058 0038 T30 = CAST (object) CV0($states)
  .php   L0058 0039 INIT_METHOD_CALL 1 T30 string("remove")
  .php   L0058 0040 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0058 0041 SEND_VAL_EX T31 1
  .php   L0058 0042 V32 = DO_FCALL
  .php   L0058 0043 ASSIGN CV5($removed) V32
  .php   L0059 0044 T34 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .php   L0059 0045 T34 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .php   L0059 0046 T34 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .php   L0059 0047 T34 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .php   L0059 0048 T35 = CAST (object) CV0($states)
  .php   L0059 0049 INIT_METHOD_CALL 0 T35 string("toList")
  .php   L0059 0050 V36 = DO_FCALL
  .php   L0059 0051 T34 = ADD_ARRAY_ELEMENT V36 NEXT
  .php   L0059 0052 T37 = CAST (object) CV1($sizes)
  .php   L0059 0053 INIT_METHOD_CALL 0 T37 string("toList")
  .php   L0059 0054 V38 = DO_FCALL
  .php   L0059 0055 T34 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   L0059 0056 T39 = CAST (object) CV1($sizes)
  .php   L0059 0057 INIT_METHOD_CALL 1 T39 string("contains")
  .php   L0059 0058 T40 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   L0059 0059 SEND_VAL_EX T40 1
  .php   L0059 0060 V41 = DO_FCALL
  .php   L0059 0061 T34 = ADD_ARRAY_ELEMENT V41 NEXT
  .php   L0059 0062 VERIFY_RETURN_TYPE T34
  .php   L0059 0063 RETURN T34
  .php   L0060 0064 VERIFY_RETURN_TYPE
  .php   L0060 0065 RETURN null
  .php        ; (lines=68, args=1, vars=6, tmps=32)
  .php   L0074 0002 JMPZ T6 0008
  .php   L0074 0003 V7 = NEW 1 string("Sharp\\Set")
  .php   L0074 0004 SEND_VAR_EX CV0($maybe) 1
  .php   L0074 0005 DO_FCALL
  .php   L0074 0006 T9 = QM_ASSIGN V7
  .php   L0074 0007 JMP 0009
  .php   L0074 0008 T9 = QM_ASSIGN CV0($maybe)
  .php   L0074 0009 ASSIGN CV0($maybe) T9
  .php   L0076 0010 INIT_METHOD_CALL 0 THIS string("copy")
  .php   L0076 0011 V11 = DO_FCALL
  .php   L0076 0012 ASSIGN CV1($copy) V11
  .php   L0077 0013 T13 = CAST (object) CV1($copy)
  .php   L0077 0014 INIT_METHOD_CALL 1 T13 string("add")
  .php   L0077 0015 SEND_VAL_EX string("copy") 1
  .php   L0077 0016 V14 = DO_FCALL
  .php   L0077 0017 ASSIGN CV2($copied) V14
  .php   L0078 0018 T16 = FETCH_OBJ_R THIS string("tags")
  .php   L0078 0019 INIT_METHOD_CALL 1 T16 string("contains")
  .php   L0078 0020 SEND_VAL_EX string("copy") 1
  .php   L0078 0021 V17 = DO_FCALL
  .php   L0078 0022 ASSIGN CV3($kept) V17
  .php   L0079 0023 T19 = FETCH_OBJ_R THIS string("tags")
  .php   L0079 0024 INIT_METHOD_CALL 1 T19 string("add")
  .php   L0079 0025 INIT_METHOD_CALL 0 THIS string("next")
  .php   L0079 0026 V20 = DO_FCALL
  .php   L0079 0027 SEND_VAR_NO_REF_EX V20 1
  .php   L0079 0028 V21 = DO_FCALL
  .php   L0079 0029 ASSIGN CV4($added) V21
  .php   L0080 0030 T23 = FETCH_OBJ_R THIS string("tags")
  .php   L0080 0031 INIT_METHOD_CALL 0 T23 string("toList")
  .php   L0080 0032 V24 = DO_FCALL
  .php   L0080 0033 ASSIGN CV5($named) V24
  .php   L0081 0034 T26 = FETCH_OBJ_R THIS string("tags")
  .php   L0081 0035 INIT_METHOD_CALL 0 T26 string("clear")
  .php   L0081 0036 DO_FCALL
  .php   L0082 0037 T28 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .php   L0082 0038 T28 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .php   L0082 0039 T28 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .php   L0082 0040 T29 = FETCH_OBJ_R THIS string("calls")
  .php   L0082 0041 T28 = ADD_ARRAY_ELEMENT T29 NEXT
  .php   L0082 0042 T28 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .php   L0082 0043 T30 = FETCH_OBJ_R THIS string("tags")
  .php   L0082 0044 INIT_METHOD_CALL 0 T30 string("toList")
  .php   L0082 0045 V31 = DO_FCALL
  .php   L0082 0046 T28 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   L0082 0047 T32 = CAST (object) CV0($maybe)
  .php   L0082 0048 V33 = JMP_NULL T32 0052
  .php   L0082 0049 INIT_METHOD_CALL 1 T32 string("add")
  .php   L0082 0050 SEND_VAL_EX string("x") 1
  .php   L0082 0051 V33 = DO_FCALL
  .php   L0082 0052 T28 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   L0082 0053 T34 = CAST (object) CV0($maybe)
  .php   L0082 0054 V35 = JMP_NULL T34 0058
  .php   L0082 0055 INIT_METHOD_CALL 1 T34 string("contains")
  .php   L0082 0056 SEND_VAL_EX string("x") 1
  .php   L0082 0057 V35 = DO_FCALL
  .php   L0082 0058 T28 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   L0082 0059 T36 = CAST (object) CV0($maybe)
  .php   L0082 0060 V37 = JMP_NULL T36 0063
  .php   L0082 0061 INIT_METHOD_CALL 0 T36 string("count")
  .php   L0082 0062 V37 = DO_FCALL
  .php   L0082 0063 T28 = ADD_ARRAY_ELEMENT V37 NEXT
  .php   L0082 0064 VERIFY_RETURN_TYPE T28
  .php   L0082 0065 RETURN T28
  .php   L0083 0066 VERIFY_RETURN_TYPE
  .php   L0083 0067 RETURN null
  .php        ; (lines=33, args=1, vars=4, tmps=16)
  .php   L0100 0008 V9 = NEW 1 string("Sharp\\Set")
  .php   L0100 0009 SEND_VAR_EX CV0($names) 1
  .php   L0100 0010 DO_FCALL
  .php   L0100 0011 SEND_VAR_NO_REF_EX V9 1
  .php   L0100 0012 V11 = DO_FCALL
  .php   L0100 0013 T12 = INIT_ARRAY 3 (packed) V11 NEXT
  .php   L0100 0014 INIT_DYNAMIC_CALL 1 CV2($check)
  .php   L0100 0015 V13 = NEW 1 string("Sharp\\Set")
  .php   L0100 0016 T14 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .php   L0100 0017 SEND_VAL_EX T14 1
  .php   L0100 0018 DO_FCALL
  .php   L0100 0019 SEND_VAR_NO_REF_EX V13 1
  .php   L0100 0020 V16 = DO_FCALL
  .php   L0100 0021 T12 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   L0100 0022 INIT_DYNAMIC_CALL 1 CV3($size)
  .php   L0100 0023 V17 = NEW 1 string("Sharp\\Set")
  .php   L0100 0024 SEND_VAL_EX array(...) 1
  .php   L0100 0025 DO_FCALL
  .php   L0100 0026 SEND_VAR_NO_REF_EX V17 1
  .php   L0100 0027 V19 = DO_FCALL
  .php   L0100 0028 T12 = ADD_ARRAY_ELEMENT V19 NEXT
  .php   L0100 0029 VERIFY_RETURN_TYPE T12
  .php   L0100 0030 RETURN T12
  .php   L0101 0031 VERIFY_RETURN_TYPE
  .php   L0101 0032 RETURN null
  .php        ; (lines=17, args=1, vars=2, tmps=7)
  .php   L0096 0003 JMPZ T2 0009
  .php   L0096 0004 V3 = NEW 1 string("Sharp\\Set")
  .php   L0096 0005 SEND_VAR_EX CV0($tags) 1
  .php   L0096 0006 DO_FCALL
  .php   L0096 0007 T5 = QM_ASSIGN V3
  .php   L0096 0008 JMP 0010
  .php   L0096 0009 T5 = QM_ASSIGN CV0($tags)
  .php   L0096 0010 ASSIGN CV0($tags) T5
  .php   L0096 0011 T7 = CAST (object) CV0($tags)
  .php   L0096 0012 INIT_METHOD_CALL 1 T7 string("contains")
  .php   L0096 0013 SEND_VAR_EX CV1($prefix) 1
  .php   L0096 0014 V8 = DO_FCALL
  .php   L0096 0015 RETURN V8
  .php   L0096 0016 RETURN null
  .php        ; (lines=15, args=1, vars=1, tmps=7)
  .php   L0097 0002 JMPZ T1 0008
  .php   L0097 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0097 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0097 0005 DO_FCALL
  .php   L0097 0006 T4 = QM_ASSIGN V2
  .php   L0097 0007 JMP 0009
  .php   L0097 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0097 0009 ASSIGN CV0($tags) T4
  .php   L0098 0010 T6 = CAST (object) CV0($tags)
  .php   L0098 0011 INIT_METHOD_CALL 0 T6 string("count")
  .php   L0098 0012 V7 = DO_FCALL
  .php   L0098 0013 RETURN V7
  .php   L0099 0014 RETURN null
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   L0103 0000 CV0($collection) = RECV 1
  .php   L0103 0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   L0103 0002 JMPZ T1 0008
  .php   L0103 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0103 0004 SEND_VAR_EX CV0($collection) 1
  .php   L0103 0005 DO_FCALL
  .php   L0103 0006 T4 = QM_ASSIGN V2
  .php   L0103 0007 JMP 0009
  .php   L0103 0008 T4 = QM_ASSIGN CV0($collection)
  .php   L0103 0009 RETURN T4
  .php   L0103 0010 RETURN null
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   L0112 0000 CV0($collection) = RECV 1
  .php   L0112 0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   L0112 0002 JMPZ T1 0008
  .php   L0112 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0112 0004 SEND_VAR_EX CV0($collection) 1
  .php   L0112 0005 DO_FCALL
  .php   L0112 0006 T4 = QM_ASSIGN V2
  .php   L0112 0007 JMP 0009
  .php   L0112 0008 T4 = QM_ASSIGN CV0($collection)
  .php   L0112 0009 RETURN T4
  .php   L0112 0010 RETURN null
  .php        ; (lines=23, args=2, vars=2, tmps=10)
  .php   0003 JMPZ T3 0009
  .php   0004 V4 = NEW 1 string("Sharp\\Set")
  .php   0005 SEND_VAR_EX CV0($ids) 1
  .php   0006 DO_FCALL
  .php   0007 T6 = QM_ASSIGN V4
  .php   0008 JMP 0010
  .php   0009 T6 = QM_ASSIGN CV0($ids)
  .php   0010 ASSIGN_OBJ THIS string("ids")
  .php   0011 OP_DATA T6
  .php   0012 T8 = TYPE_CHECK (array) CV1($labels)
  .php   0013 JMPZ T8 0019
  .php   0014 V9 = NEW 1 string("Sharp\\Set")
  .php   0015 SEND_VAR_EX CV1($labels) 1
  .php   0017 T11 = QM_ASSIGN V9
  .php   0018 JMP 0020
  .php   0019 T11 = QM_ASSIGN CV1($labels)
  .php   0020 ASSIGN_OBJ THIS string("labels")
  .php   0021 OP_DATA T11
  .php   0022 RETURN null
  .php        4: 0005 - 0007 (new)
  .php        9: 0015 - 0017 (new)
  .php        ; (lines=65, args=1, vars=7, tmps=31)
  .php   0002 JMPZ T7 0008
  .php   0003 V8 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T10 = QM_ASSIGN V8
  .php   0007 JMP 0009
  .php   0008 T10 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T10
  .php   0010 T12 = CAST (object) CV0($tags)
  .php   0011 INIT_METHOD_CALL 1 T12 string("add")
  .php   0012 SEND_VAL_EX string("vip") 1
  .php   0014 ASSIGN CV1($added) V13
  .php   0015 T15 = CAST (object) CV0($tags)
  .php   0017 SEND_VAL_EX string("vip") 1
  .php   0019 ASSIGN CV2($again) V16
  .php   0020 T18 = CAST (object) CV0($tags)
  .php   0021 INIT_METHOD_CALL 1 T18 string("remove")
  .php   0022 SEND_VAL_EX string("new") 1
  .php   0024 ASSIGN CV3($removed) V19
  .php   0025 T21 = CAST (object) CV0($tags)
  .php   0026 INIT_METHOD_CALL 1 T21 string("remove")
  .php   0027 SEND_VAL_EX string("none") 1
  .php   0029 ASSIGN CV4($absent) V22
  .php   0030 ASSIGN CV5($joined) string("")
  .php   0031 V25 = FE_RESET_R CV0($tags) 0037
  .php   0032 FE_FETCH_R V25 CV6($tag) 0037
  .php   0033 T26 = CONCAT CV5($joined) CV6($tag)
  .php   0034 T27 = CONCAT T26 string(";")
  .php   0035 ASSIGN CV5($joined) T27
  .php   0036 JMP 0032
  .php   0037 FE_FREE V25
  .php   0038 T29 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .php   0039 T29 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   0040 T29 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   0041 T29 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .php   0042 T30 = CAST (object) CV0($tags)
  .php   0044 SEND_VAL_EX string("5") 1
  .php   0047 T32 = CAST (object) CV0($tags)
  .php   0048 INIT_METHOD_CALL 1 T32 string("contains")
  .php   0049 SEND_VAL_EX string("new") 1
  .php   0050 V33 = DO_FCALL
  .php   0051 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   0052 T34 = CAST (object) CV0($tags)
  .php   0053 INIT_METHOD_CALL 0 T34 string("count")
  .php   0054 V35 = DO_FCALL
  .php   0055 T29 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   0056 T36 = CAST (object) CV0($tags)
  .php   0057 INIT_METHOD_CALL 0 T36 string("toList")
  .php   0058 V37 = DO_FCALL
  .php   0059 T29 = ADD_ARRAY_ELEMENT V37 NEXT
  .php   0060 T29 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .php   0061 VERIFY_RETURN_TYPE T29
  .php   0062 RETURN T29
  .php   0063 VERIFY_RETURN_TYPE
  .php   0064 RETURN null
  .php        8: 0004 - 0006 (new)
  .php        25: 0032 - 0037 (loop)
  .php        29: 0039 - 0062 (tmp/var)
  .php        ; (lines=55, args=1, vars=1, tmps=25)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 T6 = CAST (object) CV0($tags)
  .php   0011 INIT_METHOD_CALL 1 T6 string("filter")
  .php   0012 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   0013 SEND_VAL_EX T7 1
  .php   0014 V8 = DO_FCALL
  .php   0015 T9 = CAST (object) V8
  .php   0016 INIT_METHOD_CALL 0 T9 string("toList")
  .php   0017 V10 = DO_FCALL
  .php   0018 T11 = INIT_ARRAY 6 (packed) V10 NEXT
  .php   0019 T12 = CAST (object) CV0($tags)
  .php   0020 INIT_METHOD_CALL 1 T12 string("map")
  .php   0021 T13 = DECLARE_LAMBDA_FUNCTION 1
  .php   0022 SEND_VAL_EX T13 1
  .php   0023 V14 = DO_FCALL
  .php   0024 T11 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   0025 T15 = CAST (object) CV0($tags)
  .php   0026 INIT_METHOD_CALL 1 T15 string("any")
  .php   0027 T16 = DECLARE_LAMBDA_FUNCTION 2
  .php   0028 SEND_VAL_EX T16 1
  .php   0029 V17 = DO_FCALL
  .php   0030 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .php   0031 T18 = CAST (object) CV0($tags)
  .php   0032 INIT_METHOD_CALL 1 T18 string("first")
  .php   0033 T19 = DECLARE_LAMBDA_FUNCTION 3
  .php   0034 SEND_VAL_EX T19 1
  .php   0035 V20 = DO_FCALL
  .php   0036 T11 = ADD_ARRAY_ELEMENT V20 NEXT
  .php   0037 T21 = CAST (object) CV0($tags)
  .php   0038 INIT_METHOD_CALL 1 T21 string("sortedBy")
  .php   0039 T22 = DECLARE_LAMBDA_FUNCTION 4
  .php   0040 SEND_VAL_EX T22 1
  .php   0041 V23 = DO_FCALL
  .php   0042 T11 = ADD_ARRAY_ELEMENT V23 NEXT
  .php   0043 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0044 SEND_VAL_EX string(",") 1
  .php   0045 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   0046 SEND_VAR CV0($tags) 1
  .php   0047 V24 = DO_ICALL
  .php   0048 SEND_VAR_NO_REF_EX V24 2
  .php   0049 V25 = DO_FCALL
  .php   0050 T11 = ADD_ARRAY_ELEMENT V25 NEXT
  .php   0051 VERIFY_RETURN_TYPE T11
  .php   0052 RETURN T11
  .php   0053 VERIFY_RETURN_TYPE
  .php   0054 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        11: 0019 - 0052 (tmp/var)
  .php        ; (lines=19, args=1, vars=1, tmps=8)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 T6 = CAST (object) CV0($tags)
  .php   0011 INIT_METHOD_CALL 1 T6 string("first")
  .php   0012 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   0013 SEND_VAL_EX T7 1
  .php   0014 V8 = DO_FCALL
  .php   0015 VERIFY_RETURN_TYPE V8
  .php   0016 RETURN V8
  .php   0017 VERIFY_RETURN_TYPE
  .php        2: 0004 - 0006 (new)
  .php        8: 0015 - 0016 (tmp/var)
  .php        ; (lines=52, args=1, vars=2, tmps=24)
  .php   0002 JMPZ T2 0008
  .php   0003 V3 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($ids) 1
  .php   0005 DO_FCALL
  .php   0006 T5 = QM_ASSIGN V3
  .php   0007 JMP 0009
  .php   0008 T5 = QM_ASSIGN CV0($ids)
  .php   0009 ASSIGN CV0($ids) T5
  .php   0010 V7 = NEW 1 string("Sharp\\Set")
  .php   0011 SEND_VAL_EX array(...) 1
  .php   0013 ASSIGN CV1($more) V7
  .php   0014 T10 = CAST (object) CV0($ids)
  .php   0015 INIT_METHOD_CALL 1 T10 string("add")
  .php   0016 SEND_VAL_EX int(4) 1
  .php   0017 V11 = DO_FCALL
  .php   0018 T12 = INIT_ARRAY 7 (packed) V11 NEXT
  .php   0019 T13 = CAST (object) CV0($ids)
  .php   0020 INIT_METHOD_CALL 1 T13 string("add")
  .php   0021 SEND_VAL_EX int(4) 1
  .php   0022 V14 = DO_FCALL
  .php   0023 T12 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   0024 T15 = CAST (object) CV0($ids)
  .php   0025 INIT_METHOD_CALL 1 T15 string("remove")
  .php   0026 SEND_VAL_EX int(7) 1
  .php   0027 V16 = DO_FCALL
  .php   0028 T12 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   0029 T17 = CAST (object) CV0($ids)
  .php   0030 INIT_METHOD_CALL 1 T17 string("contains")
  .php   0031 SEND_VAL_EX int(2) 1
  .php   0032 V18 = DO_FCALL
  .php   0033 T12 = ADD_ARRAY_ELEMENT V18 NEXT
  .php   0034 T19 = CAST (object) CV0($ids)
  .php   0035 INIT_METHOD_CALL 0 T19 string("toList")
  .php   0036 V20 = DO_FCALL
  .php   0037 T12 = ADD_ARRAY_ELEMENT V20 NEXT
  .php   0038 T21 = CAST (object) CV1($more)
  .php   0039 INIT_METHOD_CALL 0 T21 string("toList")
  .php   0040 V22 = DO_FCALL
  .php   0041 T12 = ADD_ARRAY_ELEMENT V22 NEXT
  .php   0042 T23 = CAST (object) CV0($ids)
  .php   0043 INIT_METHOD_CALL 1 T23 string("sumOf")
  .php   0044 T24 = DECLARE_LAMBDA_FUNCTION 0
  .php   0045 SEND_VAL_EX T24 1
  .php   0046 V25 = DO_FCALL
  .php   0047 T12 = ADD_ARRAY_ELEMENT V25 NEXT
  .php   0048 VERIFY_RETURN_TYPE T12
  .php   0049 RETURN T12
  .php   0050 VERIFY_RETURN_TYPE
  .php   0051 RETURN null
  .php        3: 0004 - 0006 (new)
  .php        7: 0011 - 0013 (new)
  .php        12: 0019 - 0049 (tmp/var)
  .php        ; (lines=66, args=1, vars=6, tmps=36)
  .php   0002 JMPZ T6 0008
  .php   0003 V7 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($states) 1
  .php   0005 DO_FCALL
  .php   0006 T9 = QM_ASSIGN V7
  .php   0007 JMP 0009
  .php   0008 T9 = QM_ASSIGN CV0($states)
  .php   0009 ASSIGN CV0($states) T9
  .php   0010 V11 = NEW 1 string("Sharp\\Set")
  .php   0011 T12 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .php   0012 T13 = INIT_ARRAY 3 (packed) T12 NEXT
  .php   0013 T14 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   0014 T13 = ADD_ARRAY_ELEMENT T14 NEXT
  .php   0015 T15 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Large")
  .php   0016 T13 = ADD_ARRAY_ELEMENT T15 NEXT
  .php   0017 SEND_VAL_EX T13 1
  .php   0019 ASSIGN CV1($sizes) V11
  .php   0020 T18 = CAST (object) CV0($states)
  .php   0021 INIT_METHOD_CALL 1 T18 string("add")
  .php   0022 T19 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0023 SEND_VAL_EX T19 1
  .php   0025 ASSIGN CV2($closed) V20
  .php   0026 T22 = CAST (object) CV0($states)
  .php   0027 INIT_METHOD_CALL 1 T22 string("add")
  .php   0028 T23 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0029 SEND_VAL_EX T23 1
  .php   0031 ASSIGN CV3($active) V24
  .php   0032 T26 = CAST (object) CV0($states)
  .php   0033 INIT_METHOD_CALL 1 T26 string("contains")
  .php   0034 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0035 SEND_VAL_EX T27 1
  .php   0036 V28 = DO_FCALL
  .php   0037 ASSIGN CV4($has) V28
  .php   0038 T30 = CAST (object) CV0($states)
  .php   0039 INIT_METHOD_CALL 1 T30 string("remove")
  .php   0040 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0041 SEND_VAL_EX T31 1
  .php   0042 V32 = DO_FCALL
  .php   0043 ASSIGN CV5($removed) V32
  .php   0044 T34 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .php   0045 T34 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .php   0046 T34 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .php   0047 T34 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .php   0048 T35 = CAST (object) CV0($states)
  .php   0049 INIT_METHOD_CALL 0 T35 string("toList")
  .php   0050 V36 = DO_FCALL
  .php   0051 T34 = ADD_ARRAY_ELEMENT V36 NEXT
  .php   0052 T37 = CAST (object) CV1($sizes)
  .php   0053 INIT_METHOD_CALL 0 T37 string("toList")
  .php   0054 V38 = DO_FCALL
  .php   0055 T34 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   0056 T39 = CAST (object) CV1($sizes)
  .php   0057 INIT_METHOD_CALL 1 T39 string("contains")
  .php   0058 T40 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   0059 SEND_VAL_EX T40 1
  .php   0060 V41 = DO_FCALL
  .php   0061 T34 = ADD_ARRAY_ELEMENT V41 NEXT
  .php   0062 VERIFY_RETURN_TYPE T34
  .php   0063 RETURN T34
  .php   0064 VERIFY_RETURN_TYPE
  .php   0065 RETURN null
  .php        7: 0004 - 0006 (new)
  .php        11: 0011 - 0019 (new)
  .php        13: 0013 - 0017 (tmp/var)
  .php        34: 0045 - 0063 (tmp/var)
  .php        ; (lines=68, args=1, vars=6, tmps=32)
  .php   0002 JMPZ T6 0008
  .php   0003 V7 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($maybe) 1
  .php   0005 DO_FCALL
  .php   0006 T9 = QM_ASSIGN V7
  .php   0007 JMP 0009
  .php   0008 T9 = QM_ASSIGN CV0($maybe)
  .php   0009 ASSIGN CV0($maybe) T9
  .php   0010 INIT_METHOD_CALL 0 THIS string("copy")
  .php   0011 V11 = DO_FCALL
  .php   0012 ASSIGN CV1($copy) V11
  .php   0013 T13 = CAST (object) CV1($copy)
  .php   0014 INIT_METHOD_CALL 1 T13 string("add")
  .php   0015 SEND_VAL_EX string("copy") 1
  .php   0016 V14 = DO_FCALL
  .php   0017 ASSIGN CV2($copied) V14
  .php   0018 T16 = FETCH_OBJ_R THIS string("tags")
  .php   0019 INIT_METHOD_CALL 1 T16 string("contains")
  .php   0020 SEND_VAL_EX string("copy") 1
  .php   0021 V17 = DO_FCALL
  .php   0022 ASSIGN CV3($kept) V17
  .php   0023 T19 = FETCH_OBJ_R THIS string("tags")
  .php   0024 INIT_METHOD_CALL 1 T19 string("add")
  .php   0025 INIT_METHOD_CALL 0 THIS string("next")
  .php   0026 V20 = DO_FCALL
  .php   0027 SEND_VAR_NO_REF_EX V20 1
  .php   0028 V21 = DO_FCALL
  .php   0029 ASSIGN CV4($added) V21
  .php   0030 T23 = FETCH_OBJ_R THIS string("tags")
  .php   0031 INIT_METHOD_CALL 0 T23 string("toList")
  .php   0032 V24 = DO_FCALL
  .php   0033 ASSIGN CV5($named) V24
  .php   0034 T26 = FETCH_OBJ_R THIS string("tags")
  .php   0035 INIT_METHOD_CALL 0 T26 string("clear")
  .php   0036 DO_FCALL
  .php   0037 T28 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .php   0038 T28 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .php   0039 T28 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .php   0040 T29 = FETCH_OBJ_R THIS string("calls")
  .php   0041 T28 = ADD_ARRAY_ELEMENT T29 NEXT
  .php   0042 T28 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .php   0043 T30 = FETCH_OBJ_R THIS string("tags")
  .php   0044 INIT_METHOD_CALL 0 T30 string("toList")
  .php   0046 T28 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   0047 T32 = CAST (object) CV0($maybe)
  .php   0048 V33 = JMP_NULL T32 0052
  .php   0049 INIT_METHOD_CALL 1 T32 string("add")
  .php   0050 SEND_VAL_EX string("x") 1
  .php   0051 V33 = DO_FCALL
  .php   0052 T28 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   0053 T34 = CAST (object) CV0($maybe)
  .php   0054 V35 = JMP_NULL T34 0058
  .php   0055 INIT_METHOD_CALL 1 T34 string("contains")
  .php   0056 SEND_VAL_EX string("x") 1
  .php   0057 V35 = DO_FCALL
  .php   0058 T28 = ADD_ARRAY_ELEMENT V35 NEXT
  .php   0059 T36 = CAST (object) CV0($maybe)
  .php   0060 V37 = JMP_NULL T36 0063
  .php   0061 INIT_METHOD_CALL 0 T36 string("count")
  .php   0062 V37 = DO_FCALL
  .php   0063 T28 = ADD_ARRAY_ELEMENT V37 NEXT
  .php   0064 VERIFY_RETURN_TYPE T28
  .php   0065 RETURN T28
  .php   0066 VERIFY_RETURN_TYPE
  .php   0067 RETURN null
  .php        7: 0004 - 0006 (new)
  .php        28: 0038 - 0065 (tmp/var)
  .php        32: 0048 - 0049 (tmp/var)
  .php        34: 0054 - 0055 (tmp/var)
  .php        36: 0060 - 0061 (tmp/var)
  .php        ; (lines=33, args=1, vars=4, tmps=16)
  .php   0008 V9 = NEW 1 string("Sharp\\Set")
  .php   0009 SEND_VAR_EX CV0($names) 1
  .php   0010 DO_FCALL
  .php   0011 SEND_VAR_NO_REF_EX V9 1
  .php   0012 V11 = DO_FCALL
  .php   0013 T12 = INIT_ARRAY 3 (packed) V11 NEXT
  .php   0014 INIT_DYNAMIC_CALL 1 CV2($check)
  .php   0015 V13 = NEW 1 string("Sharp\\Set")
  .php   0016 T14 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .php   0017 SEND_VAL_EX T14 1
  .php   0019 SEND_VAR_NO_REF_EX V13 1
  .php   0020 V16 = DO_FCALL
  .php   0021 T12 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   0022 INIT_DYNAMIC_CALL 1 CV3($size)
  .php   0023 V17 = NEW 1 string("Sharp\\Set")
  .php   0024 SEND_VAL_EX array(...) 1
  .php   0025 DO_FCALL
  .php   0026 SEND_VAR_NO_REF_EX V17 1
  .php   0027 V19 = DO_FCALL
  .php   0028 T12 = ADD_ARRAY_ELEMENT V19 NEXT
  .php   0029 VERIFY_RETURN_TYPE T12
  .php   0030 RETURN T12
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
  .php        9: 0009 - 0011 (new)
  .php        12: 0014 - 0030 (tmp/var)
  .php        13: 0016 - 0019 (new)
  .php        17: 0024 - 0026 (new)
  .php        ; (lines=17, args=1, vars=2, tmps=7)
  .php   0003 JMPZ T2 0009
  .php   0004 V3 = NEW 1 string("Sharp\\Set")
  .php   0005 SEND_VAR_EX CV0($tags) 1
  .php   0006 DO_FCALL
  .php   0007 T5 = QM_ASSIGN V3
  .php   0008 JMP 0010
  .php   0009 T5 = QM_ASSIGN CV0($tags)
  .php   0010 ASSIGN CV0($tags) T5
  .php   0011 T7 = CAST (object) CV0($tags)
  .php   0012 INIT_METHOD_CALL 1 T7 string("contains")
  .php   0013 SEND_VAR_EX CV1($prefix) 1
  .php   0014 V8 = DO_FCALL
  .php   0015 RETURN V8
  .php        3: 0005 - 0007 (new)
  .php        ; (lines=15, args=1, vars=1, tmps=7)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 T6 = CAST (object) CV0($tags)
  .php   0011 INIT_METHOD_CALL 0 T6 string("count")
  .php   0012 V7 = DO_FCALL
  .php   0013 RETURN V7
  .php   0014 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   0000 CV0($collection) = RECV 1
  .php   0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($collection) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($collection)
  .php   0009 RETURN T4
  .php   0010 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   0000 CV0($collection) = RECV 1
  .php   0001 T1 = TYPE_CHECK (array) CV0($collection)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($collection) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($collection)
  .php   0009 RETURN T4
  .php   0010 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        ; (lines=15, args=1, vars=1, tmps=7)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($value) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($value)
  .php   0009 ASSIGN CV0($value) T4
  .php   0010 ASSIGN_OBJ_OP (ADD) THIS string("writes")
  .php   0011 OP_DATA int(1)
  .php   0012 ASSIGN_OBJ THIS string("states")
  .php   0013 OP_DATA CV0($value)
  .php   0014 RETURN null
  .php        2: 0004 - 0006 (new)
  .php       Property [ public Sharp\Set $tags ]
  .php       Property [ public Sharp\Set $states { get; set; } ]
  .php       Property [ private Sharp\Set $sizes ]
Store/Order: same opcodes and lines in 3 op arrays, same signatures in 1 classes
RushOrders/Order: same opcodes and lines in 2 op arrays, same signatures in 1 classes
RushOrders/RushOrder: same opcodes and lines in 1 op arrays, same signatures in 1 classes
TypedOrders/Order: same opcodes and lines in 2 op arrays, same signatures in 1 classes
TypedOrders/RushOrder: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Permalink: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Library: different opcodes and lines in 18 op arrays, same signatures in 1 classes
  check  Demo\Library::query string("Map<string, Any?>")
  .sharp      ; (lines=33, args=1, vars=1, tmps=12)
  .sharp L0046 0002 JMPZ T1 0024
  .sharp L0046 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0046 0004 T3 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp L0046 0005 V4 = NEW 1 V2 T3
  .sharp L0046 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp L0046 0007 SEND_VAL array(...) 1
  .sharp L0046 0008 INIT_FCALL 3 128 string("array_map")
  .sharp L0046 0009 T5 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0046 0010 SEND_VAL T5 1
  .sharp L0046 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp L0046 0012 SEND_VAR CV0($values) 1
  .sharp L0046 0013 V6 = DO_ICALL
  .sharp L0046 0014 SEND_VAR V6 2
  .sharp L0046 0015 SEND_VAR CV0($values) 3
  .sharp L0046 0016 V7 = DO_ICALL
  .sharp L0046 0017 SEND_UNPACK V7
  .sharp L0046 0018 CHECK_UNDEF_ARGS
  .sharp L0046 0019 V8 = DO_ICALL
  .sharp L0046 0020 SEND_VAR_NO_REF_EX V8 1
  .sharp L0046 0021 DO_FCALL
  .sharp L0046 0022 T10 = QM_ASSIGN V4
  .sharp L0046 0023 JMP 0025
  .sharp L0046 0024 T10 = QM_ASSIGN CV0($values)
  .sharp L0046 0025 ASSIGN CV0($values) T10
  .sharp L0046 0026 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Net\\Url") string("buildQuery")
  .sharp L0046 0027 SEND_VAR_EX CV0($values) 1
  .sharp L0046 0028 V12 = DO_FCALL
  .sharp L0046 0029 VERIFY_RETURN_TYPE V12
  .sharp L0046 0030 RETURN V12
  .sharp L0046 0031 VERIFY_RETURN_TYPE
  .sharp L0046 0032 RETURN null
  .sharp L0046 0000 CV0($key#1) = RECV 1
  .sharp L0046 0001 CV1($value#2) = RECV 2
  .sharp L0046 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0046 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=33, args=1, vars=1, tmps=12)
  .sharp 0002 JMPZ T1 0024
  .sharp 0003 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0004 T3 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp 0005 V4 = NEW 1 V2 T3
  .sharp 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp 0007 SEND_VAL array(...) 1
  .sharp 0008 INIT_FCALL 3 128 string("array_map")
  .sharp 0009 T5 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0010 SEND_VAL T5 1
  .sharp 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp 0012 SEND_VAR CV0($values) 1
  .sharp 0013 V6 = DO_ICALL
  .sharp 0014 SEND_VAR V6 2
  .sharp 0015 SEND_VAR CV0($values) 3
  .sharp 0016 V7 = DO_ICALL
  .sharp 0017 SEND_UNPACK V7
  .sharp 0018 CHECK_UNDEF_ARGS
  .sharp 0019 V8 = DO_ICALL
  .sharp 0020 SEND_VAR_NO_REF_EX V8 1
  .sharp 0021 DO_FCALL
  .sharp 0022 T10 = QM_ASSIGN V4
  .sharp 0023 JMP 0025
  .sharp 0024 T10 = QM_ASSIGN CV0($values)
  .sharp 0025 ASSIGN CV0($values) T10
  .sharp 0026 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Net\\Url") string("buildQuery")
  .sharp 0027 SEND_VAR_EX CV0($values) 1
  .sharp 0028 V12 = DO_FCALL
  .sharp 0029 VERIFY_RETURN_TYPE V12
  .sharp 0030 RETURN V12
  .sharp 0031 VERIFY_RETURN_TYPE
  .sharp 0032 RETURN null
  .sharp      4: 0006 - 0022 (new)
  .sharp      12: 0029 - 0030 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=31, args=1, vars=1, tmps=10)
  .php   L0046 0002 JMPZ T1 0022
  .php   L0046 0003 V2 = NEW 1 string("Sharp\\Map")
  .php   L0046 0004 INIT_FCALL 1 96 string("array_merge")
  .php   L0046 0005 SEND_VAL array(...) 1
  .php   L0046 0006 INIT_FCALL 3 128 string("array_map")
  .php   L0046 0007 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0046 0008 SEND_VAL T3 1
  .php   L0046 0009 INIT_FCALL 1 96 string("array_keys")
  .php   L0046 0010 SEND_VAR CV0($values) 1
  .php   L0046 0011 V4 = DO_ICALL
  .php   L0046 0012 SEND_VAR V4 2
  .php   L0046 0013 SEND_VAR CV0($values) 3
  .php   L0046 0014 V5 = DO_ICALL
  .php   L0046 0015 SEND_UNPACK V5
  .php   L0046 0016 CHECK_UNDEF_ARGS
  .php   L0046 0017 V6 = DO_ICALL
  .php   L0046 0018 SEND_VAR_NO_REF_EX V6 1
  .php   L0046 0019 DO_FCALL
  .php   L0046 0020 T8 = QM_ASSIGN V2
  .php   L0046 0021 JMP 0023
  .php   L0046 0022 T8 = QM_ASSIGN CV0($values)
  .php   L0046 0023 ASSIGN CV0($values) T8
  .php   L0046 0024 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Net\\Url") string("buildQuery")
  .php   L0046 0025 SEND_VAR_EX CV0($values) 1
  .php   L0046 0026 V10 = DO_FCALL
  .php   L0046 0027 VERIFY_RETURN_TYPE V10
  .php   L0046 0028 RETURN V10
  .php   L0046 0029 VERIFY_RETURN_TYPE
  .php   L0046 0030 RETURN null
  .php   L0046 0000 CV0($key) = RECV 1
  .php   L0046 0001 CV1($value) = RECV 2
  .php   L0046 0002 T2 = CAST (string) CV0($key)
  .php   L0046 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=31, args=1, vars=1, tmps=10)
  .php   0002 JMPZ T1 0022
  .php   0003 V2 = NEW 1 string("Sharp\\Map")
  .php   0004 INIT_FCALL 1 96 string("array_merge")
  .php   0005 SEND_VAL array(...) 1
  .php   0006 INIT_FCALL 3 128 string("array_map")
  .php   0007 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   0008 SEND_VAL T3 1
  .php   0009 INIT_FCALL 1 96 string("array_keys")
  .php   0010 SEND_VAR CV0($values) 1
  .php   0011 V4 = DO_ICALL
  .php   0012 SEND_VAR V4 2
  .php   0013 SEND_VAR CV0($values) 3
  .php   0014 V5 = DO_ICALL
  .php   0015 SEND_UNPACK V5
  .php   0016 CHECK_UNDEF_ARGS
  .php   0017 V6 = DO_ICALL
  .php   0018 SEND_VAR_NO_REF_EX V6 1
  .php   0019 DO_FCALL
  .php   0020 T8 = QM_ASSIGN V2
  .php   0021 JMP 0023
  .php   0022 T8 = QM_ASSIGN CV0($values)
  .php   0023 ASSIGN CV0($values) T8
  .php   0024 INIT_STATIC_METHOD_CALL 1 string("Sharp\\Net\\Url") string("buildQuery")
  .php   0025 SEND_VAR_EX CV0($values) 1
  .php   0026 V10 = DO_FCALL
  .php   0027 VERIFY_RETURN_TYPE V10
  .php   0028 RETURN V10
  .php   0029 VERIFY_RETURN_TYPE
  .php   0030 RETURN null
  .php        2: 0004 - 0020 (new)
  .php        10: 0027 - 0028 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Inbox: different opcodes and lines in 9 op arrays, same signatures in 1 classes
  check  Demo\Inbox::keep string("Any")
  .sharp     Property [ private Sharp\Map $saved = Closure({closure:<file>:6})() ]
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
Accessors/Address: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Accessors/Product: same opcodes and lines in 11 op arrays, same signatures in 1 classes
Parcel: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Exits: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Positions: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Deploy: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Tags: same opcodes and lines in 2 op arrays, same signatures in 1 classes
  check  Demo\Tags::of string("List<string>|string")
PhpForms: same opcodes and lines in 15 op arrays, same signatures in 1 classes
  check  Demo\PhpForms::tags string("List<string>|string")
Generics/Catalog: different opcodes and lines in 14 op arrays, same signatures in 1 classes
  check  Paging\Catalog::newest string("Paging.Feed<Paging.Order>")
  check  Paging\Catalog::check string("Paging.Validator<Paging.DatabaseEntity>, Any?")
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp L0008 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0008 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0008 0002 V2 = NEW 1 V0 T1
  .sharp L0008 0003 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp L0008 0004 V3 = DO_FCALL
  .sharp L0008 0005 SEND_VAR_NO_REF_EX V3 1
  .sharp L0008 0006 DO_FCALL
  .sharp L0008 0007 T5 = CAST (object) V2
  .sharp L0008 0008 INIT_METHOD_CALL 0 T5 string("first")
  .sharp L0008 0009 V6 = DO_FCALL
  .sharp L0008 0010 T7 = FETCH_OBJ_R V6 string("id")
  .sharp L0008 0011 VERIFY_RETURN_TYPE T7
  .sharp L0008 0012 RETURN T7
  .sharp L0008 0013 VERIFY_RETURN_TYPE
  .sharp L0008 0014 RETURN null
  .sharp      ; (lines=21, args=0, vars=0, tmps=12)
  .sharp L0010 0010 T6 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0010 0011 V7 = DO_FCALL T6
  .sharp L0010 0012 T8 = SHARP_VERIFY_TYPE_ARGS V7 string("Paging.PaginatedList<Paging.Order>")
  .sharp L0010 0013 T9 = CAST (object) T8
  .sharp L0010 0014 INIT_METHOD_CALL 0 T9 string("first")
  .sharp L0010 0015 V10 = DO_FCALL
  .sharp L0010 0016 T11 = FETCH_OBJ_R V10 string("id")
  .sharp L0010 0017 VERIFY_RETURN_TYPE T11
  .sharp L0010 0018 RETURN T11
  .sharp L0010 0019 VERIFY_RETURN_TYPE
  .sharp L0010 0020 RETURN null
  .sharp      ; (lines=16, args=0, vars=0, tmps=6)
  .sharp L0012 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0012 0006 V2 = DO_FCALL T1
  .sharp L0012 0007 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("Paging.Order?")
  .sharp L0012 0008 T4 = JMP_NULL T3 0010
  .sharp L0012 0009 T4 = FETCH_OBJ_IS T3 string("id")
  .sharp L0012 0010 T5 = COALESCE T4 0012
  .sharp L0012 0011 T5 = QM_ASSIGN int(0)
  .sharp L0012 0012 VERIFY_RETURN_TYPE T5
  .sharp L0012 0013 RETURN T5
  .sharp L0012 0014 VERIFY_RETURN_TYPE
  .sharp L0012 0015 RETURN null
  .sharp      ; (lines=16, args=0, vars=0, tmps=6)
  .sharp L0014 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0014 0006 V2 = DO_FCALL T1
  .sharp L0014 0007 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("Paging.Order?")
  .sharp L0014 0008 T4 = JMP_NULL T3 0010
  .sharp L0014 0009 T4 = FETCH_OBJ_IS T3 string("id")
  .sharp L0014 0010 T5 = COALESCE T4 0012
  .sharp L0014 0011 T5 = QM_ASSIGN int(0)
  .sharp L0014 0012 VERIFY_RETURN_TYPE T5
  .sharp L0014 0013 RETURN T5
  .sharp L0014 0014 VERIFY_RETURN_TYPE
  .sharp L0014 0015 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=6)
  .sharp L0020 0001 V0 = FETCH_CLASS (exception) string("Paging\\Feed")
  .sharp L0020 0002 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0020 0003 V2 = NEW 1 V0 T1
  .sharp L0020 0004 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp L0020 0005 V3 = DO_FCALL
  .sharp L0020 0006 SEND_VAR_NO_REF_EX V3 1
  .sharp L0020 0007 DO_FCALL
  .sharp L0020 0008 SEND_VAR V2 1
  .sharp L0020 0009 V5 = DO_FCALL
  .sharp L0020 0010 VERIFY_RETURN_TYPE V5
  .sharp L0020 0011 RETURN V5
  .sharp L0020 0012 VERIFY_RETURN_TYPE
  .sharp L0020 0013 RETURN null
  .sharp      ; (lines=11, args=2, vars=2, tmps=3)
  .sharp L0022 0006 T4 = SHARP_VERIFY_TYPE_ARGS V3 string("bool")
  .sharp L0022 0007 VERIFY_RETURN_TYPE T4
  .sharp L0022 0008 RETURN T4
  .sharp L0022 0009 VERIFY_RETURN_TYPE
  .sharp L0022 0010 RETURN null
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp L0026 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0026 0008 V5 = DO_FCALL T4
  .sharp L0026 0009 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("Paging.Order")
  .sharp L0026 0010 T7 = FETCH_OBJ_R T6 string("id")
  .sharp L0026 0011 VERIFY_RETURN_TYPE T7
  .sharp L0026 0012 RETURN T7
  .sharp L0026 0013 VERIFY_RETURN_TYPE
  .sharp L0026 0014 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp L0028 0008 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0028 0009 V6 = DO_FCALL T5
  .sharp L0028 0010 VERIFY_RETURN_TYPE V6
  .sharp L0028 0011 RETURN V6
  .sharp L0028 0012 VERIFY_RETURN_TYPE
  .sharp L0028 0013 RETURN null
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0002 V2 = NEW 1 V0 T1
  .sharp 0003 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp 0004 V3 = DO_UCALL
  .sharp 0005 SEND_VAR_NO_REF_EX V3 1
  .sharp 0007 T5 = CAST (object) V2
  .sharp 0008 INIT_METHOD_CALL 0 T5 string("first")
  .sharp 0009 V6 = DO_FCALL
  .sharp 0010 T7 = FETCH_OBJ_R V6 string("id")
  .sharp 0011 VERIFY_RETURN_TYPE T7
  .sharp 0012 RETURN T7
  .sharp 0013 VERIFY_RETURN_TYPE
  .sharp 0014 RETURN null
  .sharp      2: 0003 - 0007 (new)
  .sharp      7: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=21, args=0, vars=0, tmps=12)
  .sharp 0010 T6 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0011 V7 = DO_FCALL T6
  .sharp 0012 T8 = SHARP_VERIFY_TYPE_ARGS V7 string("Paging.PaginatedList<Paging.Order>")
  .sharp 0013 T9 = CAST (object) T8
  .sharp 0014 INIT_METHOD_CALL 0 T9 string("first")
  .sharp 0015 V10 = DO_FCALL
  .sharp 0016 T11 = FETCH_OBJ_R V10 string("id")
  .sharp 0017 VERIFY_RETURN_TYPE T11
  .sharp 0018 RETURN T11
  .sharp 0019 VERIFY_RETURN_TYPE
  .sharp 0020 RETURN null
  .sharp      11: 0017 - 0018 (tmp/var)
  .sharp      ; (lines=16, args=0, vars=0, tmps=6)
  .sharp 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0006 V2 = DO_FCALL T1
  .sharp 0007 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("Paging.Order?")
  .sharp 0008 T4 = JMP_NULL T3 0010
  .sharp 0009 T4 = FETCH_OBJ_IS T3 string("id")
  .sharp 0010 T5 = COALESCE T4 0012
  .sharp 0011 T5 = QM_ASSIGN int(0)
  .sharp 0012 VERIFY_RETURN_TYPE T5
  .sharp 0013 RETURN T5
  .sharp 0014 VERIFY_RETURN_TYPE
  .sharp 0015 RETURN null
  .sharp      5: 0012 - 0013 (tmp/var)
  .sharp      ; (lines=16, args=0, vars=0, tmps=6)
  .sharp 0005 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0006 V2 = DO_FCALL T1
  .sharp 0007 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("Paging.Order?")
  .sharp 0008 T4 = JMP_NULL T3 0010
  .sharp 0009 T4 = FETCH_OBJ_IS T3 string("id")
  .sharp 0010 T5 = COALESCE T4 0012
  .sharp 0011 T5 = QM_ASSIGN int(0)
  .sharp 0012 VERIFY_RETURN_TYPE T5
  .sharp 0013 RETURN T5
  .sharp 0014 VERIFY_RETURN_TYPE
  .sharp 0015 RETURN null
  .sharp      5: 0012 - 0013 (tmp/var)
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
  .sharp      ; (lines=11, args=2, vars=2, tmps=3)
  .sharp 0006 T4 = SHARP_VERIFY_TYPE_ARGS V3 string("bool")
  .sharp 0007 VERIFY_RETURN_TYPE T4
  .sharp 0008 RETURN T4
  .sharp 0009 VERIFY_RETURN_TYPE
  .sharp 0010 RETURN null
  .sharp      4: 0007 - 0008 (tmp/var)
  .sharp      ; (lines=15, args=0, vars=0, tmps=8)
  .sharp 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0008 V5 = DO_FCALL T4
  .sharp 0009 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("Paging.Order")
  .sharp 0010 T7 = FETCH_OBJ_R T6 string("id")
  .sharp 0011 VERIFY_RETURN_TYPE T7
  .sharp 0012 RETURN T7
  .sharp 0013 VERIFY_RETURN_TYPE
  .sharp 0014 RETURN null
  .sharp      7: 0011 - 0012 (tmp/var)
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp 0008 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0009 V6 = DO_FCALL T5
  .sharp 0010 VERIFY_RETURN_TYPE V6
  .sharp 0011 RETURN V6
  .sharp      6: 0010 - 0011 (tmp/var)
  .php   L0008 0000 V0 = NEW 1 string("Paging\\PaginatedList")
  .php   L0008 0001 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   L0008 0002 V1 = DO_FCALL
  .php   L0008 0003 SEND_VAR_NO_REF_EX V1 1
  .php   L0008 0004 DO_FCALL
  .php   L0008 0005 T3 = CAST (object) V0
  .php   L0008 0006 INIT_METHOD_CALL 0 T3 string("first")
  .php   L0008 0007 V4 = DO_FCALL
  .php   L0008 0008 T5 = FETCH_OBJ_R V4 string("id")
  .php   L0008 0009 VERIFY_RETURN_TYPE T5
  .php   L0008 0010 RETURN T5
  .php   L0008 0011 VERIFY_RETURN_TYPE
  .php   L0008 0012 RETURN null
  .php        ; (lines=19, args=0, vars=0, tmps=10)
  .php   L0010 0010 V6 = DO_FCALL
  .php   L0010 0011 T7 = CAST (object) V6
  .php   L0010 0012 INIT_METHOD_CALL 0 T7 string("first")
  .php   L0010 0013 V8 = DO_FCALL
  .php   L0010 0014 T9 = FETCH_OBJ_R V8 string("id")
  .php   L0010 0015 VERIFY_RETURN_TYPE T9
  .php   L0010 0016 RETURN T9
  .php   L0010 0017 VERIFY_RETURN_TYPE
  .php   L0010 0018 RETURN null
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   L0012 0005 V1 = DO_FCALL
  .php   L0012 0006 T2 = JMP_NULL V1 0008
  .php   L0012 0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   L0012 0008 T3 = COALESCE T2 0010
  .php   L0012 0009 T3 = QM_ASSIGN int(0)
  .php   L0012 0010 VERIFY_RETURN_TYPE T3
  .php   L0012 0011 RETURN T3
  .php   L0012 0012 VERIFY_RETURN_TYPE
  .php   L0012 0013 RETURN null
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   L0014 0005 V1 = DO_FCALL
  .php   L0014 0006 T2 = JMP_NULL V1 0008
  .php   L0014 0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   L0014 0008 T3 = COALESCE T2 0010
  .php   L0014 0009 T3 = QM_ASSIGN int(0)
  .php   L0014 0010 VERIFY_RETURN_TYPE T3
  .php   L0014 0011 RETURN T3
  .php   L0014 0012 VERIFY_RETURN_TYPE
  .php   L0014 0013 RETURN null
  .php        ; (lines=12, args=0, vars=0, tmps=4)
  .php   L0020 0001 V0 = NEW 1 string("Paging\\Feed")
  .php   L0020 0002 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   L0020 0003 V1 = DO_FCALL
  .php   L0020 0004 SEND_VAR_NO_REF_EX V1 1
  .php   L0020 0005 DO_FCALL
  .php   L0020 0006 SEND_VAR V0 1
  .php   L0020 0007 V3 = DO_FCALL
  .php   L0020 0008 VERIFY_RETURN_TYPE V3
  .php   L0020 0009 RETURN V3
  .php   L0020 0010 VERIFY_RETURN_TYPE
  .php   L0020 0011 RETURN null
  .php        ; (lines=10, args=2, vars=2, tmps=2)
  .php   L0022 0006 VERIFY_RETURN_TYPE V3
  .php   L0022 0007 RETURN V3
  .php   L0022 0008 VERIFY_RETURN_TYPE
  .php   L0022 0009 RETURN null
  .php   L0026 0007 V4 = DO_FCALL
  .php   L0026 0008 T5 = FETCH_OBJ_R V4 string("id")
  .php   L0026 0009 VERIFY_RETURN_TYPE T5
  .php   L0026 0010 RETURN T5
  .php   L0026 0011 VERIFY_RETURN_TYPE
  .php   L0026 0012 RETURN null
  .php   L0028 0008 V5 = DO_FCALL
  .php   L0028 0009 VERIFY_RETURN_TYPE V5
  .php   L0028 0010 RETURN V5
  .php   L0028 0011 VERIFY_RETURN_TYPE
  .php   L0028 0012 RETURN null
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
  .php   0005 V1 = DO_FCALL
  .php   0006 T2 = JMP_NULL V1 0008
  .php   0007 T2 = FETCH_OBJ_IS V1 string("id")
  .php   0008 T3 = COALESCE T2 0010
  .php   0009 T3 = QM_ASSIGN int(0)
  .php   0010 VERIFY_RETURN_TYPE T3
  .php   0011 RETURN T3
  .php        1: 0006 - 0007 (tmp/var)
  .php        3: 0010 - 0011 (tmp/var)
  .php        ; (lines=14, args=0, vars=0, tmps=4)
  .php   0005 V1 = DO_FCALL
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
  .php        ; (lines=10, args=2, vars=2, tmps=2)
  .php   0006 VERIFY_RETURN_TYPE V3
  .php   0007 RETURN V3
  .php   0008 VERIFY_RETURN_TYPE
  .php   0009 RETURN null
  .php        3: 0006 - 0007 (tmp/var)
  .php   0008 V5 = DO_FCALL
  .php   0009 VERIFY_RETURN_TYPE V5
  .php   0010 RETURN V5
  .php       /** @return list<Order> */
  .php       /** @param Feed<Order> $feed */
  .php       /** @param Validator<DatabaseEntity> $validator */
Generics/DatabaseEntity: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Generics/EntityValidator: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Generics/Feed: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  check  Paging\Feed::__construct string("List<$0>")
  .php       /** @param list<TItem> $items */
  .php       /** @return TItem */
Generics/Order: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Generics/OrderPage: different opcodes and lines in 1 op arrays, same signatures in 1 classes
  .php       /** @param list<TItem> $rows */
  .php       /** @return TItem */
Generics/OrderQuery: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  check  Paging\OrderQuery::__construct string("List<Paging.Order>")
  .php       /** @param list<Order> $orders */
Generics/PaginatedList: different opcodes and lines in 3 op arrays, same signatures in 1 classes
  check  Paging\PaginatedList::__construct string("List<$0>")
  .php       /** @param list<TItem> $rows */
  .php       /** @return TItem */
Generics/Query: same opcodes and lines in 2 op arrays, same signatures in 0 classes
Generics/Repository: different opcodes and lines in 2 op arrays, same signatures in 1 classes
  .sharp      ; (lines=15, args=1, vars=2, tmps=7)
  .sharp L0008 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0008 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0008 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp L0008 0004 V4 = NEW 1 V2 T3
  .sharp L0008 0005 T5 = CAST (object) CV0($query)
  .sharp L0008 0006 INIT_METHOD_CALL 0 T5 string("rows")
  .sharp L0008 0007 V6 = DO_FCALL
  .sharp L0008 0008 T7 = SHARP_VERIFY_TYPE_ARGS V6 string("List<Any?>, List<#0>")
  .sharp L0008 0009 SEND_VAL_EX T7 1
  .sharp L0008 0010 DO_FCALL
  .sharp L0008 0011 VERIFY_RETURN_TYPE V4
  .sharp L0008 0012 RETURN V4
  .sharp L0008 0013 VERIFY_RETURN_TYPE
  .sharp L0008 0014 RETURN null
  .sharp      ; (lines=15, args=1, vars=2, tmps=7)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp 0004 V4 = NEW 1 V2 T3
  .sharp 0005 T5 = CAST (object) CV0($query)
  .sharp 0006 INIT_METHOD_CALL 0 T5 string("rows")
  .sharp 0007 V6 = DO_FCALL
  .sharp 0008 T7 = SHARP_VERIFY_TYPE_ARGS V6 string("List<Any?>, List<#0>")
  .sharp 0009 SEND_VAL_EX T7 1
  .sharp 0010 DO_FCALL
  .sharp 0011 VERIFY_RETURN_TYPE V4
  .sharp 0012 RETURN V4
  .sharp 0013 VERIFY_RETURN_TYPE
  .sharp 0014 RETURN null
  .sharp      4: 0005 - 0011 (new)
  .sharp      4: 0011 - 0012 (tmp/var)
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   L0008 0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   L0008 0002 T2 = CAST (object) CV0($query)
  .php   L0008 0003 INIT_METHOD_CALL 0 T2 string("rows")
  .php   L0008 0004 V3 = DO_FCALL
  .php   L0008 0005 SEND_VAR_NO_REF_EX V3 1
  .php   L0008 0006 DO_FCALL
  .php   L0008 0007 VERIFY_RETURN_TYPE V1
  .php   L0008 0008 RETURN V1
  .php   L0008 0009 VERIFY_RETURN_TYPE
  .php   L0008 0010 RETURN null
  .php        ; (lines=11, args=1, vars=1, tmps=4)
  .php   0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   0002 T2 = CAST (object) CV0($query)
  .php   0003 INIT_METHOD_CALL 0 T2 string("rows")
  .php   0004 V3 = DO_FCALL
  .php   0005 SEND_VAR_NO_REF_EX V3 1
  .php   0006 DO_FCALL
  .php   0007 VERIFY_RETURN_TYPE V1
  .php   0008 RETURN V1
  .php   0009 VERIFY_RETURN_TYPE
  .php   0010 RETURN null
  .php        1: 0002 - 0007 (new)
  .php        1: 0007 - 0008 (tmp/var)
  .php       /** @template TItem of DatabaseEntity
  .php        *  @param Query<TItem> $query
  .php        *  @return PaginatedList<TItem> */
Generics/Shareable: same opcodes and lines in 2 op arrays, same signatures in 0 classes
Generics/Store: different opcodes and lines in 7 op arrays, same signatures in 1 classes
  .sharp      ; (lines=16, args=2, vars=4, tmps=3)
  .sharp L0015 0002 CV2($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0017 0003 V4 = FE_RESET_R CV0($items) 0012
  .sharp L0017 0004 FE_FETCH_R V4 CV3($item) 0012
  .sharp L0018 0005 T5 = FETCH_OBJ_R CV3($item) string("id")
  .sharp L0018 0006 T6 = IS_IDENTICAL CV1($id) T5
  .sharp L0018 0007 JMPZ T6 0011
  .sharp L0019 0008 VERIFY_RETURN_TYPE CV3($item)
  .sharp L0019 0009 FE_FREE V4 loop-end(+3)
  .sharp L0019 0010 RETURN CV3($item)
  .sharp L0017 0011 JMP 0004
  .sharp L0017 0012 FE_FREE V4
  .sharp L0022 0013 RETURN null
  .sharp L0023 0014 VERIFY_RETURN_TYPE
  .sharp L0023 0015 RETURN null
  .sharp      ; (lines=5, args=1, vars=2, tmps=1)
  .sharp L0028 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Any?")
  .sharp L0028 0002 T2 = FETCH_DIM_R CV0($items) int(0)
  .sharp L0028 0003 RETURN T2
  .sharp L0028 0004 RETURN null
  .sharp      ; (lines=10, args=1, vars=2, tmps=3)
  .sharp L0032 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp L0032 0002 T2 = CAST (object) CV0($item)
  .sharp L0032 0003 INIT_METHOD_CALL 0 T2 string("link")
  .sharp L0032 0004 V3 = DO_FCALL
  .sharp L0032 0005 T4 = SHARP_VERIFY_TYPE_ARGS V3 string("string")
  .sharp L0032 0006 VERIFY_RETURN_TYPE T4
  .sharp L0032 0007 RETURN T4
  .sharp L0032 0008 VERIFY_RETURN_TYPE
  .sharp L0032 0009 RETURN null
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
  .sharp 0013 RETURN null
  .sharp      4: 0004 - 0009 (loop)
  .sharp      4: 0011 - 0012 (loop)
  .sharp      ; (lines=5, args=1, vars=2, tmps=1)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Any?")
  .sharp 0002 T2 = FETCH_DIM_R CV0($items) int(0)
  .sharp 0003 RETURN T2
  .sharp 0004 RETURN null
  .sharp      ; (lines=10, args=1, vars=2, tmps=3)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp 0002 T2 = CAST (object) CV0($item)
  .sharp 0003 INIT_METHOD_CALL 0 T2 string("link")
  .sharp 0005 T4 = SHARP_VERIFY_TYPE_ARGS V3 string("string")
  .sharp 0006 VERIFY_RETURN_TYPE T4
  .sharp 0007 RETURN T4
  .sharp 0008 VERIFY_RETURN_TYPE
  .sharp 0009 RETURN null
  .sharp     Property [ private Sharp\Map $saved = Closure({closure:<file>:8})() ]
  .sharp     Property [ private Sharp\Map $kinds = Closure({closure:<file>:10})() ]
  .php        ; (lines=15, args=2, vars=3, tmps=3)
  .php   L0017 0002 V3 = FE_RESET_R CV0($items) 0011
  .php   L0017 0003 FE_FETCH_R V3 CV2($item) 0011
  .php   L0018 0004 T4 = FETCH_OBJ_R CV2($item) string("id")
  .php   L0018 0005 T5 = IS_IDENTICAL CV1($id) T4
  .php   L0018 0006 JMPZ T5 0010
  .php   L0019 0007 VERIFY_RETURN_TYPE CV2($item)
  .php   L0019 0008 FE_FREE V3 loop-end(+3)
  .php   L0019 0009 RETURN CV2($item)
  .php   L0017 0010 JMP 0003
  .php   L0017 0011 FE_FREE V3
  .php   L0022 0012 RETURN null
  .php   L0023 0013 VERIFY_RETURN_TYPE
  .php   L0023 0014 RETURN null
  .php        ; (lines=4, args=1, vars=1, tmps=1)
  .php   L0028 0001 T1 = FETCH_DIM_R CV0($items) int(0)
  .php   L0028 0002 RETURN T1
  .php   L0028 0003 RETURN null
  .php        ; (lines=8, args=1, vars=1, tmps=2)
  .php   L0032 0001 T1 = CAST (object) CV0($item)
  .php   L0032 0002 INIT_METHOD_CALL 0 T1 string("link")
  .php   L0032 0003 V2 = DO_FCALL
  .php   L0032 0004 VERIFY_RETURN_TYPE V2
  .php   L0032 0005 RETURN V2
  .php   L0032 0006 VERIFY_RETURN_TYPE
  .php   L0032 0007 RETURN null
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
  .php   0013 VERIFY_RETURN_TYPE
  .php   0014 RETURN null
  .php        3: 0003 - 0008 (loop)
  .php        3: 0010 - 0011 (loop)
  .php        ; (lines=4, args=1, vars=1, tmps=1)
  .php   0001 T1 = FETCH_DIM_R CV0($items) int(0)
  .php   0002 RETURN T1
  .php   0003 RETURN null
  .php        ; (lines=8, args=1, vars=1, tmps=2)
  .php   0001 T1 = CAST (object) CV0($item)
  .php   0002 INIT_METHOD_CALL 0 T1 string("link")
  .php   0003 V2 = DO_FCALL
  .php   0004 VERIFY_RETURN_TYPE V2
  .php   0005 RETURN V2
  .php   0006 VERIFY_RETURN_TYPE
  .php   0007 RETURN null
  .php        2: 0004 - 0005 (tmp/var)
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
Generics/Validator: same opcodes and lines in 2 op arrays, same signatures in 0 classes
  check  Paging\Validator::validate string("$0")
Operators/Cart: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Operators/DatabaseEntity: same opcodes and lines in 4 op arrays, same signatures in 1 classes
Operators/Grade: same opcodes and lines in 5 op arrays, same signatures in 1 classes
  check  Billing\Grade::ranked string("List<Billing.Grade>")
Operators/Invoice: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Operators/InvoiceQueue: same opcodes and lines in 6 op arrays, same signatures in 1 classes
Operators/Line: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Operators/Money: same opcodes and lines in 12 op arrays, same signatures in 1 classes
Operators/Operators: different opcodes and lines in 48 op arrays, same signatures in 1 classes
  check  Billing\Operators::byPrice string("List<Billing.Line>")
  check  Billing\Operators::byCode string("List<string>")
  check  Billing\Operators::checkout string("Map<string, Any?>, Any?, Map<string, Any?>")
  .sharp      ; (lines=86, args=3, vars=5, tmps=36)
  .sharp L0193 0004 JMPZ T5 0026
  .sharp L0193 0005 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0193 0006 T7 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp L0193 0007 V8 = NEW 1 V6 T7
  .sharp L0193 0008 INIT_FCALL 1 96 string("array_merge")
  .sharp L0193 0009 SEND_VAL array(...) 1
  .sharp L0193 0010 INIT_FCALL 3 128 string("array_map")
  .sharp L0193 0011 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0193 0012 SEND_VAL T9 1
  .sharp L0193 0013 INIT_FCALL 1 96 string("array_keys")
  .sharp L0193 0014 SEND_VAR CV0($payload) 1
  .sharp L0193 0015 V10 = DO_ICALL
  .sharp L0193 0016 SEND_VAR V10 2
  .sharp L0193 0017 SEND_VAR CV0($payload) 3
  .sharp L0193 0018 V11 = DO_ICALL
  .sharp L0193 0019 SEND_UNPACK V11
  .sharp L0193 0020 CHECK_UNDEF_ARGS
  .sharp L0193 0021 V12 = DO_ICALL
  .sharp L0193 0022 SEND_VAR_NO_REF_EX V12 1
  .sharp L0193 0023 DO_FCALL
  .sharp L0193 0024 T14 = QM_ASSIGN V8
  .sharp L0193 0025 JMP 0027
  .sharp L0193 0026 T14 = QM_ASSIGN CV0($payload)
  .sharp L0193 0027 ASSIGN CV0($payload) T14
  .sharp L0193 0028 T16 = TYPE_CHECK (array) CV2($prices)
  .sharp L0193 0029 JMPZ T16 0051
  .sharp L0193 0030 V17 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0193 0031 T18 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp L0193 0032 V19 = NEW 1 V17 T18
  .sharp L0193 0033 INIT_FCALL 1 96 string("array_merge")
  .sharp L0193 0034 SEND_VAL array(...) 1
  .sharp L0193 0035 INIT_FCALL 3 128 string("array_map")
  .sharp L0193 0036 T20 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0193 0037 SEND_VAL T20 1
  .sharp L0193 0038 INIT_FCALL 1 96 string("array_keys")
  .sharp L0193 0039 SEND_VAR CV2($prices) 1
  .sharp L0193 0040 V21 = DO_ICALL
  .sharp L0193 0041 SEND_VAR V21 2
  .sharp L0193 0042 SEND_VAR CV2($prices) 3
  .sharp L0193 0043 V22 = DO_ICALL
  .sharp L0193 0044 SEND_UNPACK V22
  .sharp L0193 0045 CHECK_UNDEF_ARGS
  .sharp L0193 0046 V23 = DO_ICALL
  .sharp L0193 0047 SEND_VAR_NO_REF_EX V23 1
  .sharp L0193 0048 DO_FCALL
  .sharp L0193 0049 T25 = QM_ASSIGN V19
  .sharp L0193 0050 JMP 0052
  .sharp L0193 0051 T25 = QM_ASSIGN CV2($prices)
  .sharp L0193 0052 ASSIGN CV2($prices) T25
  .sharp L0195 0053 T27 = CAST (object) CV0($payload)
  .sharp L0195 0054 INIT_METHOD_CALL 1 T27 string("get")
  .sharp L0195 0055 SEND_VAL_EX string("orderId") 1
  .sharp L0195 0056 V28 = DO_FCALL
  .sharp L0195 0057 T29 = ASSIGN CV3($orderId) V28
  .sharp L0195 0058 T30 = TYPE_CHECK (long) T29
  .sharp L0195 0059 T31 = BOOL_NOT T30
  .sharp L0195 0060 JMPZ T31 0065
  .sharp L0195 0061 V32 = NEW 1 string("Billing\\BadPayload")
  .sharp L0195 0062 SEND_VAL_EX string("orderId") 1
  .sharp L0195 0063 DO_FCALL
  .sharp L0195 0064 THROW V32
  .sharp L0196 0065 T34 = CAST (object) CV2($prices)
  .sharp L0196 0066 INIT_METHOD_CALL 1 T34 string("get")
  .sharp L0196 0067 SEND_VAR_EX CV1($plan) 1
  .sharp L0196 0068 V35 = DO_FCALL
  .sharp L0196 0069 T36 = ASSIGN CV4($price) V35
  .sharp L0196 0070 T37 = TYPE_CHECK (long) T36
  .sharp L0196 0071 T38 = BOOL_NOT T37
  .sharp L0196 0072 JMPZ T38 0078
  .sharp L0196 0073 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .sharp L0196 0074 SEND_VAR_EX CV1($plan) 1
  .sharp L0196 0075 V39 = DO_FCALL
  .sharp L0196 0076 VERIFY_RETURN_TYPE V39
  .sharp L0196 0077 RETURN V39
  .sharp L0197 0078 INIT_METHOD_CALL 2 THIS string("charge")
  .sharp L0197 0079 SEND_VAR_EX CV3($orderId) 1
  .sharp L0197 0080 SEND_VAR_EX CV4($price) 2
  .sharp L0197 0081 V40 = DO_FCALL
  .sharp L0197 0082 VERIFY_RETURN_TYPE V40
  .sharp L0197 0083 RETURN V40
  .sharp L0198 0084 VERIFY_RETURN_TYPE
  .sharp L0198 0085 RETURN null
  .sharp L0193 0000 CV0($key#1) = RECV 1
  .sharp L0193 0001 CV1($value#2) = RECV 2
  .sharp L0193 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0193 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp L0193 0000 CV0($key#1) = RECV 1
  .sharp L0193 0001 CV1($value#2) = RECV 2
  .sharp L0193 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0193 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=86, args=3, vars=5, tmps=36)
  .sharp 0004 JMPZ T5 0026
  .sharp 0005 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0006 T7 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp 0007 V8 = NEW 1 V6 T7
  .sharp 0008 INIT_FCALL 1 96 string("array_merge")
  .sharp 0009 SEND_VAL array(...) 1
  .sharp 0010 INIT_FCALL 3 128 string("array_map")
  .sharp 0011 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0012 SEND_VAL T9 1
  .sharp 0013 INIT_FCALL 1 96 string("array_keys")
  .sharp 0014 SEND_VAR CV0($payload) 1
  .sharp 0015 V10 = DO_ICALL
  .sharp 0016 SEND_VAR V10 2
  .sharp 0017 SEND_VAR CV0($payload) 3
  .sharp 0018 V11 = DO_ICALL
  .sharp 0019 SEND_UNPACK V11
  .sharp 0020 CHECK_UNDEF_ARGS
  .sharp 0021 V12 = DO_ICALL
  .sharp 0022 SEND_VAR_NO_REF_EX V12 1
  .sharp 0023 DO_FCALL
  .sharp 0024 T14 = QM_ASSIGN V8
  .sharp 0025 JMP 0027
  .sharp 0026 T14 = QM_ASSIGN CV0($payload)
  .sharp 0027 ASSIGN CV0($payload) T14
  .sharp 0028 T16 = TYPE_CHECK (array) CV2($prices)
  .sharp 0029 JMPZ T16 0051
  .sharp 0030 V17 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0031 T18 = SHARP_TYPE_ARGS string("string, Any?")
  .sharp 0032 V19 = NEW 1 V17 T18
  .sharp 0033 INIT_FCALL 1 96 string("array_merge")
  .sharp 0034 SEND_VAL array(...) 1
  .sharp 0035 INIT_FCALL 3 128 string("array_map")
  .sharp 0036 T20 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0037 SEND_VAL T20 1
  .sharp 0038 INIT_FCALL 1 96 string("array_keys")
  .sharp 0039 SEND_VAR CV2($prices) 1
  .sharp 0040 V21 = DO_ICALL
  .sharp 0041 SEND_VAR V21 2
  .sharp 0042 SEND_VAR CV2($prices) 3
  .sharp 0043 V22 = DO_ICALL
  .sharp 0044 SEND_UNPACK V22
  .sharp 0045 CHECK_UNDEF_ARGS
  .sharp 0046 V23 = DO_ICALL
  .sharp 0047 SEND_VAR_NO_REF_EX V23 1
  .sharp 0048 DO_FCALL
  .sharp 0049 T25 = QM_ASSIGN V19
  .sharp 0050 JMP 0052
  .sharp 0051 T25 = QM_ASSIGN CV2($prices)
  .sharp 0052 ASSIGN CV2($prices) T25
  .sharp 0053 T27 = CAST (object) CV0($payload)
  .sharp 0054 INIT_METHOD_CALL 1 T27 string("get")
  .sharp 0055 SEND_VAL_EX string("orderId") 1
  .sharp 0056 V28 = DO_FCALL
  .sharp 0057 T29 = ASSIGN CV3($orderId) V28
  .sharp 0058 T30 = TYPE_CHECK (long) T29
  .sharp 0059 T31 = BOOL_NOT T30
  .sharp 0060 JMPZ T31 0065
  .sharp 0061 V32 = NEW 1 string("Billing\\BadPayload")
  .sharp 0062 SEND_VAL_EX string("orderId") 1
  .sharp 0063 DO_FCALL
  .sharp 0064 THROW V32
  .sharp 0065 T34 = CAST (object) CV2($prices)
  .sharp 0066 INIT_METHOD_CALL 1 T34 string("get")
  .sharp 0067 SEND_VAR_EX CV1($plan) 1
  .sharp 0068 V35 = DO_FCALL
  .sharp 0069 T36 = ASSIGN CV4($price) V35
  .sharp 0070 T37 = TYPE_CHECK (long) T36
  .sharp 0071 T38 = BOOL_NOT T37
  .sharp 0072 JMPZ T38 0078
  .sharp 0073 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .sharp 0074 SEND_VAR_EX CV1($plan) 1
  .sharp 0075 V39 = DO_FCALL
  .sharp 0076 VERIFY_RETURN_TYPE V39
  .sharp 0077 RETURN V39
  .sharp 0078 INIT_METHOD_CALL 2 THIS string("charge")
  .sharp 0079 SEND_VAR_EX CV3($orderId) 1
  .sharp 0080 SEND_VAR_EX CV4($price) 2
  .sharp 0081 V40 = DO_FCALL
  .sharp 0082 VERIFY_RETURN_TYPE V40
  .sharp 0083 RETURN V40
  .sharp 0084 VERIFY_RETURN_TYPE
  .sharp 0085 RETURN null
  .sharp      8: 0008 - 0024 (new)
  .sharp      19: 0033 - 0049 (new)
  .sharp      32: 0062 - 0064 (new)
  .sharp      39: 0076 - 0077 (tmp/var)
  .sharp      40: 0082 - 0083 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=82, args=3, vars=5, tmps=32)
  .php   L0193 0004 JMPZ T5 0024
  .php   L0193 0005 V6 = NEW 1 string("Sharp\\Map")
  .php   L0193 0006 INIT_FCALL 1 96 string("array_merge")
  .php   L0193 0007 SEND_VAL array(...) 1
  .php   L0193 0008 INIT_FCALL 3 128 string("array_map")
  .php   L0193 0009 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0193 0010 SEND_VAL T7 1
  .php   L0193 0011 INIT_FCALL 1 96 string("array_keys")
  .php   L0193 0012 SEND_VAR CV0($payload) 1
  .php   L0193 0013 V8 = DO_ICALL
  .php   L0193 0014 SEND_VAR V8 2
  .php   L0193 0015 SEND_VAR CV0($payload) 3
  .php   L0193 0016 V9 = DO_ICALL
  .php   L0193 0017 SEND_UNPACK V9
  .php   L0193 0018 CHECK_UNDEF_ARGS
  .php   L0193 0019 V10 = DO_ICALL
  .php   L0193 0020 SEND_VAR_NO_REF_EX V10 1
  .php   L0193 0021 DO_FCALL
  .php   L0193 0022 T12 = QM_ASSIGN V6
  .php   L0193 0023 JMP 0025
  .php   L0193 0024 T12 = QM_ASSIGN CV0($payload)
  .php   L0193 0025 ASSIGN CV0($payload) T12
  .php   L0193 0026 T14 = TYPE_CHECK (array) CV2($prices)
  .php   L0193 0027 JMPZ T14 0047
  .php   L0193 0028 V15 = NEW 1 string("Sharp\\Map")
  .php   L0193 0029 INIT_FCALL 1 96 string("array_merge")
  .php   L0193 0030 SEND_VAL array(...) 1
  .php   L0193 0031 INIT_FCALL 3 128 string("array_map")
  .php   L0193 0032 T16 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0193 0033 SEND_VAL T16 1
  .php   L0193 0034 INIT_FCALL 1 96 string("array_keys")
  .php   L0193 0035 SEND_VAR CV2($prices) 1
  .php   L0193 0036 V17 = DO_ICALL
  .php   L0193 0037 SEND_VAR V17 2
  .php   L0193 0038 SEND_VAR CV2($prices) 3
  .php   L0193 0039 V18 = DO_ICALL
  .php   L0193 0040 SEND_UNPACK V18
  .php   L0193 0041 CHECK_UNDEF_ARGS
  .php   L0193 0042 V19 = DO_ICALL
  .php   L0193 0043 SEND_VAR_NO_REF_EX V19 1
  .php   L0193 0044 DO_FCALL
  .php   L0193 0045 T21 = QM_ASSIGN V15
  .php   L0193 0046 JMP 0048
  .php   L0193 0047 T21 = QM_ASSIGN CV2($prices)
  .php   L0193 0048 ASSIGN CV2($prices) T21
  .php   L0195 0049 T23 = CAST (object) CV0($payload)
  .php   L0195 0050 INIT_METHOD_CALL 1 T23 string("get")
  .php   L0195 0051 SEND_VAL_EX string("orderId") 1
  .php   L0195 0052 V24 = DO_FCALL
  .php   L0195 0053 T25 = ASSIGN CV3($orderId) V24
  .php   L0195 0054 T26 = TYPE_CHECK (long) T25
  .php   L0195 0055 T27 = BOOL_NOT T26
  .php   L0195 0056 JMPZ T27 0061
  .php   L0195 0057 V28 = NEW 1 string("Billing\\BadPayload")
  .php   L0195 0058 SEND_VAL_EX string("orderId") 1
  .php   L0195 0059 DO_FCALL
  .php   L0195 0060 THROW V28
  .php   L0196 0061 T30 = CAST (object) CV2($prices)
  .php   L0196 0062 INIT_METHOD_CALL 1 T30 string("get")
  .php   L0196 0063 SEND_VAR_EX CV1($plan) 1
  .php   L0196 0064 V31 = DO_FCALL
  .php   L0196 0065 T32 = ASSIGN CV4($price) V31
  .php   L0196 0066 T33 = TYPE_CHECK (long) T32
  .php   L0196 0067 T34 = BOOL_NOT T33
  .php   L0196 0068 JMPZ T34 0074
  .php   L0196 0069 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .php   L0196 0070 SEND_VAR_EX CV1($plan) 1
  .php   L0196 0071 V35 = DO_FCALL
  .php   L0196 0072 VERIFY_RETURN_TYPE V35
  .php   L0196 0073 RETURN V35
  .php   L0197 0074 INIT_METHOD_CALL 2 THIS string("charge")
  .php   L0197 0075 SEND_VAR_EX CV3($orderId) 1
  .php   L0197 0076 SEND_VAR_EX CV4($price) 2
  .php   L0197 0077 V36 = DO_FCALL
  .php   L0197 0078 VERIFY_RETURN_TYPE V36
  .php   L0197 0079 RETURN V36
  .php   L0198 0080 VERIFY_RETURN_TYPE
  .php   L0198 0081 RETURN null
  .php   L0193 0000 CV0($key) = RECV 1
  .php   L0193 0001 CV1($value) = RECV 2
  .php   L0193 0002 T2 = CAST (string) CV0($key)
  .php   L0193 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   L0193 0000 CV0($key) = RECV 1
  .php   L0193 0001 CV1($value) = RECV 2
  .php   L0193 0002 T2 = CAST (string) CV0($key)
  .php   L0193 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=82, args=3, vars=5, tmps=32)
  .php   0004 JMPZ T5 0024
  .php   0005 V6 = NEW 1 string("Sharp\\Map")
  .php   0006 INIT_FCALL 1 96 string("array_merge")
  .php   0007 SEND_VAL array(...) 1
  .php   0008 INIT_FCALL 3 128 string("array_map")
  .php   0009 T7 = DECLARE_LAMBDA_FUNCTION 0
  .php   0010 SEND_VAL T7 1
  .php   0011 INIT_FCALL 1 96 string("array_keys")
  .php   0012 SEND_VAR CV0($payload) 1
  .php   0013 V8 = DO_ICALL
  .php   0014 SEND_VAR V8 2
  .php   0015 SEND_VAR CV0($payload) 3
  .php   0016 V9 = DO_ICALL
  .php   0017 SEND_UNPACK V9
  .php   0018 CHECK_UNDEF_ARGS
  .php   0019 V10 = DO_ICALL
  .php   0020 SEND_VAR_NO_REF_EX V10 1
  .php   0021 DO_FCALL
  .php   0022 T12 = QM_ASSIGN V6
  .php   0023 JMP 0025
  .php   0024 T12 = QM_ASSIGN CV0($payload)
  .php   0025 ASSIGN CV0($payload) T12
  .php   0026 T14 = TYPE_CHECK (array) CV2($prices)
  .php   0027 JMPZ T14 0047
  .php   0028 V15 = NEW 1 string("Sharp\\Map")
  .php   0029 INIT_FCALL 1 96 string("array_merge")
  .php   0030 SEND_VAL array(...) 1
  .php   0031 INIT_FCALL 3 128 string("array_map")
  .php   0032 T16 = DECLARE_LAMBDA_FUNCTION 1
  .php   0033 SEND_VAL T16 1
  .php   0034 INIT_FCALL 1 96 string("array_keys")
  .php   0035 SEND_VAR CV2($prices) 1
  .php   0036 V17 = DO_ICALL
  .php   0037 SEND_VAR V17 2
  .php   0038 SEND_VAR CV2($prices) 3
  .php   0039 V18 = DO_ICALL
  .php   0040 SEND_UNPACK V18
  .php   0041 CHECK_UNDEF_ARGS
  .php   0042 V19 = DO_ICALL
  .php   0043 SEND_VAR_NO_REF_EX V19 1
  .php   0044 DO_FCALL
  .php   0045 T21 = QM_ASSIGN V15
  .php   0046 JMP 0048
  .php   0047 T21 = QM_ASSIGN CV2($prices)
  .php   0048 ASSIGN CV2($prices) T21
  .php   0049 T23 = CAST (object) CV0($payload)
  .php   0050 INIT_METHOD_CALL 1 T23 string("get")
  .php   0051 SEND_VAL_EX string("orderId") 1
  .php   0052 V24 = DO_FCALL
  .php   0053 T25 = ASSIGN CV3($orderId) V24
  .php   0054 T26 = TYPE_CHECK (long) T25
  .php   0055 T27 = BOOL_NOT T26
  .php   0056 JMPZ T27 0061
  .php   0057 V28 = NEW 1 string("Billing\\BadPayload")
  .php   0058 SEND_VAL_EX string("orderId") 1
  .php   0059 DO_FCALL
  .php   0060 THROW V28
  .php   0061 T30 = CAST (object) CV2($prices)
  .php   0062 INIT_METHOD_CALL 1 T30 string("get")
  .php   0063 SEND_VAR_EX CV1($plan) 1
  .php   0064 V31 = DO_FCALL
  .php   0065 T32 = ASSIGN CV4($price) V31
  .php   0066 T33 = TYPE_CHECK (long) T32
  .php   0067 T34 = BOOL_NOT T33
  .php   0068 JMPZ T34 0074
  .php   0069 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .php   0070 SEND_VAR_EX CV1($plan) 1
  .php   0071 V35 = DO_FCALL
  .php   0072 VERIFY_RETURN_TYPE V35
  .php   0073 RETURN V35
  .php   0074 INIT_METHOD_CALL 2 THIS string("charge")
  .php   0075 SEND_VAR_EX CV3($orderId) 1
  .php   0076 SEND_VAR_EX CV4($price) 2
  .php   0077 V36 = DO_FCALL
  .php   0078 VERIFY_RETURN_TYPE V36
  .php   0079 RETURN V36
  .php   0080 VERIFY_RETURN_TYPE
  .php   0081 RETURN null
  .php        6: 0006 - 0022 (new)
  .php        15: 0029 - 0045 (new)
  .php        28: 0058 - 0060 (new)
  .php        35: 0072 - 0073 (tmp/var)
  .php        36: 0078 - 0079 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
Operators/Order: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Traits/Align: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Traits/Draft: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Traits/Essay: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Traits/Guide: same opcodes and lines in 3 op arrays, same signatures in 1 classes
Traits/Linked: same opcodes and lines in 2 op arrays, same signatures in 0 classes
Traits/Posting: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Traits/Shade: same opcodes and lines in 1 op arrays, same signatures in 1 classes
Traits/Tier: same opcodes and lines in 2 op arrays, same signatures in 1 classes
a check: same
a check and another opcode: different
