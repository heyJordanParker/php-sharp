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
Calc: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Nulls: same opcodes and lines in 18 op arrays, same signatures in 1 classes
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
  .sharp      ; (lines=71, args=1, vars=7, tmps=39)
  .sharp L0012 0001 V8 = NEW 2 string("Demo\\Item")
  .sharp L0012 0005 T10 = INIT_ARRAY 1 (packed) V8 NEXT
  .sharp L0012 0007 OP_DATA T10
  .sharp L0013 0008 T11 = FETCH_DIM_R CV0($extra) int(0)
  .sharp L0013 0009 ASSIGN CV1($argument#1) T11
  .sharp L0013 0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0013 0011 V13 = FETCH_OBJ_W (ref) THIS string("items")
  .sharp L0013 0012 SEND_REF V13 1
  .sharp L0013 0013 SEND_VAR CV1($argument#1) 2
  .sharp L0013 0014 DO_ICALL
  .sharp L0013 0015 V15 = FETCH_OBJ_UNSET THIS string("items")
  .sharp L0013 0016 V15 = SEPARATE V15
  .sharp L0014 0017 T16 = FETCH_OBJ_R THIS string("items")
  .sharp L0014 0018 T17 = COUNT T16
  .sharp L0014 0019 ASSIGN CV2($value#1) T17
  .sharp L0014 0020 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0014 0021 V19 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp L0014 0022 SEND_REF V19 1
  .sharp L0014 0023 SEND_VAL string("items") 2
  .sharp L0014 0024 SEND_VAR CV2($value#1) 3
  .sharp L0014 0025 DO_ICALL
  .sharp L0014 0026 V21 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp L0014 0027 V21 = SEPARATE V21
  .sharp L0015 0028 T22 = COUNT CV0($extra)
  .sharp L0015 0029 ASSIGN CV2($value#1) T22
  .sharp L0015 0030 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0015 0031 V24 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp L0015 0032 SEND_REF V24 1
  .sharp L0015 0033 SEND_VAL string("extra") 2
  .sharp L0015 0034 SEND_VAR CV2($value#1) 3
  .sharp L0015 0035 DO_ICALL
  .sharp L0015 0036 V26 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp L0015 0037 V26 = SEPARATE V26
  .sharp L0016 0038 T27 = FETCH_OBJ_R THIS string("items")
  .sharp L0016 0039 T28 = FETCH_DIM_R T27 int(0)
  .sharp L0016 0040 T29 = FETCH_OBJ_R T28 string("cents")
  .sharp L0016 0041 ASSIGN CV3($total) T29
  .sharp L0017 0042 T31 = FETCH_OBJ_R THIS string("items")
  .sharp L0017 0043 V32 = FE_RESET_R T31 0048
  .sharp L0017 0044 FE_FETCH_R V32 CV4($item) 0048
  .sharp L0018 0045 T33 = FETCH_OBJ_R CV4($item) string("cents")
  .sharp L0018 0046 ASSIGN_OP (ADD) CV3($total) T33
  .sharp L0017 0047 JMP 0044
  .sharp L0017 0048 FE_FREE V32
  .sharp L0020 0049 T35 = FETCH_OBJ_R THIS string("counts")
  .sharp L0020 0050 V36 = FE_RESET_R T35 0058
  .sharp L0020 0051 T37 = FE_FETCH_R V36 CV5($size) 0058
  .sharp L0020 0052 ASSIGN CV6($name) T37
  .sharp L0021 0053 T39 = IS_IDENTICAL CV6($name) string("items")
  .sharp L0021 0054 JMPZ T39 0057
  .sharp L0022 0055 T40 = MUL CV5($size) int(10)
  .sharp L0022 0056 ASSIGN_OP (ADD) CV3($total) T40
  .sharp L0020 0057 JMP 0051
  .sharp L0020 0058 FE_FREE V36
  .sharp L0025 0059 INIT_STATIC_METHOD_CALL 1 string("Lib\\Slots") string("fromArray")
  .sharp L0025 0060 INIT_METHOD_CALL 0 THIS string("prices")
  .sharp L0025 0061 V42 = DO_FCALL
  .sharp L0025 0062 SEND_VAR_NO_REF_EX V42 1
  .sharp L0025 0063 V43 = DO_FCALL
  .sharp L0025 0064 INIT_METHOD_CALL 0 V43 string("getSize")
  .sharp L0025 0065 V44 = DO_FCALL
  .sharp L0025 0066 T45 = ADD CV3($total) V44
  .sharp L0025 0067 VERIFY_RETURN_TYPE T45
  .sharp L0025 0068 RETURN T45
  .sharp L0026 0069 VERIFY_RETURN_TYPE
  .sharp L0026 0070 RETURN null
  .sharp      ; (lines=17, args=0, vars=3, tmps=6)
  .sharp L0031 0001 T4 = FETCH_OBJ_R THIS string("items")
  .sharp L0031 0002 V5 = FE_RESET_R T4 0012
  .sharp L0031 0003 FE_FETCH_R V5 CV1($item) 0012
  .sharp L0032 0004 T6 = FETCH_OBJ_R CV1($item) string("cents")
  .sharp L0032 0005 ASSIGN CV2($argument#1) T6
  .sharp L0032 0006 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0032 0007 SEND_REF CV0($prices) 1
  .sharp L0032 0008 SEND_VAR CV2($argument#1) 2
  .sharp L0032 0009 DO_ICALL
  .sharp L0032 0010 SEPARATE CV0($prices)
  .sharp L0031 0011 JMP 0003
  .sharp L0031 0012 FE_FREE V5
  .sharp L0034 0013 VERIFY_RETURN_TYPE CV0($prices)
  .sharp L0034 0014 RETURN CV0($prices)
  .sharp L0035 0015 VERIFY_RETURN_TYPE
  .sharp L0035 0016 RETURN null
  .sharp      ; (lines=23, args=1, vars=2, tmps=11)
  .sharp L0039 0001 T2 = FETCH_OBJ_IS THIS string("counts")
  .sharp L0039 0002 T3 = FETCH_DIM_IS T2 CV0($name)
  .sharp L0039 0003 T4 = COALESCE T3 0005
  .sharp L0039 0004 T4 = QM_ASSIGN int(0)
  .sharp L0039 0005 T5 = ADD T4 int(1)
  .sharp L0039 0006 ASSIGN CV1($value#1) T5
  .sharp L0039 0007 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0039 0008 V7 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp L0039 0009 SEND_REF V7 1
  .sharp L0039 0010 SEND_VAR CV0($name) 2
  .sharp L0039 0011 SEND_VAR CV1($value#1) 3
  .sharp L0039 0012 DO_ICALL
  .sharp L0039 0013 V9 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp L0039 0014 V9 = SEPARATE V9
  .sharp L0040 0015 T10 = FETCH_OBJ_IS THIS string("counts")
  .sharp L0040 0016 T11 = FETCH_DIM_IS T10 CV0($name)
  .sharp L0040 0017 T12 = COALESCE T11 0019
  .sharp L0040 0018 T12 = QM_ASSIGN int(0)
  .sharp L0040 0019 VERIFY_RETURN_TYPE T12
  .sharp L0040 0020 RETURN T12
  .sharp L0041 0021 VERIFY_RETURN_TYPE
  .sharp L0041 0022 RETURN null
  .sharp      ; (lines=71, args=1, vars=7, tmps=39)
  .sharp 0001 V8 = NEW 2 string("Demo\\Item")
  .sharp 0005 T10 = INIT_ARRAY 1 (packed) V8 NEXT
  .sharp 0007 OP_DATA T10
  .sharp 0008 T11 = FETCH_DIM_R CV0($extra) int(0)
  .sharp 0009 ASSIGN CV1($argument#1) T11
  .sharp 0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0011 V13 = FETCH_OBJ_W (ref) THIS string("items")
  .sharp 0012 SEND_REF V13 1
  .sharp 0013 SEND_VAR CV1($argument#1) 2
  .sharp 0014 DO_ICALL
  .sharp 0015 V15 = FETCH_OBJ_UNSET THIS string("items")
  .sharp 0016 V15 = SEPARATE V15
  .sharp 0017 T16 = FETCH_OBJ_R THIS string("items")
  .sharp 0018 T17 = COUNT T16
  .sharp 0019 ASSIGN CV2($value#1) T17
  .sharp 0020 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0021 V19 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp 0022 SEND_REF V19 1
  .sharp 0023 SEND_VAL string("items") 2
  .sharp 0024 SEND_VAR CV2($value#1) 3
  .sharp 0025 DO_ICALL
  .sharp 0026 V21 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp 0027 V21 = SEPARATE V21
  .sharp 0028 T22 = COUNT CV0($extra)
  .sharp 0029 ASSIGN CV2($value#1) T22
  .sharp 0030 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0031 V24 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp 0032 SEND_REF V24 1
  .sharp 0033 SEND_VAL string("extra") 2
  .sharp 0034 SEND_VAR CV2($value#1) 3
  .sharp 0035 DO_ICALL
  .sharp 0036 V26 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp 0037 V26 = SEPARATE V26
  .sharp 0038 T27 = FETCH_OBJ_R THIS string("items")
  .sharp 0039 T28 = FETCH_DIM_R T27 int(0)
  .sharp 0040 T29 = FETCH_OBJ_R T28 string("cents")
  .sharp 0041 ASSIGN CV3($total) T29
  .sharp 0042 T31 = FETCH_OBJ_R THIS string("items")
  .sharp 0043 V32 = FE_RESET_R T31 0048
  .sharp 0044 FE_FETCH_R V32 CV4($item) 0048
  .sharp 0045 T33 = FETCH_OBJ_R CV4($item) string("cents")
  .sharp 0046 ASSIGN_OP (ADD) CV3($total) T33
  .sharp 0047 JMP 0044
  .sharp 0048 FE_FREE V32
  .sharp 0049 T35 = FETCH_OBJ_R THIS string("counts")
  .sharp 0050 V36 = FE_RESET_R T35 0058
  .sharp 0051 T37 = FE_FETCH_R V36 CV5($size) 0058
  .sharp 0052 ASSIGN CV6($name) T37
  .sharp 0053 T39 = IS_IDENTICAL CV6($name) string("items")
  .sharp 0054 JMPZ T39 0057
  .sharp 0055 T40 = MUL CV5($size) int(10)
  .sharp 0056 ASSIGN_OP (ADD) CV3($total) T40
  .sharp 0057 JMP 0051
  .sharp 0058 FE_FREE V36
  .sharp 0059 INIT_STATIC_METHOD_CALL 1 string("Lib\\Slots") string("fromArray")
  .sharp 0060 INIT_METHOD_CALL 0 THIS string("prices")
  .sharp 0061 V42 = DO_FCALL
  .sharp 0062 SEND_VAR_NO_REF_EX V42 1
  .sharp 0063 V43 = DO_FCALL
  .sharp 0064 INIT_METHOD_CALL 0 V43 string("getSize")
  .sharp 0065 V44 = DO_FCALL
  .sharp 0066 T45 = ADD CV3($total) V44
  .sharp 0067 VERIFY_RETURN_TYPE T45
  .sharp 0068 RETURN T45
  .sharp 0069 VERIFY_RETURN_TYPE
  .sharp 0070 RETURN null
  .sharp      8: 0002 - 0005 (new)
  .sharp      32: 0044 - 0048 (loop)
  .sharp      36: 0051 - 0058 (loop)
  .sharp      45: 0067 - 0068 (tmp/var)
  .sharp      ; (lines=17, args=0, vars=3, tmps=6)
  .sharp 0001 T4 = FETCH_OBJ_R THIS string("items")
  .sharp 0002 V5 = FE_RESET_R T4 0012
  .sharp 0003 FE_FETCH_R V5 CV1($item) 0012
  .sharp 0004 T6 = FETCH_OBJ_R CV1($item) string("cents")
  .sharp 0005 ASSIGN CV2($argument#1) T6
  .sharp 0006 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0007 SEND_REF CV0($prices) 1
  .sharp 0008 SEND_VAR CV2($argument#1) 2
  .sharp 0009 DO_ICALL
  .sharp 0010 SEPARATE CV0($prices)
  .sharp 0011 JMP 0003
  .sharp 0012 FE_FREE V5
  .sharp 0013 VERIFY_RETURN_TYPE CV0($prices)
  .sharp 0014 RETURN CV0($prices)
  .sharp      5: 0003 - 0012 (loop)
  .sharp      ; (lines=23, args=1, vars=2, tmps=11)
  .sharp 0001 T2 = FETCH_OBJ_IS THIS string("counts")
  .sharp 0002 T3 = FETCH_DIM_IS T2 CV0($name)
  .sharp 0003 T4 = COALESCE T3 0005
  .sharp 0004 T4 = QM_ASSIGN int(0)
  .sharp 0005 T5 = ADD T4 int(1)
  .sharp 0006 ASSIGN CV1($value#1) T5
  .sharp 0007 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0008 V7 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp 0009 SEND_REF V7 1
  .sharp 0010 SEND_VAR CV0($name) 2
  .sharp 0011 SEND_VAR CV1($value#1) 3
  .sharp 0012 DO_ICALL
  .sharp 0013 V9 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp 0014 V9 = SEPARATE V9
  .sharp 0015 T10 = FETCH_OBJ_IS THIS string("counts")
  .sharp 0016 T11 = FETCH_DIM_IS T10 CV0($name)
  .sharp 0017 T12 = COALESCE T11 0019
  .sharp 0018 T12 = QM_ASSIGN int(0)
  .sharp 0019 VERIFY_RETURN_TYPE T12
  .sharp 0020 RETURN T12
  .sharp 0021 VERIFY_RETURN_TYPE
  .sharp 0022 RETURN null
  .sharp      12: 0019 - 0020 (tmp/var)
  .sharp     Property [ private Sharp\Map $counts = Closure({closure:<file>:8})() ]
  .php        ; (lines=56, args=1, vars=5, tmps=33)
  .php   L0012 0001 V6 = NEW 2 string("Demo\\Item")
  .php   L0012 0005 T8 = INIT_ARRAY 1 (packed) V6 NEXT
  .php   L0012 0007 OP_DATA T8
  .php   L0013 0008 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0013 0009 V9 = FETCH_OBJ_W (ref) THIS string("items")
  .php   L0013 0010 SEND_REF V9 1
  .php   L0013 0011 T10 = FETCH_DIM_R CV0($extra) int(0)
  .php   L0013 0012 SEND_VAL T10 2
  .php   L0013 0013 DO_ICALL
  .php   L0014 0014 T14 = FETCH_OBJ_R THIS string("items")
  .php   L0014 0015 T15 = COUNT T14
  .php   L0014 0016 V12 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   L0014 0017 ASSIGN_DIM V12 string("items")
  .php   L0014 0018 OP_DATA T15
  .php   L0015 0019 T18 = COUNT CV0($extra)
  .php   L0015 0020 V16 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   L0015 0021 ASSIGN_DIM V16 string("extra")
  .php   L0015 0022 OP_DATA T18
  .php   L0016 0023 T19 = FETCH_OBJ_R THIS string("items")
  .php   L0016 0024 T20 = FETCH_DIM_R T19 int(0)
  .php   L0016 0025 T21 = FETCH_OBJ_R T20 string("cents")
  .php   L0016 0026 ASSIGN CV1($total) T21
  .php   L0017 0027 T23 = FETCH_OBJ_R THIS string("items")
  .php   L0017 0028 V24 = FE_RESET_R T23 0033
  .php   L0017 0029 FE_FETCH_R V24 CV2($item) 0033
  .php   L0018 0030 T25 = FETCH_OBJ_R CV2($item) string("cents")
  .php   L0018 0031 ASSIGN_OP (ADD) CV1($total) T25
  .php   L0017 0032 JMP 0029
  .php   L0017 0033 FE_FREE V24
  .php   L0020 0034 T27 = FETCH_OBJ_R THIS string("counts")
  .php   L0020 0035 V28 = FE_RESET_R T27 0043
  .php   L0020 0036 T29 = FE_FETCH_R V28 CV3($size) 0043
  .php   L0020 0037 ASSIGN CV4($name) T29
  .php   L0021 0038 T31 = IS_IDENTICAL CV4($name) string("items")
  .php   L0021 0039 JMPZ T31 0042
  .php   L0022 0040 T32 = MUL CV3($size) int(10)
  .php   L0022 0041 ASSIGN_OP (ADD) CV1($total) T32
  .php   L0020 0042 JMP 0036
  .php   L0020 0043 FE_FREE V28
  .php   L0025 0044 INIT_STATIC_METHOD_CALL 1 string("Lib\\Slots") string("fromArray")
  .php   L0025 0045 INIT_METHOD_CALL 0 THIS string("prices")
  .php   L0025 0046 V34 = DO_FCALL
  .php   L0025 0047 SEND_VAR_NO_REF_EX V34 1
  .php   L0025 0048 V35 = DO_FCALL
  .php   L0025 0049 INIT_METHOD_CALL 0 V35 string("getSize")
  .php   L0025 0050 V36 = DO_FCALL
  .php   L0025 0051 T37 = ADD CV1($total) V36
  .php   L0025 0052 VERIFY_RETURN_TYPE T37
  .php   L0025 0053 RETURN T37
  .php   L0026 0054 VERIFY_RETURN_TYPE
  .php   L0026 0055 RETURN null
  .php        ; (lines=15, args=0, vars=2, tmps=5)
  .php   L0031 0001 T3 = FETCH_OBJ_R THIS string("items")
  .php   L0031 0002 V4 = FE_RESET_R T3 0010
  .php   L0031 0003 FE_FETCH_R V4 CV1($item) 0010
  .php   L0032 0004 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0032 0005 SEND_REF CV0($prices) 1
  .php   L0032 0006 T5 = FETCH_OBJ_R CV1($item) string("cents")
  .php   L0032 0007 SEND_VAL T5 2
  .php   L0032 0008 DO_ICALL
  .php   L0031 0009 JMP 0003
  .php   L0031 0010 FE_FREE V4
  .php   L0034 0011 VERIFY_RETURN_TYPE CV0($prices)
  .php   L0034 0012 RETURN CV0($prices)
  .php   L0035 0013 VERIFY_RETURN_TYPE
  .php   L0035 0014 RETURN null
  .php        ; (lines=17, args=1, vars=1, tmps=9)
  .php   L0039 0001 T3 = FETCH_OBJ_IS THIS string("counts")
  .php   L0039 0002 T4 = FETCH_DIM_IS T3 CV0($name)
  .php   L0039 0003 T5 = COALESCE T4 0005
  .php   L0039 0004 T5 = QM_ASSIGN int(0)
  .php   L0039 0005 T6 = ADD T5 int(1)
  .php   L0039 0006 V1 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   L0039 0007 ASSIGN_DIM V1 CV0($name)
  .php   L0039 0008 OP_DATA T6
  .php   L0040 0009 T7 = FETCH_OBJ_IS THIS string("counts")
  .php   L0040 0010 T8 = FETCH_DIM_IS T7 CV0($name)
  .php   L0040 0011 T9 = COALESCE T8 0013
  .php   L0040 0012 T9 = QM_ASSIGN int(0)
  .php   L0040 0013 VERIFY_RETURN_TYPE T9
  .php   L0040 0014 RETURN T9
  .php   L0041 0015 VERIFY_RETURN_TYPE
  .php   L0041 0016 RETURN null
  .php        ; (lines=56, args=1, vars=5, tmps=33)
  .php   0001 V6 = NEW 2 string("Demo\\Item")
  .php   0005 T8 = INIT_ARRAY 1 (packed) V6 NEXT
  .php   0007 OP_DATA T8
  .php   0008 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0009 V9 = FETCH_OBJ_W (ref) THIS string("items")
  .php   0010 SEND_REF V9 1
  .php   0011 T10 = FETCH_DIM_R CV0($extra) int(0)
  .php   0012 SEND_VAL T10 2
  .php   0013 DO_ICALL
  .php   0014 T14 = FETCH_OBJ_R THIS string("items")
  .php   0015 T15 = COUNT T14
  .php   0016 V12 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   0017 ASSIGN_DIM V12 string("items")
  .php   0018 OP_DATA T15
  .php   0019 T18 = COUNT CV0($extra)
  .php   0020 V16 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   0021 ASSIGN_DIM V16 string("extra")
  .php   0022 OP_DATA T18
  .php   0023 T19 = FETCH_OBJ_R THIS string("items")
  .php   0024 T20 = FETCH_DIM_R T19 int(0)
  .php   0025 T21 = FETCH_OBJ_R T20 string("cents")
  .php   0026 ASSIGN CV1($total) T21
  .php   0027 T23 = FETCH_OBJ_R THIS string("items")
  .php   0028 V24 = FE_RESET_R T23 0033
  .php   0029 FE_FETCH_R V24 CV2($item) 0033
  .php   0030 T25 = FETCH_OBJ_R CV2($item) string("cents")
  .php   0031 ASSIGN_OP (ADD) CV1($total) T25
  .php   0032 JMP 0029
  .php   0033 FE_FREE V24
  .php   0034 T27 = FETCH_OBJ_R THIS string("counts")
  .php   0035 V28 = FE_RESET_R T27 0043
  .php   0036 T29 = FE_FETCH_R V28 CV3($size) 0043
  .php   0037 ASSIGN CV4($name) T29
  .php   0038 T31 = IS_IDENTICAL CV4($name) string("items")
  .php   0039 JMPZ T31 0042
  .php   0040 T32 = MUL CV3($size) int(10)
  .php   0041 ASSIGN_OP (ADD) CV1($total) T32
  .php   0042 JMP 0036
  .php   0043 FE_FREE V28
  .php   0044 INIT_STATIC_METHOD_CALL 1 string("Lib\\Slots") string("fromArray")
  .php   0045 INIT_METHOD_CALL 0 THIS string("prices")
  .php   0046 V34 = DO_FCALL
  .php   0047 SEND_VAR_NO_REF_EX V34 1
  .php   0048 V35 = DO_FCALL
  .php   0049 INIT_METHOD_CALL 0 V35 string("getSize")
  .php   0050 V36 = DO_FCALL
  .php   0051 T37 = ADD CV1($total) V36
  .php   0052 VERIFY_RETURN_TYPE T37
  .php   0053 RETURN T37
  .php   0054 VERIFY_RETURN_TYPE
  .php   0055 RETURN null
  .php        6: 0002 - 0005 (new)
  .php        15: 0016 - 0017 (tmp/var)
  .php        18: 0020 - 0021 (tmp/var)
  .php        24: 0029 - 0033 (loop)
  .php        28: 0036 - 0043 (loop)
  .php        37: 0052 - 0053 (tmp/var)
  .php        ; (lines=15, args=0, vars=2, tmps=5)
  .php   0002 V4 = FE_RESET_R T3 0010
  .php   0003 FE_FETCH_R V4 CV1($item) 0010
  .php   0004 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0005 SEND_REF CV0($prices) 1
  .php   0006 T5 = FETCH_OBJ_R CV1($item) string("cents")
  .php   0007 SEND_VAL T5 2
  .php   0008 DO_ICALL
  .php   0009 JMP 0003
  .php   0010 FE_FREE V4
  .php   0011 VERIFY_RETURN_TYPE CV0($prices)
  .php   0012 RETURN CV0($prices)
  .php   0013 VERIFY_RETURN_TYPE
  .php   0014 RETURN null
  .php        4: 0003 - 0010 (loop)
  .php        ; (lines=17, args=1, vars=1, tmps=9)
  .php   0001 T3 = FETCH_OBJ_IS THIS string("counts")
  .php   0002 T4 = FETCH_DIM_IS T3 CV0($name)
  .php   0003 T5 = COALESCE T4 0005
  .php   0004 T5 = QM_ASSIGN int(0)
  .php   0005 T6 = ADD T5 int(1)
  .php   0006 V1 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   0007 ASSIGN_DIM V1 CV0($name)
  .php   0008 OP_DATA T6
  .php   0009 T7 = FETCH_OBJ_IS THIS string("counts")
  .php   0010 T8 = FETCH_DIM_IS T7 CV0($name)
  .php   0011 T9 = COALESCE T8 0013
  .php   0012 T9 = QM_ASSIGN int(0)
  .php   0013 VERIFY_RETURN_TYPE T9
  .php   0014 RETURN T9
  .php        6: 0006 - 0007 (tmp/var)
  .php        9: 0013 - 0014 (tmp/var)
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
  .sharp      ; (lines=20, args=1, vars=2, tmps=9)
  .sharp L0019 0001 T2 = FETCH_OBJ_IS THIS string("counts")
  .sharp L0019 0002 T3 = FETCH_DIM_IS T2 CV0($status)
  .sharp L0019 0003 T4 = COALESCE T3 0005
  .sharp L0019 0004 T4 = QM_ASSIGN int(0)
  .sharp L0019 0005 T5 = ADD T4 int(1)
  .sharp L0019 0006 ASSIGN CV1($value#1) T5
  .sharp L0019 0007 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0019 0008 V7 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp L0019 0009 SEND_REF V7 1
  .sharp L0019 0010 SEND_VAR CV0($status) 2
  .sharp L0019 0011 SEND_VAR CV1($value#1) 3
  .sharp L0019 0012 DO_ICALL
  .sharp L0019 0013 V9 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp L0019 0014 V9 = SEPARATE V9
  .sharp L0020 0015 T10 = FETCH_OBJ_R THIS string("counts")
  .sharp L0020 0016 VERIFY_RETURN_TYPE T10
  .sharp L0020 0017 RETURN T10
  .sharp L0021 0018 VERIFY_RETURN_TYPE
  .sharp L0021 0019 RETURN null
  .sharp      ; (lines=42, args=2, vars=3, tmps=16)
  .sharp L0023 0002 T3 = TYPE_CHECK (array) CV0($counts)
  .sharp L0023 0003 JMPZ T3 0025
  .sharp L0023 0004 V4 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0023 0005 T5 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0023 0006 V6 = NEW 1 V4 T5
  .sharp L0023 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp L0023 0008 SEND_VAL array(...) 1
  .sharp L0023 0009 INIT_FCALL 3 128 string("array_map")
  .sharp L0023 0010 T7 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0023 0011 SEND_VAL T7 1
  .sharp L0023 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp L0023 0013 SEND_VAR CV0($counts) 1
  .sharp L0023 0014 V8 = DO_ICALL
  .sharp L0023 0015 SEND_VAR V8 2
  .sharp L0023 0016 SEND_VAR CV0($counts) 3
  .sharp L0023 0017 V9 = DO_ICALL
  .sharp L0023 0018 SEND_UNPACK V9
  .sharp L0023 0019 CHECK_UNDEF_ARGS
  .sharp L0023 0020 V10 = DO_ICALL
  .sharp L0023 0021 SEND_VAR_NO_REF_EX V10 1
  .sharp L0023 0022 DO_FCALL
  .sharp L0023 0023 T12 = QM_ASSIGN V6
  .sharp L0023 0024 JMP 0026
  .sharp L0023 0025 T12 = QM_ASSIGN CV0($counts)
  .sharp L0023 0026 ASSIGN CV0($counts) T12
  .sharp L0025 0027 T14 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp L0025 0028 T15 = COALESCE T14 0030
  .sharp L0025 0029 T15 = QM_ASSIGN int(0)
  .sharp L0025 0030 T16 = ADD T15 int(1)
  .sharp L0025 0031 ASSIGN CV2($value#1) T16
  .sharp L0025 0032 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0025 0033 SEND_REF CV0($counts) 1
  .sharp L0025 0034 SEND_VAR CV1($status) 2
  .sharp L0025 0035 SEND_VAR CV2($value#1) 3
  .sharp L0025 0036 DO_ICALL
  .sharp L0025 0037 SEPARATE CV0($counts)
  .sharp L0026 0038 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0026 0039 RETURN CV0($counts)
  .sharp L0027 0040 VERIFY_RETURN_TYPE
  .sharp L0027 0041 RETURN null
  .sharp L0023 0000 CV0($key#1) = RECV 1
  .sharp L0023 0001 CV1($value#2) = RECV 2
  .sharp L0023 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0023 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=44, args=2, vars=2, tmps=14)
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
  .sharp L0031 0027 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0031 0028 SEND_VAR CV0($counts) 1
  .sharp L0031 0029 SEND_VAR CV1($status) 2
  .sharp L0031 0030 V13 = DO_ICALL
  .sharp L0031 0031 T14 = COALESCE V13 0039
  .sharp L0031 0032 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0031 0033 SEND_REF CV0($counts) 1
  .sharp L0031 0034 SEND_VAR CV1($status) 2
  .sharp L0031 0035 SEND_VAL int(0) 3
  .sharp L0031 0036 V15 = DO_ICALL
  .sharp L0031 0037 SEPARATE CV0($counts)
  .sharp L0031 0038 T14 = QM_ASSIGN V15
  .sharp L0031 0039 FREE T14
  .sharp L0032 0040 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0032 0041 RETURN CV0($counts)
  .sharp L0033 0042 VERIFY_RETURN_TYPE
  .sharp L0033 0043 RETURN null
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
  .sharp      ; (lines=16, args=1, vars=2, tmps=6)
  .sharp L0042 0001 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0042 0002 T3 = SHARP_TYPE_ARGS string("Lib.Code, int")
  .sharp L0042 0003 V4 = NEW 0 V2 T3
  .sharp L0042 0004 DO_FCALL
  .sharp L0042 0005 ASSIGN CV1($codes) V4
  .sharp L0043 0006 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0043 0007 SEND_REF CV1($codes) 1
  .sharp L0043 0008 SEND_VAR CV0($code) 2
  .sharp L0043 0009 SEND_VAL int(5) 3
  .sharp L0043 0010 DO_ICALL
  .sharp L0043 0011 SEPARATE CV1($codes)
  .sharp L0044 0012 VERIFY_RETURN_TYPE CV1($codes)
  .sharp L0044 0013 RETURN CV1($codes)
  .sharp L0045 0014 VERIFY_RETURN_TYPE
  .sharp L0045 0015 RETURN null
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
  .sharp L0049 0027 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0049 0028 SEND_VAR CV0($counts) 1
  .sharp L0049 0029 SEND_VAR CV1($status) 2
  .sharp L0049 0030 V13 = DO_ICALL
  .sharp L0049 0031 VERIFY_RETURN_TYPE V13
  .sharp L0049 0032 RETURN V13
  .sharp L0050 0033 VERIFY_RETURN_TYPE
  .sharp L0050 0034 RETURN null
  .sharp L0047 0000 CV0($key#1) = RECV 1
  .sharp L0047 0001 CV1($value#2) = RECV 2
  .sharp L0047 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=36, args=2, vars=2, tmps=12)
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
  .sharp L0054 0027 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0054 0028 SEND_REF CV0($counts) 1
  .sharp L0054 0029 SEND_VAR CV1($status) 2
  .sharp L0054 0030 DO_ICALL
  .sharp L0054 0031 SEPARATE CV0($counts)
  .sharp L0055 0032 VERIFY_RETURN_TYPE CV0($counts)
  .sharp L0055 0033 RETURN CV0($counts)
  .sharp L0056 0034 VERIFY_RETURN_TYPE
  .sharp L0056 0035 RETURN null
  .sharp L0052 0000 CV0($key#1) = RECV 1
  .sharp L0052 0001 CV1($value#2) = RECV 2
  .sharp L0052 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0052 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=28, args=2, vars=4, tmps=12)
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
  .sharp L0062 0012 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0062 0013 SEND_REF CV3($sizes) 1
  .sharp L0062 0014 SEND_VAR CV1($size) 2
  .sharp L0062 0015 SEND_VAL int(1) 3
  .sharp L0062 0016 DO_ICALL
  .sharp L0062 0017 SEPARATE CV3($sizes)
  .sharp L0063 0018 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0063 0019 SEND_REF CV2($nested) 1
  .sharp L0063 0020 SEND_VAR CV0($status) 2
  .sharp L0063 0021 SEND_VAR CV3($sizes) 3
  .sharp L0063 0022 DO_ICALL
  .sharp L0063 0023 SEPARATE CV2($nested)
  .sharp L0064 0024 VERIFY_RETURN_TYPE CV2($nested)
  .sharp L0064 0025 RETURN CV2($nested)
  .sharp L0065 0026 VERIFY_RETURN_TYPE
  .sharp L0065 0027 RETURN null
  .sharp      ; (lines=48, args=1, vars=5, tmps=22)
  .sharp L0067 0001 T5 = TYPE_CHECK (array) CV0($counts)
  .sharp L0067 0002 JMPZ T5 0024
  .sharp L0067 0003 V6 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0067 0004 T7 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp L0067 0005 V8 = NEW 1 V6 T7
  .sharp L0067 0006 INIT_FCALL 1 96 string("array_merge")
  .sharp L0067 0007 SEND_VAL array(...) 1
  .sharp L0067 0008 INIT_FCALL 3 128 string("array_map")
  .sharp L0067 0009 T9 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0067 0010 SEND_VAL T9 1
  .sharp L0067 0011 INIT_FCALL 1 96 string("array_keys")
  .sharp L0067 0012 SEND_VAR CV0($counts) 1
  .sharp L0067 0013 V10 = DO_ICALL
  .sharp L0067 0014 SEND_VAR V10 2
  .sharp L0067 0015 SEND_VAR CV0($counts) 3
  .sharp L0067 0016 V11 = DO_ICALL
  .sharp L0067 0017 SEND_UNPACK V11
  .sharp L0067 0018 CHECK_UNDEF_ARGS
  .sharp L0067 0019 V12 = DO_ICALL
  .sharp L0067 0020 SEND_VAR_NO_REF_EX V12 1
  .sharp L0067 0021 DO_FCALL
  .sharp L0067 0022 T14 = QM_ASSIGN V8
  .sharp L0067 0023 JMP 0025
  .sharp L0067 0024 T14 = QM_ASSIGN CV0($counts)
  .sharp L0067 0025 ASSIGN CV0($counts) T14
  .sharp L0069 0026 V16 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp L0069 0027 T17 = SHARP_TYPE_ARGS string("string, int")
  .sharp L0069 0028 V18 = NEW 0 V16 T17
  .sharp L0069 0029 DO_FCALL
  .sharp L0069 0030 ASSIGN CV1($named) V18
  .sharp L0070 0031 V21 = FE_RESET_R CV0($counts) 0043
  .sharp L0070 0032 T22 = FE_FETCH_R V21 CV2($n) 0043
  .sharp L0070 0033 ASSIGN CV3($status) T22
  .sharp L0071 0034 T24 = FETCH_OBJ_R CV3($status) string("name")
  .sharp L0071 0035 ASSIGN CV4($index#1) T24
  .sharp L0071 0036 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0071 0037 SEND_REF CV1($named) 1
  .sharp L0071 0038 SEND_VAR CV4($index#1) 2
  .sharp L0071 0039 SEND_VAR CV2($n) 3
  .sharp L0071 0040 DO_ICALL
  .sharp L0071 0041 SEPARATE CV1($named)
  .sharp L0070 0042 JMP 0032
  .sharp L0070 0043 FE_FREE V21
  .sharp L0073 0044 VERIFY_RETURN_TYPE CV1($named)
  .sharp L0073 0045 RETURN CV1($named)
  .sharp L0074 0046 VERIFY_RETURN_TYPE
  .sharp L0074 0047 RETURN null
  .sharp L0067 0000 CV0($key#1) = RECV 1
  .sharp L0067 0001 CV1($value#2) = RECV 2
  .sharp L0067 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0067 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=43, args=1, vars=4, tmps=17)
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
  .sharp L0079 0027 V16 = FE_RESET_R CV0($stock) 0038
  .sharp L0079 0028 T17 = FE_FETCH_R V16 CV2($n) 0038
  .sharp L0079 0029 ASSIGN CV3($sku) T17
  .sharp L0080 0030 T19 = IS_SMALLER int(0) CV2($n)
  .sharp L0080 0031 JMPZ T19 0037
  .sharp L0081 0032 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0081 0033 SEND_REF CV1($skus) 1
  .sharp L0081 0034 SEND_VAR CV3($sku) 2
  .sharp L0081 0035 DO_ICALL
  .sharp L0081 0036 SEPARATE CV1($skus)
  .sharp L0079 0037 JMP 0028
  .sharp L0079 0038 FE_FREE V16
  .sharp L0084 0039 VERIFY_RETURN_TYPE CV1($skus)
  .sharp L0084 0040 RETURN CV1($skus)
  .sharp L0085 0041 VERIFY_RETURN_TYPE
  .sharp L0085 0042 RETURN null
  .sharp L0076 0000 CV0($key#1) = RECV 1
  .sharp L0076 0001 CV1($value#2) = RECV 2
  .sharp L0076 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0076 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=24, args=1, vars=5, tmps=9)
  .sharp L0095 0004 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0095 0005 SEND_VAL T6 2
  .sharp L0095 0007 V7 = DO_ICALL
  .sharp L0095 0008 V8 = FE_RESET_R V7 0019
  .sharp L0095 0009 T9 = FE_FETCH_R V8 CV2($group) 0019
  .sharp L0095 0010 ASSIGN CV3($status) T9
  .sharp L0096 0011 T11 = FETCH_OBJ_R CV3($status) string("name")
  .sharp L0096 0012 ASSIGN CV4($argument#1) T11
  .sharp L0096 0013 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0096 0014 SEND_REF CV1($names) 1
  .sharp L0096 0015 SEND_VAR CV4($argument#1) 2
  .sharp L0096 0016 DO_ICALL
  .sharp L0096 0017 SEPARATE CV1($names)
  .sharp L0095 0018 JMP 0009
  .sharp L0095 0019 FE_FREE V8
  .sharp L0098 0020 VERIFY_RETURN_TYPE CV1($names)
  .sharp L0098 0021 RETURN CV1($names)
  .sharp L0099 0022 VERIFY_RETURN_TYPE
  .sharp L0099 0023 RETURN null
  .sharp      ; (lines=20, args=1, vars=2, tmps=9)
  .sharp 0001 T2 = FETCH_OBJ_IS THIS string("counts")
  .sharp 0002 T3 = FETCH_DIM_IS T2 CV0($status)
  .sharp 0003 T4 = COALESCE T3 0005
  .sharp 0004 T4 = QM_ASSIGN int(0)
  .sharp 0005 T5 = ADD T4 int(1)
  .sharp 0006 ASSIGN CV1($value#1) T5
  .sharp 0007 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0008 V7 = FETCH_OBJ_W (ref) THIS string("counts")
  .sharp 0009 SEND_REF V7 1
  .sharp 0010 SEND_VAR CV0($status) 2
  .sharp 0011 SEND_VAR CV1($value#1) 3
  .sharp 0012 DO_ICALL
  .sharp 0013 V9 = FETCH_OBJ_UNSET THIS string("counts")
  .sharp 0014 V9 = SEPARATE V9
  .sharp 0015 T10 = FETCH_OBJ_R THIS string("counts")
  .sharp 0016 VERIFY_RETURN_TYPE T10
  .sharp 0017 RETURN T10
  .sharp 0018 VERIFY_RETURN_TYPE
  .sharp 0019 RETURN null
  .sharp      10: 0016 - 0017 (tmp/var)
  .sharp      ; (lines=42, args=2, vars=3, tmps=16)
  .sharp 0002 T3 = TYPE_CHECK (array) CV0($counts)
  .sharp 0003 JMPZ T3 0025
  .sharp 0004 V4 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0005 T5 = SHARP_TYPE_ARGS string("Lib.Standing, int")
  .sharp 0006 V6 = NEW 1 V4 T5
  .sharp 0007 INIT_FCALL 1 96 string("array_merge")
  .sharp 0008 SEND_VAL array(...) 1
  .sharp 0009 INIT_FCALL 3 128 string("array_map")
  .sharp 0010 T7 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0011 SEND_VAL T7 1
  .sharp 0012 INIT_FCALL 1 96 string("array_keys")
  .sharp 0013 SEND_VAR CV0($counts) 1
  .sharp 0015 SEND_VAR V8 2
  .sharp 0016 SEND_VAR CV0($counts) 3
  .sharp 0018 SEND_UNPACK V9
  .sharp 0019 CHECK_UNDEF_ARGS
  .sharp 0020 V10 = DO_ICALL
  .sharp 0021 SEND_VAR_NO_REF_EX V10 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T12 = QM_ASSIGN V6
  .sharp 0024 JMP 0026
  .sharp 0025 T12 = QM_ASSIGN CV0($counts)
  .sharp 0026 ASSIGN CV0($counts) T12
  .sharp 0027 T14 = FETCH_DIM_IS CV0($counts) CV1($status)
  .sharp 0028 T15 = COALESCE T14 0030
  .sharp 0029 T15 = QM_ASSIGN int(0)
  .sharp 0030 T16 = ADD T15 int(1)
  .sharp 0031 ASSIGN CV2($value#1) T16
  .sharp 0032 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0033 SEND_REF CV0($counts) 1
  .sharp 0034 SEND_VAR CV1($status) 2
  .sharp 0035 SEND_VAR CV2($value#1) 3
  .sharp 0036 DO_ICALL
  .sharp 0037 SEPARATE CV0($counts)
  .sharp 0038 VERIFY_RETURN_TYPE CV0($counts)
  .sharp 0039 RETURN CV0($counts)
  .sharp 0040 VERIFY_RETURN_TYPE
  .sharp 0041 RETURN null
  .sharp      6: 0007 - 0023 (new)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=44, args=2, vars=2, tmps=14)
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
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0028 SEND_VAR CV0($counts) 1
  .sharp 0029 SEND_VAR CV1($status) 2
  .sharp 0030 V13 = DO_ICALL
  .sharp 0031 T14 = COALESCE V13 0039
  .sharp 0032 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0033 SEND_REF CV0($counts) 1
  .sharp 0034 SEND_VAR CV1($status) 2
  .sharp 0035 SEND_VAL int(0) 3
  .sharp 0036 V15 = DO_ICALL
  .sharp 0037 SEPARATE CV0($counts)
  .sharp 0038 T14 = QM_ASSIGN V15
  .sharp 0039 FREE T14
  .sharp 0040 VERIFY_RETURN_TYPE CV0($counts)
  .sharp 0041 RETURN CV0($counts)
  .sharp 0042 VERIFY_RETURN_TYPE
  .sharp 0043 RETURN null
  .sharp      5: 0007 - 0023 (new)
  .sharp      15: 0037 - 0038 (tmp/var)
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
  .sharp      ; (lines=16, args=1, vars=2, tmps=6)
  .sharp 0001 V2 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0002 T3 = SHARP_TYPE_ARGS string("Lib.Code, int")
  .sharp 0003 V4 = NEW 0 V2 T3
  .sharp 0004 DO_FCALL
  .sharp 0005 ASSIGN CV1($codes) V4
  .sharp 0006 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0007 SEND_REF CV1($codes) 1
  .sharp 0008 SEND_VAR CV0($code) 2
  .sharp 0009 SEND_VAL int(5) 3
  .sharp 0010 DO_ICALL
  .sharp 0011 SEPARATE CV1($codes)
  .sharp 0012 VERIFY_RETURN_TYPE CV1($codes)
  .sharp 0013 RETURN CV1($codes)
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
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0028 SEND_VAR CV0($counts) 1
  .sharp 0029 SEND_VAR CV1($status) 2
  .sharp 0030 V13 = DO_ICALL
  .sharp 0031 VERIFY_RETURN_TYPE V13
  .sharp 0032 RETURN V13
  .sharp      5: 0007 - 0023 (new)
  .sharp      13: 0031 - 0032 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=36, args=2, vars=2, tmps=12)
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
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0028 SEND_REF CV0($counts) 1
  .sharp 0029 SEND_VAR CV1($status) 2
  .sharp 0030 DO_ICALL
  .sharp 0031 SEPARATE CV0($counts)
  .sharp 0032 VERIFY_RETURN_TYPE CV0($counts)
  .sharp 0033 RETURN CV0($counts)
  .sharp 0034 VERIFY_RETURN_TYPE
  .sharp 0035 RETURN null
  .sharp      5: 0007 - 0023 (new)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=28, args=2, vars=4, tmps=12)
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
  .sharp 0012 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0013 SEND_REF CV3($sizes) 1
  .sharp 0014 SEND_VAR CV1($size) 2
  .sharp 0015 SEND_VAL int(1) 3
  .sharp 0016 DO_ICALL
  .sharp 0017 SEPARATE CV3($sizes)
  .sharp 0018 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0019 SEND_REF CV2($nested) 1
  .sharp 0020 SEND_VAR CV0($status) 2
  .sharp 0021 SEND_VAR CV3($sizes) 3
  .sharp 0022 DO_ICALL
  .sharp 0023 SEPARATE CV2($nested)
  .sharp 0024 VERIFY_RETURN_TYPE CV2($nested)
  .sharp 0025 RETURN CV2($nested)
  .sharp 0026 VERIFY_RETURN_TYPE
  .sharp 0027 RETURN null
  .sharp      6: 0005 - 0006 (new)
  .sharp      11: 0010 - 0011 (new)
  .sharp      ; (lines=48, args=1, vars=5, tmps=22)
  .sharp 0001 T5 = TYPE_CHECK (array) CV0($counts)
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
  .sharp 0012 SEND_VAR CV0($counts) 1
  .sharp 0013 V10 = DO_ICALL
  .sharp 0014 SEND_VAR V10 2
  .sharp 0015 SEND_VAR CV0($counts) 3
  .sharp 0016 V11 = DO_ICALL
  .sharp 0017 SEND_UNPACK V11
  .sharp 0018 CHECK_UNDEF_ARGS
  .sharp 0019 V12 = DO_ICALL
  .sharp 0020 SEND_VAR_NO_REF_EX V12 1
  .sharp 0021 DO_FCALL
  .sharp 0022 T14 = QM_ASSIGN V8
  .sharp 0023 JMP 0025
  .sharp 0024 T14 = QM_ASSIGN CV0($counts)
  .sharp 0025 ASSIGN CV0($counts) T14
  .sharp 0026 V16 = FETCH_CLASS (exception) string("Sharp\\Map")
  .sharp 0027 T17 = SHARP_TYPE_ARGS string("string, int")
  .sharp 0028 V18 = NEW 0 V16 T17
  .sharp 0029 DO_FCALL
  .sharp 0030 ASSIGN CV1($named) V18
  .sharp 0031 V21 = FE_RESET_R CV0($counts) 0043
  .sharp 0032 T22 = FE_FETCH_R V21 CV2($n) 0043
  .sharp 0033 ASSIGN CV3($status) T22
  .sharp 0034 T24 = FETCH_OBJ_R CV3($status) string("name")
  .sharp 0035 ASSIGN CV4($index#1) T24
  .sharp 0036 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0037 SEND_REF CV1($named) 1
  .sharp 0038 SEND_VAR CV4($index#1) 2
  .sharp 0039 SEND_VAR CV2($n) 3
  .sharp 0040 DO_ICALL
  .sharp 0041 SEPARATE CV1($named)
  .sharp 0042 JMP 0032
  .sharp 0043 FE_FREE V21
  .sharp 0044 VERIFY_RETURN_TYPE CV1($named)
  .sharp 0045 RETURN CV1($named)
  .sharp 0046 VERIFY_RETURN_TYPE
  .sharp 0047 RETURN null
  .sharp      8: 0006 - 0022 (new)
  .sharp      18: 0029 - 0030 (new)
  .sharp      21: 0032 - 0043 (loop)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=43, args=1, vars=4, tmps=17)
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
  .sharp 0027 V16 = FE_RESET_R CV0($stock) 0038
  .sharp 0028 T17 = FE_FETCH_R V16 CV2($n) 0038
  .sharp 0029 ASSIGN CV3($sku) T17
  .sharp 0030 T19 = IS_SMALLER int(0) CV2($n)
  .sharp 0031 JMPZ T19 0037
  .sharp 0032 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0033 SEND_REF CV1($skus) 1
  .sharp 0034 SEND_VAR CV3($sku) 2
  .sharp 0035 DO_ICALL
  .sharp 0036 SEPARATE CV1($skus)
  .sharp 0037 JMP 0028
  .sharp 0038 FE_FREE V16
  .sharp 0039 VERIFY_RETURN_TYPE CV1($skus)
  .sharp 0040 RETURN CV1($skus)
  .sharp 0041 VERIFY_RETURN_TYPE
  .sharp 0042 RETURN null
  .sharp      7: 0006 - 0022 (new)
  .sharp      16: 0028 - 0038 (loop)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=24, args=1, vars=5, tmps=9)
  .sharp 0004 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0005 SEND_VAL T6 2
  .sharp 0007 V7 = DO_ICALL
  .sharp 0008 V8 = FE_RESET_R V7 0019
  .sharp 0009 T9 = FE_FETCH_R V8 CV2($group) 0019
  .sharp 0010 ASSIGN CV3($status) T9
  .sharp 0011 T11 = FETCH_OBJ_R CV3($status) string("name")
  .sharp 0012 ASSIGN CV4($argument#1) T11
  .sharp 0013 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0014 SEND_REF CV1($names) 1
  .sharp 0015 SEND_VAR CV4($argument#1) 2
  .sharp 0016 DO_ICALL
  .sharp 0017 SEPARATE CV1($names)
  .sharp 0018 JMP 0009
  .sharp 0019 FE_FREE V8
  .sharp 0020 VERIFY_RETURN_TYPE CV1($names)
  .sharp 0021 RETURN CV1($names)
  .sharp 0022 VERIFY_RETURN_TYPE
  .sharp 0023 RETURN null
  .sharp      8: 0009 - 0019 (loop)
  .sharp     Property [ private Sharp\Map $counts = Closure({closure:<file>:9})() ]
  .sharp     Property [ private Sharp\Map $seeded = Closure({closure:<file>:10})() ]
  .php        ; (lines=14, args=1, vars=1, tmps=7)
  .php   L0019 0001 T3 = FETCH_OBJ_IS THIS string("counts")
  .php   L0019 0002 T4 = FETCH_DIM_IS T3 CV0($status)
  .php   L0019 0003 T5 = COALESCE T4 0005
  .php   L0019 0004 T5 = QM_ASSIGN int(0)
  .php   L0019 0005 T6 = ADD T5 int(1)
  .php   L0019 0006 V1 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   L0019 0007 ASSIGN_DIM V1 CV0($status)
  .php   L0019 0008 OP_DATA T6
  .php   L0020 0009 T7 = FETCH_OBJ_R THIS string("counts")
  .php   L0020 0010 VERIFY_RETURN_TYPE T7
  .php   L0020 0011 RETURN T7
  .php   L0021 0012 VERIFY_RETURN_TYPE
  .php   L0021 0013 RETURN null
  .php        ; (lines=35, args=2, vars=2, tmps=13)
  .php   L0023 0002 T2 = TYPE_CHECK (array) CV0($counts)
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
  .php        ; (lines=33, args=2, vars=2, tmps=10)
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
  .php   L0049 0025 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0049 0026 SEND_VAR CV0($counts) 1
  .php   L0049 0027 SEND_VAR CV1($status) 2
  .php   L0049 0028 V11 = DO_ICALL
  .php   L0049 0029 VERIFY_RETURN_TYPE V11
  .php   L0049 0030 RETURN V11
  .php   L0050 0031 VERIFY_RETURN_TYPE
  .php   L0050 0032 RETURN null
  .php   L0047 0000 CV0($key) = RECV 1
  .php   L0047 0001 CV1($value) = RECV 2
  .php   L0047 0003 T2 = CAST (string) CV0($key)
  .php   L0047 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=33, args=2, vars=2, tmps=10)
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
  .php   L0054 0025 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0054 0026 SEND_REF CV0($counts) 1
  .php   L0054 0027 SEND_VAR CV1($status) 2
  .php   L0054 0028 DO_ICALL
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
  .php   L0067 0001 T4 = TYPE_CHECK (array) CV0($counts)
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
  .php        ; (lines=40, args=1, vars=4, tmps=15)
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
  .php   L0081 0030 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0081 0031 SEND_REF CV1($skus) 1
  .php   L0081 0032 SEND_VAR CV3($sku) 2
  .php   L0081 0033 DO_ICALL
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
  .php        ; (lines=22, args=1, vars=4, tmps=8)
  .php   L0095 0004 T5 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0095 0005 SEND_VAL T5 2
  .php   L0095 0007 V6 = DO_ICALL
  .php   L0095 0008 V7 = FE_RESET_R V6 0017
  .php   L0095 0009 T8 = FE_FETCH_R V7 CV2($group) 0017
  .php   L0095 0010 ASSIGN CV3($status) T8
  .php   L0096 0011 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0096 0012 SEND_REF CV1($names) 1
  .php   L0096 0013 T10 = FETCH_OBJ_R CV3($status) string("name")
  .php   L0096 0014 SEND_VAL T10 2
  .php   L0096 0015 DO_ICALL
  .php   L0095 0016 JMP 0009
  .php   L0095 0017 FE_FREE V7
  .php   L0098 0018 VERIFY_RETURN_TYPE CV1($names)
  .php   L0098 0019 RETURN CV1($names)
  .php   L0099 0020 VERIFY_RETURN_TYPE
  .php   L0099 0021 RETURN null
  .php        ; (lines=14, args=1, vars=1, tmps=7)
  .php   0001 T3 = FETCH_OBJ_IS THIS string("counts")
  .php   0002 T4 = FETCH_DIM_IS T3 CV0($status)
  .php   0003 T5 = COALESCE T4 0005
  .php   0004 T5 = QM_ASSIGN int(0)
  .php   0005 T6 = ADD T5 int(1)
  .php   0006 V1 = FETCH_OBJ_W (dim write) THIS string("counts")
  .php   0007 ASSIGN_DIM V1 CV0($status)
  .php   0008 OP_DATA T6
  .php   0009 T7 = FETCH_OBJ_R THIS string("counts")
  .php   0010 VERIFY_RETURN_TYPE T7
  .php   0011 RETURN T7
  .php   0012 VERIFY_RETURN_TYPE
  .php   0013 RETURN null
  .php        6: 0006 - 0007 (tmp/var)
  .php        7: 0010 - 0011 (tmp/var)
  .php        ; (lines=35, args=2, vars=2, tmps=13)
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
  .php   0031 VERIFY_RETURN_TYPE CV0($counts)
  .php   0032 RETURN CV0($counts)
  .php        3: 0005 - 0021 (new)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
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
  .php   0031 VERIFY_RETURN_TYPE CV0($counts)
  .php   0032 RETURN CV0($counts)
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
  .php        ; (lines=33, args=2, vars=2, tmps=10)
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
  .php   0025 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0026 SEND_VAR CV0($counts) 1
  .php   0027 SEND_VAR CV1($status) 2
  .php   0028 V11 = DO_ICALL
  .php   0029 VERIFY_RETURN_TYPE V11
  .php   0030 RETURN V11
  .php   0031 VERIFY_RETURN_TYPE
  .php   0032 RETURN null
  .php        3: 0005 - 0021 (new)
  .php        11: 0029 - 0030 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0003 T2 = CAST (string) CV0($key)
  .php   0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=33, args=2, vars=2, tmps=10)
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
  .php   0025 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0026 SEND_REF CV0($counts) 1
  .php   0027 SEND_VAR CV1($status) 2
  .php   0028 DO_ICALL
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
  .php        4: 0003 - 0004 (new)
  .php        7: 0006 - 0007 (new)
  .php        ; (lines=39, args=1, vars=4, tmps=17)
  .php   0001 T4 = TYPE_CHECK (array) CV0($counts)
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
  .php   0015 SEND_UNPACK V8
  .php   0016 CHECK_UNDEF_ARGS
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
  .php        ; (lines=40, args=1, vars=4, tmps=15)
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
  .php   0015 SEND_UNPACK V8
  .php   0016 CHECK_UNDEF_ARGS
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
  .php   0030 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0031 SEND_REF CV1($skus) 1
  .php   0032 SEND_VAR CV3($sku) 2
  .php   0033 DO_ICALL
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
  .php        ; (lines=22, args=1, vars=4, tmps=8)
  .php   0004 T5 = DECLARE_LAMBDA_FUNCTION 0
  .php   0005 SEND_VAL T5 2
  .php   0007 V6 = DO_ICALL
  .php   0008 V7 = FE_RESET_R V6 0017
  .php   0009 T8 = FE_FETCH_R V7 CV2($group) 0017
  .php   0010 ASSIGN CV3($status) T8
  .php   0011 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0012 SEND_REF CV1($names) 1
  .php   0013 T10 = FETCH_OBJ_R CV3($status) string("name")
  .php   0014 SEND_VAL T10 2
  .php   0015 DO_ICALL
  .php   0016 JMP 0009
  .php   0017 FE_FREE V7
  .php   0018 VERIFY_RETURN_TYPE CV1($names)
  .php   0019 RETURN CV1($names)
  .php   0020 VERIFY_RETURN_TYPE
  .php   0021 RETURN null
  .php        7: 0009 - 0017 (loop)
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
  .sharp      ; (lines=35, args=1, vars=4, tmps=14)
  .sharp L0033 0004 JMP 0020
  .sharp L0039 0018 SEPARATE CV1($marks)
  .sharp L0040 0019 ASSIGN_OP (SUB) CV0($times) int(1)
  .sharp L0033 0020 T14 = IS_SMALLER int(0) CV0($times)
  .sharp L0033 0021 JMPNZ T14 0005
  .sharp L0042 0022 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0042 0023 SEND_VAL_EX string(",") 1
  .sharp L0042 0024 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0042 0025 SEND_VAR CV1($marks) 1
  .sharp L0042 0026 T15 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0042 0027 SEND_VAL T15 2
  .sharp L0042 0028 V16 = DO_ICALL
  .sharp L0042 0029 SEND_VAR_NO_REF_EX V16 2
  .sharp L0042 0030 V17 = DO_FCALL
  .sharp L0042 0031 VERIFY_RETURN_TYPE V17
  .sharp L0042 0032 RETURN V17
  .sharp L0043 0033 VERIFY_RETURN_TYPE
  .sharp L0043 0034 RETURN null
  .sharp      ; (lines=124, args=2, vars=5, tmps=43)
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
  .sharp L0047 0027 INIT_FCALL 2 112 string("sharp\\internal\\filter")
  .sharp L0047 0028 SEND_VAR CV0($numbers) 1
  .sharp L0047 0029 T16 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0047 0030 SEND_VAL T16 2
  .sharp L0047 0031 V17 = DO_ICALL
  .sharp L0047 0032 ASSIGN CV2($odd) V17
  .sharp L0048 0033 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0048 0034 SEND_VAR CV2($odd) 1
  .sharp L0048 0035 T19 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0048 0036 SEND_VAL T19 2
  .sharp L0048 0037 V20 = DO_ICALL
  .sharp L0048 0038 ASSIGN CV3($labels) V20
  .sharp L0049 0039 INIT_FCALL 3 128 string("sharp\\internal\\groupby")
  .sharp L0049 0040 SEND_VAR CV0($numbers) 1
  .sharp L0049 0041 T22 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0049 0042 SEND_VAL T22 2
  .sharp L0049 0043 SEND_VAL string("string, List<int>") 3
  .sharp L0049 0044 V23 = DO_ICALL
  .sharp L0049 0045 ASSIGN CV4($groups) V23
  .sharp L0051 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0051 0047 SEND_VAL_EX string(",") 1
  .sharp L0051 0048 SEND_VAR_EX CV3($labels) 2
  .sharp L0051 0049 V25 = DO_FCALL
  .sharp L0051 0050 T26 = INIT_ARRAY 7 (packed) V25 NEXT
  .sharp L0052 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0052 0052 SEND_VAL_EX string(",") 1
  .sharp L0052 0053 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp L0052 0054 SEND_VAR CV0($numbers) 1
  .sharp L0052 0055 T27 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0052 0056 SEND_VAL T27 2
  .sharp L0052 0057 SEND_VAL null 3
  .sharp L0052 0058 V28 = DO_ICALL
  .sharp L0052 0059 SEND_VAR_NO_REF_EX V28 2
  .sharp L0052 0060 V29 = DO_FCALL
  .sharp L0052 0061 T26 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp L0053 0062 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0053 0063 SEND_VAL_EX string(",") 1
  .sharp L0053 0064 T30 = FETCH_DIM_IS CV4($groups) string("odd")
  .sharp L0053 0065 T31 = COALESCE T30 0067
  .sharp L0053 0066 T31 = QM_ASSIGN array(...)
  .sharp L0053 0067 SEND_VAL_EX T31 2
  .sharp L0053 0068 V32 = DO_FCALL
  .sharp L0053 0069 T26 = ADD_ARRAY_ELEMENT V32 NEXT
  .sharp L0054 0070 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0054 0071 SEND_VAL_EX string(",") 1
  .sharp L0054 0072 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0054 0073 INIT_FCALL 3 128 string("sharp\\internal\\associateby")
  .sharp L0054 0074 SEND_VAR CV3($labels) 1
  .sharp L0054 0075 T33 = DECLARE_LAMBDA_FUNCTION 5
  .sharp L0054 0076 SEND_VAL T33 2
  .sharp L0054 0077 SEND_VAL string("string, string") 3
  .sharp L0054 0078 V34 = DO_ICALL
  .sharp L0054 0079 SEND_VAR V34 1
  .sharp L0054 0080 V35 = DO_ICALL
  .sharp L0054 0081 SEND_VAR_NO_REF_EX V35 2
  .sharp L0054 0082 V36 = DO_FCALL
  .sharp L0054 0083 T26 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp L0055 0084 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0055 0085 SEND_VAL_EX string(",") 1
  .sharp L0055 0086 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0055 0087 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp L0055 0088 SEND_VAR CV1($prices) 1
  .sharp L0055 0089 T37 = DECLARE_LAMBDA_FUNCTION 6
  .sharp L0055 0090 SEND_VAL T37 2
  .sharp L0055 0091 V38 = DO_ICALL
  .sharp L0055 0092 SEND_VAR V38 1
  .sharp L0055 0093 V39 = DO_ICALL
  .sharp L0055 0094 SEND_VAR_NO_REF_EX V39 2
  .sharp L0055 0095 V40 = DO_FCALL
  .sharp L0055 0096 T26 = ADD_ARRAY_ELEMENT V40 NEXT
  .sharp L0056 0097 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0056 0098 SEND_VAL_EX string(",") 1
  .sharp L0056 0099 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp L0056 0100 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp L0056 0101 SEND_VAR CV1($prices) 1
  .sharp L0056 0102 T41 = DECLARE_LAMBDA_FUNCTION 7
  .sharp L0056 0103 SEND_VAL T41 2
  .sharp L0056 0104 V42 = DO_ICALL
  .sharp L0056 0105 SEND_VAR V42 1
  .sharp L0056 0106 V43 = DO_ICALL
  .sharp L0056 0107 SEND_VAR_NO_REF_EX V43 2
  .sharp L0056 0108 V44 = DO_FCALL
  .sharp L0056 0109 T26 = ADD_ARRAY_ELEMENT V44 NEXT
  .sharp L0057 0110 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0057 0111 SEND_VAL_EX string(",") 1
  .sharp L0057 0112 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0057 0113 SEND_VAR CV1($prices) 1
  .sharp L0057 0114 T45 = DECLARE_LAMBDA_FUNCTION 8
  .sharp L0057 0115 SEND_VAL T45 2
  .sharp L0057 0116 V46 = DO_ICALL
  .sharp L0057 0117 SEND_VAR_NO_REF_EX V46 2
  .sharp L0057 0118 V47 = DO_FCALL
  .sharp L0057 0119 T26 = ADD_ARRAY_ELEMENT V47 NEXT
  .sharp L0057 0120 VERIFY_RETURN_TYPE T26
  .sharp L0057 0121 RETURN T26
  .sharp L0059 0122 VERIFY_RETURN_TYPE
  .sharp L0059 0123 RETURN null
  .sharp L0045 0000 CV0($key#1) = RECV 1
  .sharp L0045 0001 CV1($value#2) = RECV 2
  .sharp L0045 0002 T2 = CAST (string) CV0($key#1)
  .sharp L0045 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=36, args=1, vars=5, tmps=11)
  .sharp L0067 0004 T6 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0067 0005 SEND_VAL T6 2
  .sharp L0067 0007 JMP 0017
  .sharp L0069 0009 INIT_METHOD_CALL 0 CV2($missing) string("getMessage")
  .sharp L0069 0010 V8 = DO_FCALL
  .sharp L0069 0011 ASSIGN CV3($argument#1) V8
  .sharp L0069 0012 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0069 0013 SEND_REF CV1($messages) 1
  .sharp L0069 0014 SEND_VAR CV3($argument#1) 2
  .sharp L0069 0015 DO_ICALL
  .sharp L0069 0016 SEPARATE CV1($messages)
  .sharp L0072 0017 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0072 0018 SEND_VAR CV0($numbers) 1
  .sharp L0072 0019 T11 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0072 0020 SEND_VAL T11 2
  .sharp L0072 0021 DO_ICALL
  .sharp L0072 0022 JMP 0032
  .sharp L0073 0023 CV4($stopped) = CATCH string("OutOfRangeException")
  .sharp L0074 0024 INIT_METHOD_CALL 0 CV4($stopped) string("getMessage")
  .sharp L0074 0025 V13 = DO_FCALL
  .sharp L0074 0026 ASSIGN CV3($argument#1) V13
  .sharp L0074 0027 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0074 0028 SEND_REF CV1($messages) 1
  .sharp L0074 0029 SEND_VAR CV3($argument#1) 2
  .sharp L0074 0030 DO_ICALL
  .sharp L0074 0031 SEPARATE CV1($messages)
  .sharp L0076 0032 VERIFY_RETURN_TYPE CV1($messages)
  .sharp L0076 0033 RETURN CV1($messages)
  .sharp L0077 0034 VERIFY_RETURN_TYPE
  .sharp L0077 0035 RETURN null
  .sharp      0017, 0023, -, -
  .sharp      ; (lines=29, args=1, vars=5, tmps=12)
  .sharp L0088 0018 SEPARATE CV1($seen)
  .sharp L0089 0019 T14 = COUNT CV2($kept)
  .sharp L0089 0020 T15 = INIT_ARRAY 3 (packed) T14 NEXT
  .sharp L0089 0021 T15 = ADD_ARRAY_ELEMENT CV3($grown) NEXT
  .sharp L0089 0022 INIT_DYNAMIC_CALL 0 CV4($size)
  .sharp L0089 0023 V16 = DO_FCALL
  .sharp L0089 0024 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp L0089 0025 VERIFY_RETURN_TYPE T15
  .sharp L0089 0026 RETURN T15
  .sharp L0090 0027 VERIFY_RETURN_TYPE
  .sharp L0090 0028 RETURN null
  .sharp      ; (lines=9, args=1, vars=2, tmps=1)
  .sharp L0083 0006 SEPARATE CV1($seen)
  .sharp L0084 0007 RETURN bool(true)
  .sharp L0085 0008 RETURN null
  .sharp      ; (lines=35, args=1, vars=4, tmps=14)
  .sharp 0004 JMP 0020
  .sharp 0018 SEPARATE CV1($marks)
  .sharp 0019 ASSIGN_OP (SUB) CV0($times) int(1)
  .sharp 0020 T14 = IS_SMALLER int(0) CV0($times)
  .sharp 0021 JMPNZ T14 0005
  .sharp 0022 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0023 SEND_VAL_EX string(",") 1
  .sharp 0024 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0025 SEND_VAR CV1($marks) 1
  .sharp 0026 T15 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0027 SEND_VAL T15 2
  .sharp 0028 V16 = DO_ICALL
  .sharp 0029 SEND_VAR_NO_REF_EX V16 2
  .sharp 0030 V17 = DO_FCALL
  .sharp 0031 VERIFY_RETURN_TYPE V17
  .sharp 0032 RETURN V17
  .sharp 0033 VERIFY_RETURN_TYPE
  .sharp 0034 RETURN null
  .sharp      17: 0031 - 0032 (tmp/var)
  .sharp      ; (lines=124, args=2, vars=5, tmps=43)
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
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\filter")
  .sharp 0028 SEND_VAR CV0($numbers) 1
  .sharp 0029 T16 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0030 SEND_VAL T16 2
  .sharp 0031 V17 = DO_ICALL
  .sharp 0032 ASSIGN CV2($odd) V17
  .sharp 0033 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0034 SEND_VAR CV2($odd) 1
  .sharp 0035 T19 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0036 SEND_VAL T19 2
  .sharp 0037 V20 = DO_ICALL
  .sharp 0038 ASSIGN CV3($labels) V20
  .sharp 0039 INIT_FCALL 3 128 string("sharp\\internal\\groupby")
  .sharp 0040 SEND_VAR CV0($numbers) 1
  .sharp 0041 T22 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0042 SEND_VAL T22 2
  .sharp 0043 SEND_VAL string("string, List<int>") 3
  .sharp 0044 V23 = DO_ICALL
  .sharp 0045 ASSIGN CV4($groups) V23
  .sharp 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0047 SEND_VAL_EX string(",") 1
  .sharp 0048 SEND_VAR_EX CV3($labels) 2
  .sharp 0049 V25 = DO_FCALL
  .sharp 0050 T26 = INIT_ARRAY 7 (packed) V25 NEXT
  .sharp 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0052 SEND_VAL_EX string(",") 1
  .sharp 0053 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp 0054 SEND_VAR CV0($numbers) 1
  .sharp 0055 T27 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0056 SEND_VAL T27 2
  .sharp 0057 SEND_VAL null 3
  .sharp 0058 V28 = DO_ICALL
  .sharp 0059 SEND_VAR_NO_REF_EX V28 2
  .sharp 0060 V29 = DO_FCALL
  .sharp 0061 T26 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp 0062 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0063 SEND_VAL_EX string(",") 1
  .sharp 0064 T30 = FETCH_DIM_IS CV4($groups) string("odd")
  .sharp 0065 T31 = COALESCE T30 0067
  .sharp 0066 T31 = QM_ASSIGN array(...)
  .sharp 0067 SEND_VAL_EX T31 2
  .sharp 0068 V32 = DO_FCALL
  .sharp 0069 T26 = ADD_ARRAY_ELEMENT V32 NEXT
  .sharp 0070 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0071 SEND_VAL_EX string(",") 1
  .sharp 0072 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0073 INIT_FCALL 3 128 string("sharp\\internal\\associateby")
  .sharp 0074 SEND_VAR CV3($labels) 1
  .sharp 0075 T33 = DECLARE_LAMBDA_FUNCTION 5
  .sharp 0076 SEND_VAL T33 2
  .sharp 0077 SEND_VAL string("string, string") 3
  .sharp 0078 V34 = DO_ICALL
  .sharp 0079 SEND_VAR V34 1
  .sharp 0080 V35 = DO_ICALL
  .sharp 0081 SEND_VAR_NO_REF_EX V35 2
  .sharp 0082 V36 = DO_FCALL
  .sharp 0083 T26 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp 0084 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0085 SEND_VAL_EX string(",") 1
  .sharp 0086 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0087 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp 0088 SEND_VAR CV1($prices) 1
  .sharp 0089 T37 = DECLARE_LAMBDA_FUNCTION 6
  .sharp 0090 SEND_VAL T37 2
  .sharp 0091 V38 = DO_ICALL
  .sharp 0092 SEND_VAR V38 1
  .sharp 0093 V39 = DO_ICALL
  .sharp 0094 SEND_VAR_NO_REF_EX V39 2
  .sharp 0095 V40 = DO_FCALL
  .sharp 0096 T26 = ADD_ARRAY_ELEMENT V40 NEXT
  .sharp 0097 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0098 SEND_VAL_EX string(",") 1
  .sharp 0099 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp 0100 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp 0101 SEND_VAR CV1($prices) 1
  .sharp 0102 T41 = DECLARE_LAMBDA_FUNCTION 7
  .sharp 0103 SEND_VAL T41 2
  .sharp 0104 V42 = DO_ICALL
  .sharp 0105 SEND_VAR V42 1
  .sharp 0106 V43 = DO_ICALL
  .sharp 0107 SEND_VAR_NO_REF_EX V43 2
  .sharp 0108 V44 = DO_FCALL
  .sharp 0109 T26 = ADD_ARRAY_ELEMENT V44 NEXT
  .sharp 0110 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0111 SEND_VAL_EX string(",") 1
  .sharp 0112 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0113 SEND_VAR CV1($prices) 1
  .sharp 0114 T45 = DECLARE_LAMBDA_FUNCTION 8
  .sharp 0115 SEND_VAL T45 2
  .sharp 0116 V46 = DO_ICALL
  .sharp 0117 SEND_VAR_NO_REF_EX V46 2
  .sharp 0118 V47 = DO_FCALL
  .sharp 0119 T26 = ADD_ARRAY_ELEMENT V47 NEXT
  .sharp 0120 VERIFY_RETURN_TYPE T26
  .sharp 0121 RETURN T26
  .sharp 0122 VERIFY_RETURN_TYPE
  .sharp 0123 RETURN null
  .sharp      8: 0007 - 0023 (new)
  .sharp      26: 0051 - 0121 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=36, args=1, vars=5, tmps=11)
  .sharp 0005 SEND_VAL T6 2
  .sharp 0007 JMP 0017
  .sharp 0009 INIT_METHOD_CALL 0 CV2($missing) string("getMessage")
  .sharp 0010 V8 = DO_FCALL
  .sharp 0011 ASSIGN CV3($argument#1) V8
  .sharp 0013 SEND_REF CV1($messages) 1
  .sharp 0014 SEND_VAR CV3($argument#1) 2
  .sharp 0015 DO_ICALL
  .sharp 0016 SEPARATE CV1($messages)
  .sharp 0017 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0018 SEND_VAR CV0($numbers) 1
  .sharp 0019 T11 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0020 SEND_VAL T11 2
  .sharp 0021 DO_ICALL
  .sharp 0022 JMP 0032
  .sharp 0023 CV4($stopped) = CATCH string("OutOfRangeException")
  .sharp 0024 INIT_METHOD_CALL 0 CV4($stopped) string("getMessage")
  .sharp 0025 V13 = DO_FCALL
  .sharp 0026 ASSIGN CV3($argument#1) V13
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0028 SEND_REF CV1($messages) 1
  .sharp 0029 SEND_VAR CV3($argument#1) 2
  .sharp 0030 DO_ICALL
  .sharp 0031 SEPARATE CV1($messages)
  .sharp 0032 VERIFY_RETURN_TYPE CV1($messages)
  .sharp 0033 RETURN CV1($messages)
  .sharp 0034 VERIFY_RETURN_TYPE
  .sharp 0035 RETURN null
  .sharp      0017, 0023, -, -
  .sharp      ; (lines=29, args=1, vars=5, tmps=12)
  .sharp 0018 SEPARATE CV1($seen)
  .sharp 0019 T14 = COUNT CV2($kept)
  .sharp 0020 T15 = INIT_ARRAY 3 (packed) T14 NEXT
  .sharp 0021 T15 = ADD_ARRAY_ELEMENT CV3($grown) NEXT
  .sharp 0022 INIT_DYNAMIC_CALL 0 CV4($size)
  .sharp 0023 V16 = DO_FCALL
  .sharp 0024 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp 0025 VERIFY_RETURN_TYPE T15
  .sharp 0026 RETURN T15
  .sharp      15: 0021 - 0026 (tmp/var)
  .sharp      ; (lines=9, args=1, vars=2, tmps=1)
  .sharp 0006 SEPARATE CV1($seen)
  .sharp 0007 RETURN bool(true)
  .php        ; (lines=34, args=1, vars=4, tmps=14)
  .php   L0033 0004 JMP 0019
  .php   L0040 0018 ASSIGN_OP (SUB) CV0($times) int(1)
  .php   L0033 0019 T14 = IS_SMALLER int(0) CV0($times)
  .php   L0033 0020 JMPNZ T14 0005
  .php   L0042 0021 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0042 0022 SEND_VAL_EX string(",") 1
  .php   L0042 0023 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0042 0024 SEND_VAR CV1($marks) 1
  .php   L0042 0025 T15 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0042 0026 SEND_VAL T15 2
  .php   L0042 0027 V16 = DO_ICALL
  .php   L0042 0028 SEND_VAR_NO_REF_EX V16 2
  .php   L0042 0029 V17 = DO_FCALL
  .php   L0042 0030 VERIFY_RETURN_TYPE V17
  .php   L0042 0031 RETURN V17
  .php   L0043 0032 VERIFY_RETURN_TYPE
  .php   L0043 0033 RETURN null
  .php        ; (lines=122, args=2, vars=5, tmps=41)
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
  .php   L0047 0025 INIT_FCALL 2 112 string("sharp\\internal\\filter")
  .php   L0047 0026 SEND_VAR CV0($numbers) 1
  .php   L0047 0027 T14 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0047 0028 SEND_VAL T14 2
  .php   L0047 0029 V15 = DO_ICALL
  .php   L0047 0030 ASSIGN CV2($odd) V15
  .php   L0048 0031 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0048 0032 SEND_VAR CV2($odd) 1
  .php   L0048 0033 T17 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0048 0034 SEND_VAL T17 2
  .php   L0048 0035 V18 = DO_ICALL
  .php   L0048 0036 ASSIGN CV3($labels) V18
  .php   L0049 0037 INIT_FCALL 3 128 string("sharp\\internal\\groupby")
  .php   L0049 0038 SEND_VAR CV0($numbers) 1
  .php   L0049 0039 T20 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0049 0040 SEND_VAL T20 2
  .php   L0049 0041 SEND_VAL string("string, List<int>") 3
  .php   L0049 0042 V21 = DO_ICALL
  .php   L0049 0043 ASSIGN CV4($groups) V21
  .php   L0051 0044 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0051 0045 SEND_VAL_EX string(",") 1
  .php   L0051 0046 SEND_VAR_EX CV3($labels) 2
  .php   L0051 0047 V23 = DO_FCALL
  .php   L0051 0048 T24 = INIT_ARRAY 7 (packed) V23 NEXT
  .php   L0052 0049 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0052 0050 SEND_VAL_EX string(",") 1
  .php   L0052 0051 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   L0052 0052 SEND_VAR CV0($numbers) 1
  .php   L0052 0053 T25 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0052 0054 SEND_VAL T25 2
  .php   L0052 0055 SEND_VAL null 3
  .php   L0052 0056 V26 = DO_ICALL
  .php   L0052 0057 SEND_VAR_NO_REF_EX V26 2
  .php   L0052 0058 V27 = DO_FCALL
  .php   L0052 0059 T24 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   L0053 0060 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0053 0061 SEND_VAL_EX string(",") 1
  .php   L0053 0062 T28 = FETCH_DIM_IS CV4($groups) string("odd")
  .php   L0053 0063 T29 = COALESCE T28 0065
  .php   L0053 0064 T29 = QM_ASSIGN array(...)
  .php   L0053 0065 SEND_VAL_EX T29 2
  .php   L0053 0066 V30 = DO_FCALL
  .php   L0053 0067 T24 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   L0054 0068 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0054 0069 SEND_VAL_EX string(",") 1
  .php   L0054 0070 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0054 0071 INIT_FCALL 3 128 string("sharp\\internal\\associateby")
  .php   L0054 0072 SEND_VAR CV3($labels) 1
  .php   L0054 0073 T31 = DECLARE_LAMBDA_FUNCTION 5
  .php   L0054 0074 SEND_VAL T31 2
  .php   L0054 0075 SEND_VAL string("string, string") 3
  .php   L0054 0076 V32 = DO_ICALL
  .php   L0054 0077 SEND_VAR V32 1
  .php   L0054 0078 V33 = DO_ICALL
  .php   L0054 0079 SEND_VAR_NO_REF_EX V33 2
  .php   L0054 0080 V34 = DO_FCALL
  .php   L0054 0081 T24 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   L0055 0082 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0055 0083 SEND_VAL_EX string(",") 1
  .php   L0055 0084 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0055 0085 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   L0055 0086 SEND_VAR CV1($prices) 1
  .php   L0055 0087 T35 = DECLARE_LAMBDA_FUNCTION 6
  .php   L0055 0088 SEND_VAL T35 2
  .php   L0055 0089 V36 = DO_ICALL
  .php   L0055 0090 SEND_VAR V36 1
  .php   L0055 0091 V37 = DO_ICALL
  .php   L0055 0092 SEND_VAR_NO_REF_EX V37 2
  .php   L0055 0093 V38 = DO_FCALL
  .php   L0055 0094 T24 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   L0056 0095 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0056 0096 SEND_VAL_EX string(",") 1
  .php   L0056 0097 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   L0056 0098 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   L0056 0099 SEND_VAR CV1($prices) 1
  .php   L0056 0100 T39 = DECLARE_LAMBDA_FUNCTION 7
  .php   L0056 0101 SEND_VAL T39 2
  .php   L0056 0102 V40 = DO_ICALL
  .php   L0056 0103 SEND_VAR V40 1
  .php   L0056 0104 V41 = DO_ICALL
  .php   L0056 0105 SEND_VAR_NO_REF_EX V41 2
  .php   L0056 0106 V42 = DO_FCALL
  .php   L0056 0107 T24 = ADD_ARRAY_ELEMENT V42 NEXT
  .php   L0057 0108 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0057 0109 SEND_VAL_EX string(",") 1
  .php   L0057 0110 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0057 0111 SEND_VAR CV1($prices) 1
  .php   L0057 0112 T43 = DECLARE_LAMBDA_FUNCTION 8
  .php   L0057 0113 SEND_VAL T43 2
  .php   L0057 0114 V44 = DO_ICALL
  .php   L0057 0115 SEND_VAR_NO_REF_EX V44 2
  .php   L0057 0116 V45 = DO_FCALL
  .php   L0057 0117 T24 = ADD_ARRAY_ELEMENT V45 NEXT
  .php   L0057 0118 VERIFY_RETURN_TYPE T24
  .php   L0057 0119 RETURN T24
  .php   L0059 0120 VERIFY_RETURN_TYPE
  .php   L0059 0121 RETURN null
  .php   L0045 0000 CV0($key) = RECV 1
  .php   L0045 0001 CV1($value) = RECV 2
  .php   L0045 0002 T2 = CAST (string) CV0($key)
  .php   L0045 0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=32, args=1, vars=4, tmps=9)
  .php   L0067 0004 T5 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0067 0005 SEND_VAL T5 2
  .php   L0067 0007 JMP 0015
  .php   L0069 0009 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0069 0010 SEND_REF CV1($messages) 1
  .php   L0069 0011 INIT_METHOD_CALL 0 CV2($missing) string("getMessage")
  .php   L0069 0012 V7 = DO_FCALL
  .php   L0069 0013 SEND_VAR V7 2
  .php   L0069 0014 DO_ICALL
  .php   L0072 0015 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0072 0016 SEND_VAR CV0($numbers) 1
  .php   L0072 0017 T9 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0072 0018 SEND_VAL T9 2
  .php   L0072 0019 DO_ICALL
  .php   L0072 0020 JMP 0028
  .php   L0073 0021 CV3($stopped) = CATCH string("OutOfRangeException")
  .php   L0074 0022 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0074 0023 SEND_REF CV1($messages) 1
  .php   L0074 0024 INIT_METHOD_CALL 0 CV3($stopped) string("getMessage")
  .php   L0074 0025 V11 = DO_FCALL
  .php   L0074 0026 SEND_VAR V11 2
  .php   L0074 0027 DO_ICALL
  .php   L0076 0028 VERIFY_RETURN_TYPE CV1($messages)
  .php   L0076 0029 RETURN CV1($messages)
  .php   L0077 0030 VERIFY_RETURN_TYPE
  .php   L0077 0031 RETURN null
  .php        0015, 0021, -, -
  .php        ; (lines=28, args=1, vars=5, tmps=12)
  .php   L0089 0018 T14 = COUNT CV2($kept)
  .php   L0089 0019 T15 = INIT_ARRAY 3 (packed) T14 NEXT
  .php   L0089 0020 T15 = ADD_ARRAY_ELEMENT CV3($grown) NEXT
  .php   L0089 0021 INIT_DYNAMIC_CALL 0 CV4($size)
  .php   L0089 0022 V16 = DO_FCALL
  .php   L0089 0023 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   L0089 0024 VERIFY_RETURN_TYPE T15
  .php   L0089 0025 RETURN T15
  .php   L0090 0026 VERIFY_RETURN_TYPE
  .php   L0090 0027 RETURN null
  .php        ; (lines=8, args=1, vars=2, tmps=1)
  .php   L0084 0006 RETURN bool(true)
  .php   L0085 0007 RETURN null
  .php        ; (lines=34, args=1, vars=4, tmps=14)
  .php   0004 JMP 0019
  .php   0018 ASSIGN_OP (SUB) CV0($times) int(1)
  .php   0019 T14 = IS_SMALLER int(0) CV0($times)
  .php   0020 JMPNZ T14 0005
  .php   0021 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0022 SEND_VAL_EX string(",") 1
  .php   0023 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0024 SEND_VAR CV1($marks) 1
  .php   0025 T15 = DECLARE_LAMBDA_FUNCTION 3
  .php   0026 SEND_VAL T15 2
  .php   0027 V16 = DO_ICALL
  .php   0028 SEND_VAR_NO_REF_EX V16 2
  .php   0029 V17 = DO_FCALL
  .php   0030 VERIFY_RETURN_TYPE V17
  .php   0031 RETURN V17
  .php   0032 VERIFY_RETURN_TYPE
  .php   0033 RETURN null
  .php        17: 0030 - 0031 (tmp/var)
  .php        ; (lines=122, args=2, vars=5, tmps=41)
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
  .php   0020 DO_FCALL
  .php   0021 T12 = QM_ASSIGN V6
  .php   0022 JMP 0024
  .php   0023 T12 = QM_ASSIGN CV1($prices)
  .php   0024 ASSIGN CV1($prices) T12
  .php   0025 INIT_FCALL 2 112 string("sharp\\internal\\filter")
  .php   0026 SEND_VAR CV0($numbers) 1
  .php   0027 T14 = DECLARE_LAMBDA_FUNCTION 1
  .php   0028 SEND_VAL T14 2
  .php   0029 V15 = DO_ICALL
  .php   0030 ASSIGN CV2($odd) V15
  .php   0031 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0032 SEND_VAR CV2($odd) 1
  .php   0033 T17 = DECLARE_LAMBDA_FUNCTION 2
  .php   0034 SEND_VAL T17 2
  .php   0035 V18 = DO_ICALL
  .php   0036 ASSIGN CV3($labels) V18
  .php   0037 INIT_FCALL 3 128 string("sharp\\internal\\groupby")
  .php   0038 SEND_VAR CV0($numbers) 1
  .php   0039 T20 = DECLARE_LAMBDA_FUNCTION 3
  .php   0040 SEND_VAL T20 2
  .php   0041 SEND_VAL string("string, List<int>") 3
  .php   0042 V21 = DO_ICALL
  .php   0043 ASSIGN CV4($groups) V21
  .php   0044 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0045 SEND_VAL_EX string(",") 1
  .php   0046 SEND_VAR_EX CV3($labels) 2
  .php   0047 V23 = DO_FCALL
  .php   0048 T24 = INIT_ARRAY 7 (packed) V23 NEXT
  .php   0049 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0050 SEND_VAL_EX string(",") 1
  .php   0051 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   0052 SEND_VAR CV0($numbers) 1
  .php   0053 T25 = DECLARE_LAMBDA_FUNCTION 4
  .php   0054 SEND_VAL T25 2
  .php   0055 SEND_VAL null 3
  .php   0056 V26 = DO_ICALL
  .php   0057 SEND_VAR_NO_REF_EX V26 2
  .php   0058 V27 = DO_FCALL
  .php   0059 T24 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   0060 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0061 SEND_VAL_EX string(",") 1
  .php   0062 T28 = FETCH_DIM_IS CV4($groups) string("odd")
  .php   0063 T29 = COALESCE T28 0065
  .php   0064 T29 = QM_ASSIGN array(...)
  .php   0065 SEND_VAL_EX T29 2
  .php   0066 V30 = DO_FCALL
  .php   0067 T24 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   0068 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0069 SEND_VAL_EX string(",") 1
  .php   0070 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0071 INIT_FCALL 3 128 string("sharp\\internal\\associateby")
  .php   0072 SEND_VAR CV3($labels) 1
  .php   0073 T31 = DECLARE_LAMBDA_FUNCTION 5
  .php   0074 SEND_VAL T31 2
  .php   0075 SEND_VAL string("string, string") 3
  .php   0076 V32 = DO_ICALL
  .php   0077 SEND_VAR V32 1
  .php   0078 V33 = DO_ICALL
  .php   0079 SEND_VAR_NO_REF_EX V33 2
  .php   0080 V34 = DO_FCALL
  .php   0081 T24 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   0082 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0083 SEND_VAL_EX string(",") 1
  .php   0084 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0085 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   0086 SEND_VAR CV1($prices) 1
  .php   0087 T35 = DECLARE_LAMBDA_FUNCTION 6
  .php   0088 SEND_VAL T35 2
  .php   0089 V36 = DO_ICALL
  .php   0090 SEND_VAR V36 1
  .php   0091 V37 = DO_ICALL
  .php   0092 SEND_VAR_NO_REF_EX V37 2
  .php   0093 V38 = DO_FCALL
  .php   0094 T24 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   0095 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0096 SEND_VAL_EX string(",") 1
  .php   0097 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   0098 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   0099 SEND_VAR CV1($prices) 1
  .php   0100 T39 = DECLARE_LAMBDA_FUNCTION 7
  .php   0101 SEND_VAL T39 2
  .php   0102 V40 = DO_ICALL
  .php   0103 SEND_VAR V40 1
  .php   0104 V41 = DO_ICALL
  .php   0105 SEND_VAR_NO_REF_EX V41 2
  .php   0106 V42 = DO_FCALL
  .php   0107 T24 = ADD_ARRAY_ELEMENT V42 NEXT
  .php   0108 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0109 SEND_VAL_EX string(",") 1
  .php   0110 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0111 SEND_VAR CV1($prices) 1
  .php   0112 T43 = DECLARE_LAMBDA_FUNCTION 8
  .php   0113 SEND_VAL T43 2
  .php   0114 V44 = DO_ICALL
  .php   0115 SEND_VAR_NO_REF_EX V44 2
  .php   0116 V45 = DO_FCALL
  .php   0117 T24 = ADD_ARRAY_ELEMENT V45 NEXT
  .php   0118 VERIFY_RETURN_TYPE T24
  .php   0119 RETURN T24
  .php   0120 VERIFY_RETURN_TYPE
  .php   0121 RETURN null
  .php        6: 0005 - 0021 (new)
  .php        24: 0049 - 0119 (tmp/var)
  .php   0000 CV0($key) = RECV 1
  .php   0001 CV1($value) = RECV 2
  .php   0002 T2 = CAST (string) CV0($key)
  .php   0004 T3 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=32, args=1, vars=4, tmps=9)
  .php   0004 T5 = DECLARE_LAMBDA_FUNCTION 0
  .php   0005 SEND_VAL T5 2
  .php   0007 JMP 0015
  .php   0009 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0010 SEND_REF CV1($messages) 1
  .php   0011 INIT_METHOD_CALL 0 CV2($missing) string("getMessage")
  .php   0012 V7 = DO_FCALL
  .php   0013 SEND_VAR V7 2
  .php   0014 DO_ICALL
  .php   0015 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0016 SEND_VAR CV0($numbers) 1
  .php   0017 T9 = DECLARE_LAMBDA_FUNCTION 1
  .php   0018 SEND_VAL T9 2
  .php   0019 DO_ICALL
  .php   0020 JMP 0028
  .php   0021 CV3($stopped) = CATCH string("OutOfRangeException")
  .php   0022 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0023 SEND_REF CV1($messages) 1
  .php   0024 INIT_METHOD_CALL 0 CV3($stopped) string("getMessage")
  .php   0025 V11 = DO_FCALL
  .php   0026 SEND_VAR V11 2
  .php   0027 DO_ICALL
  .php   0028 VERIFY_RETURN_TYPE CV1($messages)
  .php   0029 RETURN CV1($messages)
  .php   0030 VERIFY_RETURN_TYPE
  .php   0031 RETURN null
  .php        0015, 0021, -, -
  .php        ; (lines=28, args=1, vars=5, tmps=12)
  .php   0018 T14 = COUNT CV2($kept)
  .php   0019 T15 = INIT_ARRAY 3 (packed) T14 NEXT
  .php   0020 T15 = ADD_ARRAY_ELEMENT CV3($grown) NEXT
  .php   0021 INIT_DYNAMIC_CALL 0 CV4($size)
  .php   0022 V16 = DO_FCALL
  .php   0023 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   0024 VERIFY_RETURN_TYPE T15
  .php   0025 RETURN T15
  .php   0026 VERIFY_RETURN_TYPE
  .php   0027 RETURN null
  .php        15: 0020 - 0025 (tmp/var)
  .php        ; (lines=8, args=1, vars=2, tmps=1)
  .php   0006 RETURN bool(true)
  .php   0007 RETURN null
Cashier: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Cashier::receipt string("List<int>, Any?, Any?")
  .sharp      ; (lines=67, args=3, vars=7, tmps=25)
  .sharp L0023 0035 T20 = SHARP_VERIFY_TYPE_ARGS V19 string("List<int>")
  .sharp L0023 0036 ASSIGN CV6($recorded) T20
  .sharp L0025 0037 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0025 0038 SEND_VAL_EX string(",") 1
  .sharp L0025 0039 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0025 0040 SEND_VAR CV4($paid) 1
  .sharp L0025 0041 INIT_METHOD_CALL 0 THIS string("format")
  .sharp L0025 0042 T22 = CALLABLE_CONVERT
  .sharp L0025 0043 SEND_VAL T22 2
  .sharp L0025 0044 V23 = DO_ICALL
  .sharp L0025 0045 SEND_VAR_NO_REF_EX V23 2
  .sharp L0025 0046 V24 = DO_FCALL
  .sharp L0025 0047 T25 = INIT_ARRAY 5 (packed) V24 NEXT
  .sharp L0026 0048 T26 = CAST (string) CV5($total)
  .sharp L0026 0049 T25 = ADD_ARRAY_ELEMENT T26 NEXT
  .sharp L0027 0050 T27 = CAST (string) CV3($count)
  .sharp L0027 0051 T25 = ADD_ARRAY_ELEMENT T27 NEXT
  .sharp L0028 0052 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0028 0053 SEND_VAL_EX string(",") 1
  .sharp L0028 0054 SEND_VAR_EX CV6($recorded) 2
  .sharp L0028 0055 V28 = DO_FCALL
  .sharp L0028 0056 T25 = ADD_ARRAY_ELEMENT V28 NEXT
  .sharp L0029 0057 T29 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp L0029 0058 INIT_DYNAMIC_CALL 1 T29
  .sharp L0029 0059 SEND_VAR_EX CV5($total) 1
  .sharp L0029 0060 V30 = DO_FCALL
  .sharp L0029 0061 T31 = CAST (string) V30
  .sharp L0029 0062 T25 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp L0029 0063 VERIFY_RETURN_TYPE T25
  .sharp L0029 0064 RETURN T25
  .sharp L0031 0065 VERIFY_RETURN_TYPE
  .sharp L0031 0066 RETURN null
  .sharp      ; (lines=67, args=3, vars=7, tmps=25)
  .sharp 0035 T20 = SHARP_VERIFY_TYPE_ARGS V19 string("List<int>")
  .sharp 0036 ASSIGN CV6($recorded) T20
  .sharp 0037 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0038 SEND_VAL_EX string(",") 1
  .sharp 0039 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0040 SEND_VAR CV4($paid) 1
  .sharp 0041 INIT_METHOD_CALL 0 THIS string("format")
  .sharp 0042 T22 = CALLABLE_CONVERT
  .sharp 0043 SEND_VAL T22 2
  .sharp 0044 V23 = DO_ICALL
  .sharp 0045 SEND_VAR_NO_REF_EX V23 2
  .sharp 0046 V24 = DO_FCALL
  .sharp 0047 T25 = INIT_ARRAY 5 (packed) V24 NEXT
  .sharp 0048 T26 = CAST (string) CV5($total)
  .sharp 0049 T25 = ADD_ARRAY_ELEMENT T26 NEXT
  .sharp 0050 T27 = CAST (string) CV3($count)
  .sharp 0051 T25 = ADD_ARRAY_ELEMENT T27 NEXT
  .sharp 0052 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0053 SEND_VAL_EX string(",") 1
  .sharp 0054 SEND_VAR_EX CV6($recorded) 2
  .sharp 0055 V28 = DO_FCALL
  .sharp 0056 T25 = ADD_ARRAY_ELEMENT V28 NEXT
  .sharp 0057 T29 = FETCH_OBJ_R CV2($ledger) string("scale")
  .sharp 0058 INIT_DYNAMIC_CALL 1 T29
  .sharp 0059 SEND_VAR_EX CV5($total) 1
  .sharp 0060 V30 = DO_FCALL
  .sharp 0061 T31 = CAST (string) V30
  .sharp 0062 T25 = ADD_ARRAY_ELEMENT T31 NEXT
  .sharp 0063 VERIFY_RETURN_TYPE T25
  .sharp 0064 RETURN T25
  .sharp 0065 VERIFY_RETURN_TYPE
  .sharp 0066 RETURN null
  .sharp      25: 0048 - 0064 (tmp/var)
  .php        ; (lines=66, args=3, vars=7, tmps=24)
  .php   L0023 0035 ASSIGN CV6($recorded) V19
  .php   L0025 0036 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0025 0037 SEND_VAL_EX string(",") 1
  .php   L0025 0038 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0025 0039 SEND_VAR CV4($paid) 1
  .php   L0025 0040 INIT_METHOD_CALL 0 THIS string("format")
  .php   L0025 0041 T21 = CALLABLE_CONVERT
  .php   L0025 0042 SEND_VAL T21 2
  .php   L0025 0043 V22 = DO_ICALL
  .php   L0025 0044 SEND_VAR_NO_REF_EX V22 2
  .php   L0025 0045 V23 = DO_FCALL
  .php   L0025 0046 T24 = INIT_ARRAY 5 (packed) V23 NEXT
  .php   L0026 0047 T25 = CAST (string) CV5($total)
  .php   L0026 0048 T24 = ADD_ARRAY_ELEMENT T25 NEXT
  .php   L0027 0049 T26 = CAST (string) CV3($count)
  .php   L0027 0050 T24 = ADD_ARRAY_ELEMENT T26 NEXT
  .php   L0028 0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0028 0052 SEND_VAL_EX string(",") 1
  .php   L0028 0053 SEND_VAR_EX CV6($recorded) 2
  .php   L0028 0054 V27 = DO_FCALL
  .php   L0028 0055 T24 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   L0029 0056 T28 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   L0029 0057 INIT_DYNAMIC_CALL 1 T28
  .php   L0029 0058 SEND_VAR_EX CV5($total) 1
  .php   L0029 0059 V29 = DO_FCALL
  .php   L0029 0060 T30 = CAST (string) V29
  .php   L0029 0061 T24 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   L0029 0062 VERIFY_RETURN_TYPE T24
  .php   L0029 0063 RETURN T24
  .php   L0031 0064 VERIFY_RETURN_TYPE
  .php   L0031 0065 RETURN null
  .php        ; (lines=66, args=3, vars=7, tmps=24)
  .php   0035 ASSIGN CV6($recorded) V19
  .php   0036 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0037 SEND_VAL_EX string(",") 1
  .php   0038 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0039 SEND_VAR CV4($paid) 1
  .php   0040 INIT_METHOD_CALL 0 THIS string("format")
  .php   0041 T21 = CALLABLE_CONVERT
  .php   0042 SEND_VAL T21 2
  .php   0043 V22 = DO_ICALL
  .php   0044 SEND_VAR_NO_REF_EX V22 2
  .php   0045 V23 = DO_FCALL
  .php   0046 T24 = INIT_ARRAY 5 (packed) V23 NEXT
  .php   0047 T25 = CAST (string) CV5($total)
  .php   0048 T24 = ADD_ARRAY_ELEMENT T25 NEXT
  .php   0049 T26 = CAST (string) CV3($count)
  .php   0050 T24 = ADD_ARRAY_ELEMENT T26 NEXT
  .php   0051 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0052 SEND_VAL_EX string(",") 1
  .php   0053 SEND_VAR_EX CV6($recorded) 2
  .php   0054 V27 = DO_FCALL
  .php   0055 T24 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   0056 T28 = FETCH_OBJ_R CV2($ledger) string("scale")
  .php   0057 INIT_DYNAMIC_CALL 1 T28
  .php   0058 SEND_VAR_EX CV5($total) 1
  .php   0059 V29 = DO_FCALL
  .php   0060 T30 = CAST (string) V29
  .php   0061 T24 = ADD_ARRAY_ELEMENT T30 NEXT
  .php   0062 VERIFY_RETURN_TYPE T24
  .php   0063 RETURN T24
  .php   0064 VERIFY_RETURN_TYPE
  .php   0065 RETURN null
  .php        24: 0047 - 0063 (tmp/var)
Roster: different opcodes and lines in 8 op arrays, same signatures in 1 classes
  check  Demo\Roster::report string("Map<Lib.Standing, int>")
  .sharp      ; (lines=118, args=1, vars=5, tmps=47)
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
  .sharp L0010 0026 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp L0010 0027 SEND_VAR CV0($members) 1
  .sharp L0010 0028 T16 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0010 0029 SEND_VAL T16 2
  .sharp L0010 0030 SEND_VAL null 3
  .sharp L0010 0031 V17 = DO_ICALL
  .sharp L0010 0032 ASSIGN CV1($ranked) V17
  .sharp L0011 0033 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0011 0034 SEND_VAR CV0($members) 1
  .sharp L0011 0035 T19 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0011 0036 SEND_VAL T19 2
  .sharp L0011 0037 V20 = DO_ICALL
  .sharp L0011 0038 ASSIGN CV2($tens) V20
  .sharp L0012 0039 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp L0012 0040 SEND_VAR CV0($members) 1
  .sharp L0012 0041 T22 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0012 0042 SEND_VAL T22 2
  .sharp L0012 0043 V23 = DO_ICALL
  .sharp L0012 0044 ASSIGN CV3($busy) V23
  .sharp L0013 0045 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp L0013 0046 SEND_VAR CV0($members) 1
  .sharp L0013 0047 T25 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0013 0048 BIND_LEXICAL (ref) T25 CV0($members)
  .sharp L0013 0049 SEND_VAL T25 2
  .sharp L0013 0050 V26 = DO_ICALL
  .sharp L0013 0051 ASSIGN CV4($kept) V26
  .sharp L0014 0052 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0014 0053 SEND_REF CV0($members) 1
  .sharp L0014 0054 T28 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0014 0055 SEND_VAL T28 2
  .sharp L0014 0056 DO_ICALL
  .sharp L0014 0057 SEPARATE CV0($members)
  .sharp L0016 0058 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0016 0059 SEND_VAL_EX string(",") 1
  .sharp L0016 0060 SEND_VAR_EX CV1($ranked) 2
  .sharp L0016 0061 V30 = DO_FCALL
  .sharp L0016 0062 T31 = INIT_ARRAY 7 (packed) V30 NEXT
  .sharp L0017 0063 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0017 0064 SEND_VAL_EX string(",") 1
  .sharp L0017 0065 SEND_VAR_EX CV2($tens) 2
  .sharp L0017 0066 V32 = DO_FCALL
  .sharp L0017 0067 T31 = ADD_ARRAY_ELEMENT V32 NEXT
  .sharp L0018 0068 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0018 0069 SEND_VAL_EX string(",") 1
  .sharp L0018 0070 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0018 0071 SEND_VAR CV4($kept) 1
  .sharp L0018 0072 T33 = DECLARE_LAMBDA_FUNCTION 5
  .sharp L0018 0073 SEND_VAL T33 2
  .sharp L0018 0074 V34 = DO_ICALL
  .sharp L0018 0075 SEND_VAR_NO_REF_EX V34 2
  .sharp L0018 0076 V35 = DO_FCALL
  .sharp L0018 0077 T31 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp L0019 0078 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0019 0079 SEND_VAR CV3($busy) 1
  .sharp L0019 0080 T36 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0019 0081 SEND_VAL T36 2
  .sharp L0019 0082 V37 = DO_ICALL
  .sharp L0019 0083 T38 = COALESCE V37 0085
  .sharp L0019 0084 T38 = QM_ASSIGN int(0)
  .sharp L0019 0085 T39 = CAST (string) T38
  .sharp L0019 0086 T31 = ADD_ARRAY_ELEMENT T39 NEXT
  .sharp L0020 0087 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0020 0088 SEND_VAR CV3($busy) 1
  .sharp L0020 0089 T40 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0020 0090 SEND_VAL T40 2
  .sharp L0020 0091 V41 = DO_ICALL
  .sharp L0020 0092 T42 = COALESCE V41 0094
  .sharp L0020 0093 T42 = QM_ASSIGN int(0)
  .sharp L0020 0094 T43 = CAST (string) T42
  .sharp L0020 0095 T31 = ADD_ARRAY_ELEMENT T43 NEXT
  .sharp L0021 0096 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0021 0097 SEND_VAR CV0($members) 1
  .sharp L0021 0098 T44 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0021 0099 SEND_VAL T44 2
  .sharp L0021 0100 V45 = DO_ICALL
  .sharp L0021 0101 T46 = COALESCE V45 0103
  .sharp L0021 0102 T46 = QM_ASSIGN int(0)
  .sharp L0021 0103 T47 = CAST (string) T46
  .sharp L0021 0104 T31 = ADD_ARRAY_ELEMENT T47 NEXT
  .sharp L0022 0105 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0022 0106 SEND_VAR CV0($members) 1
  .sharp L0022 0107 T48 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0022 0108 SEND_VAL T48 2
  .sharp L0022 0109 V49 = DO_ICALL
  .sharp L0022 0110 T50 = COALESCE V49 0112
  .sharp L0022 0111 T50 = QM_ASSIGN int(0)
  .sharp L0022 0112 T51 = CAST (string) T50
  .sharp L0022 0113 T31 = ADD_ARRAY_ELEMENT T51 NEXT
  .sharp L0022 0114 VERIFY_RETURN_TYPE T31
  .sharp L0022 0115 RETURN T31
  .sharp L0024 0116 VERIFY_RETURN_TYPE
  .sharp L0024 0117 RETURN null
  .sharp L0008 0000 CV0($key#1) = RECV 1
  .sharp L0008 0001 CV1($value#2) = RECV 2
  .sharp L0008 0003 T2 = CAST (string) CV0($key#1)
  .sharp L0008 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp      ; (lines=118, args=1, vars=5, tmps=47)
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
  .sharp 0026 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp 0027 SEND_VAR CV0($members) 1
  .sharp 0028 T16 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0029 SEND_VAL T16 2
  .sharp 0030 SEND_VAL null 3
  .sharp 0031 V17 = DO_ICALL
  .sharp 0032 ASSIGN CV1($ranked) V17
  .sharp 0033 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0034 SEND_VAR CV0($members) 1
  .sharp 0035 T19 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0036 SEND_VAL T19 2
  .sharp 0037 V20 = DO_ICALL
  .sharp 0038 ASSIGN CV2($tens) V20
  .sharp 0039 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp 0040 SEND_VAR CV0($members) 1
  .sharp 0041 T22 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0042 SEND_VAL T22 2
  .sharp 0043 V23 = DO_ICALL
  .sharp 0044 ASSIGN CV3($busy) V23
  .sharp 0045 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .sharp 0046 SEND_VAR CV0($members) 1
  .sharp 0047 T25 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0048 BIND_LEXICAL (ref) T25 CV0($members)
  .sharp 0049 SEND_VAL T25 2
  .sharp 0050 V26 = DO_ICALL
  .sharp 0051 ASSIGN CV4($kept) V26
  .sharp 0052 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0053 SEND_REF CV0($members) 1
  .sharp 0054 T28 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0055 SEND_VAL T28 2
  .sharp 0056 DO_ICALL
  .sharp 0057 SEPARATE CV0($members)
  .sharp 0058 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0059 SEND_VAL_EX string(",") 1
  .sharp 0060 SEND_VAR_EX CV1($ranked) 2
  .sharp 0061 V30 = DO_FCALL
  .sharp 0062 T31 = INIT_ARRAY 7 (packed) V30 NEXT
  .sharp 0063 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0064 SEND_VAL_EX string(",") 1
  .sharp 0065 SEND_VAR_EX CV2($tens) 2
  .sharp 0066 V32 = DO_FCALL
  .sharp 0067 T31 = ADD_ARRAY_ELEMENT V32 NEXT
  .sharp 0068 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0069 SEND_VAL_EX string(",") 1
  .sharp 0070 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0071 SEND_VAR CV4($kept) 1
  .sharp 0072 T33 = DECLARE_LAMBDA_FUNCTION 5
  .sharp 0073 SEND_VAL T33 2
  .sharp 0074 V34 = DO_ICALL
  .sharp 0075 SEND_VAR_NO_REF_EX V34 2
  .sharp 0076 V35 = DO_FCALL
  .sharp 0077 T31 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp 0078 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0079 SEND_VAR CV3($busy) 1
  .sharp 0080 T36 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0081 SEND_VAL T36 2
  .sharp 0082 V37 = DO_ICALL
  .sharp 0083 T38 = COALESCE V37 0085
  .sharp 0084 T38 = QM_ASSIGN int(0)
  .sharp 0085 T39 = CAST (string) T38
  .sharp 0086 T31 = ADD_ARRAY_ELEMENT T39 NEXT
  .sharp 0087 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0088 SEND_VAR CV3($busy) 1
  .sharp 0089 T40 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0090 SEND_VAL T40 2
  .sharp 0091 V41 = DO_ICALL
  .sharp 0092 T42 = COALESCE V41 0094
  .sharp 0093 T42 = QM_ASSIGN int(0)
  .sharp 0094 T43 = CAST (string) T42
  .sharp 0095 T31 = ADD_ARRAY_ELEMENT T43 NEXT
  .sharp 0096 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0097 SEND_VAR CV0($members) 1
  .sharp 0098 T44 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0099 SEND_VAL T44 2
  .sharp 0100 V45 = DO_ICALL
  .sharp 0101 T46 = COALESCE V45 0103
  .sharp 0102 T46 = QM_ASSIGN int(0)
  .sharp 0103 T47 = CAST (string) T46
  .sharp 0104 T31 = ADD_ARRAY_ELEMENT T47 NEXT
  .sharp 0105 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0106 SEND_VAR CV0($members) 1
  .sharp 0107 T48 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0108 SEND_VAL T48 2
  .sharp 0109 V49 = DO_ICALL
  .sharp 0110 T50 = COALESCE V49 0112
  .sharp 0111 T50 = QM_ASSIGN int(0)
  .sharp 0112 T51 = CAST (string) T50
  .sharp 0113 T31 = ADD_ARRAY_ELEMENT T51 NEXT
  .sharp 0114 VERIFY_RETURN_TYPE T31
  .sharp 0115 RETURN T31
  .sharp 0116 VERIFY_RETURN_TYPE
  .sharp 0117 RETURN null
  .sharp      8: 0006 - 0022 (new)
  .sharp      25: 0048 - 0049 (tmp/var)
  .sharp      31: 0063 - 0115 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0003 T2 = CAST (string) CV0($key#1)
  .sharp 0007 T4 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=115, args=1, vars=5, tmps=45)
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
  .php   L0010 0024 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   L0010 0025 SEND_VAR CV0($members) 1
  .php   L0010 0026 T14 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0010 0027 SEND_VAL T14 2
  .php   L0010 0028 SEND_VAL null 3
  .php   L0010 0029 V15 = DO_ICALL
  .php   L0010 0030 ASSIGN CV1($ranked) V15
  .php   L0011 0031 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0011 0032 SEND_VAR CV0($members) 1
  .php   L0011 0033 T17 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0011 0034 SEND_VAL T17 2
  .php   L0011 0035 V18 = DO_ICALL
  .php   L0011 0036 ASSIGN CV2($tens) V18
  .php   L0012 0037 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   L0012 0038 SEND_VAR CV0($members) 1
  .php   L0012 0039 T20 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0012 0040 SEND_VAL T20 2
  .php   L0012 0041 V21 = DO_ICALL
  .php   L0012 0042 ASSIGN CV3($busy) V21
  .php   L0013 0043 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   L0013 0044 SEND_VAR CV0($members) 1
  .php   L0013 0045 T23 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0013 0046 BIND_LEXICAL (ref) T23 CV0($members)
  .php   L0013 0047 SEND_VAL T23 2
  .php   L0013 0048 V24 = DO_ICALL
  .php   L0013 0049 ASSIGN CV4($kept) V24
  .php   L0014 0050 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0014 0051 SEND_REF CV0($members) 1
  .php   L0014 0052 T26 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0014 0053 SEND_VAL T26 2
  .php   L0014 0054 DO_ICALL
  .php   L0016 0055 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0016 0056 SEND_VAL_EX string(",") 1
  .php   L0016 0057 SEND_VAR_EX CV1($ranked) 2
  .php   L0016 0058 V28 = DO_FCALL
  .php   L0016 0059 T29 = INIT_ARRAY 7 (packed) V28 NEXT
  .php   L0017 0060 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0017 0061 SEND_VAL_EX string(",") 1
  .php   L0017 0062 SEND_VAR_EX CV2($tens) 2
  .php   L0017 0063 V30 = DO_FCALL
  .php   L0017 0064 T29 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   L0018 0065 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0018 0066 SEND_VAL_EX string(",") 1
  .php   L0018 0067 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0018 0068 SEND_VAR CV4($kept) 1
  .php   L0018 0069 T31 = DECLARE_LAMBDA_FUNCTION 5
  .php   L0018 0070 SEND_VAL T31 2
  .php   L0018 0071 V32 = DO_ICALL
  .php   L0018 0072 SEND_VAR_NO_REF_EX V32 2
  .php   L0018 0073 V33 = DO_FCALL
  .php   L0018 0074 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   L0019 0075 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0019 0076 SEND_VAR CV3($busy) 1
  .php   L0019 0077 T34 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0019 0078 SEND_VAL T34 2
  .php   L0019 0079 V35 = DO_ICALL
  .php   L0019 0080 T36 = COALESCE V35 0082
  .php   L0019 0081 T36 = QM_ASSIGN int(0)
  .php   L0019 0082 T37 = CAST (string) T36
  .php   L0019 0083 T29 = ADD_ARRAY_ELEMENT T37 NEXT
  .php   L0020 0084 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0020 0085 SEND_VAR CV3($busy) 1
  .php   L0020 0086 T38 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0020 0087 SEND_VAL T38 2
  .php   L0020 0088 V39 = DO_ICALL
  .php   L0020 0089 T40 = COALESCE V39 0091
  .php   L0020 0090 T40 = QM_ASSIGN int(0)
  .php   L0020 0091 T41 = CAST (string) T40
  .php   L0020 0092 T29 = ADD_ARRAY_ELEMENT T41 NEXT
  .php   L0021 0093 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0021 0094 SEND_VAR CV0($members) 1
  .php   L0021 0095 T42 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0021 0096 SEND_VAL T42 2
  .php   L0021 0097 V43 = DO_ICALL
  .php   L0021 0098 T44 = COALESCE V43 0100
  .php   L0021 0099 T44 = QM_ASSIGN int(0)
  .php   L0021 0100 T45 = CAST (string) T44
  .php   L0021 0101 T29 = ADD_ARRAY_ELEMENT T45 NEXT
  .php   L0022 0102 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0022 0103 SEND_VAR CV0($members) 1
  .php   L0022 0104 T46 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0022 0105 SEND_VAL T46 2
  .php   L0022 0106 V47 = DO_ICALL
  .php   L0022 0107 T48 = COALESCE V47 0109
  .php   L0022 0108 T48 = QM_ASSIGN int(0)
  .php   L0022 0109 T49 = CAST (string) T48
  .php   L0022 0110 T29 = ADD_ARRAY_ELEMENT T49 NEXT
  .php   L0022 0111 VERIFY_RETURN_TYPE T29
  .php   L0022 0112 RETURN T29
  .php   L0024 0113 VERIFY_RETURN_TYPE
  .php   L0024 0114 RETURN null
  .php   L0008 0000 CV0($key) = RECV 1
  .php   L0008 0001 CV1($value) = RECV 2
  .php   L0008 0003 T2 = CAST (string) CV0($key)
  .php   L0008 0007 T4 = ADD_ARRAY_ELEMENT CV1($value) NEXT
  .php        ; (lines=115, args=1, vars=5, tmps=45)
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
  .php   0024 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   0025 SEND_VAR CV0($members) 1
  .php   0026 T14 = DECLARE_LAMBDA_FUNCTION 1
  .php   0027 SEND_VAL T14 2
  .php   0028 SEND_VAL null 3
  .php   0029 V15 = DO_ICALL
  .php   0030 ASSIGN CV1($ranked) V15
  .php   0031 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0032 SEND_VAR CV0($members) 1
  .php   0033 T17 = DECLARE_LAMBDA_FUNCTION 2
  .php   0034 SEND_VAL T17 2
  .php   0035 V18 = DO_ICALL
  .php   0036 ASSIGN CV2($tens) V18
  .php   0037 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   0038 SEND_VAR CV0($members) 1
  .php   0039 T20 = DECLARE_LAMBDA_FUNCTION 3
  .php   0040 SEND_VAL T20 2
  .php   0041 V21 = DO_ICALL
  .php   0042 ASSIGN CV3($busy) V21
  .php   0043 INIT_FCALL 2 112 string("sharp\\internal\\filterentries")
  .php   0044 SEND_VAR CV0($members) 1
  .php   0045 T23 = DECLARE_LAMBDA_FUNCTION 4
  .php   0046 BIND_LEXICAL (ref) T23 CV0($members)
  .php   0047 SEND_VAL T23 2
  .php   0048 V24 = DO_ICALL
  .php   0049 ASSIGN CV4($kept) V24
  .php   0050 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0051 SEND_REF CV0($members) 1
  .php   0052 T26 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0053 SEND_VAL T26 2
  .php   0054 DO_ICALL
  .php   0055 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0056 SEND_VAL_EX string(",") 1
  .php   0057 SEND_VAR_EX CV1($ranked) 2
  .php   0058 V28 = DO_FCALL
  .php   0059 T29 = INIT_ARRAY 7 (packed) V28 NEXT
  .php   0060 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0061 SEND_VAL_EX string(",") 1
  .php   0062 SEND_VAR_EX CV2($tens) 2
  .php   0063 V30 = DO_FCALL
  .php   0064 T29 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   0065 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0066 SEND_VAL_EX string(",") 1
  .php   0067 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0068 SEND_VAR CV4($kept) 1
  .php   0069 T31 = DECLARE_LAMBDA_FUNCTION 5
  .php   0070 SEND_VAL T31 2
  .php   0071 V32 = DO_ICALL
  .php   0072 SEND_VAR_NO_REF_EX V32 2
  .php   0073 V33 = DO_FCALL
  .php   0074 T29 = ADD_ARRAY_ELEMENT V33 NEXT
  .php   0075 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0076 SEND_VAR CV3($busy) 1
  .php   0077 T34 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0078 SEND_VAL T34 2
  .php   0079 V35 = DO_ICALL
  .php   0080 T36 = COALESCE V35 0082
  .php   0081 T36 = QM_ASSIGN int(0)
  .php   0082 T37 = CAST (string) T36
  .php   0083 T29 = ADD_ARRAY_ELEMENT T37 NEXT
  .php   0084 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0085 SEND_VAR CV3($busy) 1
  .php   0086 T38 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0087 SEND_VAL T38 2
  .php   0088 V39 = DO_ICALL
  .php   0089 T40 = COALESCE V39 0091
  .php   0090 T40 = QM_ASSIGN int(0)
  .php   0091 T41 = CAST (string) T40
  .php   0092 T29 = ADD_ARRAY_ELEMENT T41 NEXT
  .php   0093 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0094 SEND_VAR CV0($members) 1
  .php   0095 T42 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0096 SEND_VAL T42 2
  .php   0097 V43 = DO_ICALL
  .php   0098 T44 = COALESCE V43 0100
  .php   0099 T44 = QM_ASSIGN int(0)
  .php   0100 T45 = CAST (string) T44
  .php   0101 T29 = ADD_ARRAY_ELEMENT T45 NEXT
  .php   0102 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0103 SEND_VAR CV0($members) 1
  .php   0104 T46 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0105 SEND_VAL T46 2
  .php   0106 V47 = DO_ICALL
  .php   0107 T48 = COALESCE V47 0109
  .php   0108 T48 = QM_ASSIGN int(0)
  .php   0109 T49 = CAST (string) T48
  .php   0110 T29 = ADD_ARRAY_ELEMENT T49 NEXT
  .php   0111 VERIFY_RETURN_TYPE T29
  .php   0112 RETURN T29
  .php   0113 VERIFY_RETURN_TYPE
  .php   0114 RETURN null
  .php        6: 0004 - 0020 (new)
  .php        23: 0046 - 0047 (tmp/var)
  .php        29: 0060 - 0112 (tmp/var)
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
  .sharp      ; (lines=71, args=1, vars=7, tmps=25)
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
  .sharp L0021 0012 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0021 0013 SEND_REF CV0($tags) 1
  .sharp L0021 0014 SEND_VAL string("vip") 2
  .sharp L0021 0015 V14 = DO_ICALL
  .sharp L0021 0016 SEPARATE CV0($tags)
  .sharp L0021 0017 ASSIGN CV1($added) V14
  .sharp L0022 0018 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0022 0019 SEND_REF CV0($tags) 1
  .sharp L0022 0020 SEND_VAL string("vip") 2
  .sharp L0022 0021 V16 = DO_ICALL
  .sharp L0022 0022 SEPARATE CV0($tags)
  .sharp L0022 0023 ASSIGN CV2($again) V16
  .sharp L0023 0024 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0023 0025 SEND_REF CV0($tags) 1
  .sharp L0023 0026 SEND_VAL string("new") 2
  .sharp L0023 0027 V18 = DO_ICALL
  .sharp L0023 0028 SEPARATE CV0($tags)
  .sharp L0023 0029 ASSIGN CV3($removed) V18
  .sharp L0024 0030 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0024 0031 SEND_REF CV0($tags) 1
  .sharp L0024 0032 SEND_VAL string("none") 2
  .sharp L0024 0033 V20 = DO_ICALL
  .sharp L0024 0034 SEPARATE CV0($tags)
  .sharp L0024 0035 ASSIGN CV4($absent) V20
  .sharp L0025 0036 ASSIGN CV5($joined) string("")
  .sharp L0026 0037 V23 = FE_RESET_R CV0($tags) 0043
  .sharp L0026 0038 FE_FETCH_R V23 CV6($tag) 0043
  .sharp L0027 0039 T24 = CONCAT CV5($joined) CV6($tag)
  .sharp L0027 0040 T25 = CONCAT T24 string(";")
  .sharp L0027 0041 ASSIGN CV5($joined) T25
  .sharp L0026 0042 JMP 0038
  .sharp L0026 0043 FE_FREE V23
  .sharp L0029 0044 T27 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .sharp L0029 0045 T27 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp L0029 0046 T27 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp L0029 0047 T27 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .sharp L0029 0048 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0029 0049 SEND_VAR CV0($tags) 1
  .sharp L0029 0050 SEND_VAL string("5") 2
  .sharp L0029 0051 V28 = DO_ICALL
  .sharp L0029 0052 T27 = ADD_ARRAY_ELEMENT V28 NEXT
  .sharp L0029 0053 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0029 0054 SEND_VAR CV0($tags) 1
  .sharp L0029 0055 SEND_VAL string("new") 2
  .sharp L0029 0056 V29 = DO_ICALL
  .sharp L0029 0057 T27 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp L0029 0058 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp L0029 0059 SEND_VAR CV0($tags) 1
  .sharp L0029 0060 V30 = DO_ICALL
  .sharp L0029 0061 T27 = ADD_ARRAY_ELEMENT V30 NEXT
  .sharp L0029 0062 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0029 0063 SEND_VAR CV0($tags) 1
  .sharp L0029 0064 V31 = DO_ICALL
  .sharp L0029 0065 T27 = ADD_ARRAY_ELEMENT V31 NEXT
  .sharp L0029 0066 T27 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .sharp L0029 0067 VERIFY_RETURN_TYPE T27
  .sharp L0029 0068 RETURN T27
  .sharp L0030 0069 VERIFY_RETURN_TYPE
  .sharp L0030 0070 RETURN null
  .sharp      ; (lines=58, args=1, vars=1, tmps=21)
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
  .sharp L0035 0012 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0035 0013 INIT_FCALL 2 112 string("sharp\\internal\\filterelements")
  .sharp L0035 0014 SEND_VAR CV0($tags) 1
  .sharp L0035 0015 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0035 0016 SEND_VAL T8 2
  .sharp L0035 0017 V9 = DO_ICALL
  .sharp L0035 0018 SEND_VAR V9 1
  .sharp L0035 0019 V10 = DO_ICALL
  .sharp L0035 0020 T11 = INIT_ARRAY 6 (packed) V10 NEXT
  .sharp L0036 0021 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp L0036 0022 SEND_VAR CV0($tags) 1
  .sharp L0036 0023 T12 = DECLARE_LAMBDA_FUNCTION 1
  .sharp L0036 0024 SEND_VAL T12 2
  .sharp L0036 0025 V13 = DO_ICALL
  .sharp L0036 0026 T11 = ADD_ARRAY_ELEMENT V13 NEXT
  .sharp L0037 0027 INIT_FCALL 2 112 string("sharp\\internal\\any")
  .sharp L0037 0028 SEND_VAR CV0($tags) 1
  .sharp L0037 0029 T14 = DECLARE_LAMBDA_FUNCTION 2
  .sharp L0037 0030 SEND_VAL T14 2
  .sharp L0037 0031 V15 = DO_ICALL
  .sharp L0037 0032 T11 = ADD_ARRAY_ELEMENT V15 NEXT
  .sharp L0038 0033 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .sharp L0038 0034 SEND_VAR CV0($tags) 1
  .sharp L0038 0035 T16 = DECLARE_LAMBDA_FUNCTION 3
  .sharp L0038 0036 SEND_VAL T16 2
  .sharp L0038 0037 V17 = DO_ICALL
  .sharp L0038 0038 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp L0039 0039 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp L0039 0040 SEND_VAR CV0($tags) 1
  .sharp L0039 0041 T18 = DECLARE_LAMBDA_FUNCTION 4
  .sharp L0039 0042 SEND_VAL T18 2
  .sharp L0039 0043 SEND_VAL null 3
  .sharp L0039 0044 V19 = DO_ICALL
  .sharp L0039 0045 T11 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp L0040 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp L0040 0047 SEND_VAL_EX string(",") 1
  .sharp L0040 0048 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp L0040 0049 SEND_VAR CV0($tags) 1
  .sharp L0040 0050 V20 = DO_ICALL
  .sharp L0040 0051 SEND_VAR_NO_REF_EX V20 2
  .sharp L0040 0052 V21 = DO_FCALL
  .sharp L0040 0053 T11 = ADD_ARRAY_ELEMENT V21 NEXT
  .sharp L0040 0054 VERIFY_RETURN_TYPE T11
  .sharp L0040 0055 RETURN T11
  .sharp L0042 0056 VERIFY_RETURN_TYPE
  .sharp L0042 0057 RETURN null
  .sharp      ; (lines=21, args=1, vars=1, tmps=9)
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
  .sharp L0044 0012 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .sharp L0044 0013 SEND_VAR CV0($tags) 1
  .sharp L0044 0014 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0044 0015 SEND_VAL T8 2
  .sharp L0044 0016 V9 = DO_ICALL
  .sharp L0044 0017 VERIFY_RETURN_TYPE V9
  .sharp L0044 0018 RETURN V9
  .sharp L0044 0019 VERIFY_RETURN_TYPE
  .sharp L0044 0020 RETURN null
  .sharp      ; (lines=59, args=1, vars=2, tmps=21)
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
  .sharp L0049 0018 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0049 0019 SEND_REF CV0($ids) 1
  .sharp L0049 0020 SEND_VAL int(4) 2
  .sharp L0049 0021 V14 = DO_ICALL
  .sharp L0049 0022 SEPARATE CV0($ids)
  .sharp L0049 0023 T15 = INIT_ARRAY 7 (packed) V14 NEXT
  .sharp L0049 0024 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0049 0026 SEND_VAL int(4) 2
  .sharp L0049 0027 V16 = DO_ICALL
  .sharp L0049 0028 SEPARATE CV0($ids)
  .sharp L0049 0029 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp L0049 0030 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0049 0031 SEND_REF CV0($ids) 1
  .sharp L0049 0032 SEND_VAL int(7) 2
  .sharp L0049 0033 V17 = DO_ICALL
  .sharp L0049 0034 SEPARATE CV0($ids)
  .sharp L0049 0035 T15 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp L0049 0036 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0049 0037 SEND_VAR CV0($ids) 1
  .sharp L0049 0038 SEND_VAL int(2) 2
  .sharp L0049 0039 V18 = DO_ICALL
  .sharp L0049 0040 T15 = ADD_ARRAY_ELEMENT V18 NEXT
  .sharp L0049 0041 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0049 0042 SEND_VAR CV0($ids) 1
  .sharp L0049 0043 V19 = DO_ICALL
  .sharp L0049 0044 T15 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp L0049 0045 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0049 0046 SEND_VAR CV1($more) 1
  .sharp L0049 0047 V20 = DO_ICALL
  .sharp L0049 0048 T15 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp L0049 0049 INIT_FCALL 2 112 string("sharp\\internal\\sumof")
  .sharp L0049 0050 SEND_VAR CV0($ids) 1
  .sharp L0049 0051 T21 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0049 0052 SEND_VAL T21 2
  .sharp L0049 0053 V22 = DO_ICALL
  .sharp L0049 0054 T15 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp L0049 0055 VERIFY_RETURN_TYPE T15
  .sharp L0049 0056 RETURN T15
  .sharp L0050 0057 VERIFY_RETURN_TYPE
  .sharp L0050 0058 RETURN null
  .sharp      ; (lines=73, args=1, vars=6, tmps=33)
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
  .sharp L0055 0024 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0055 0025 SEND_REF CV0($states) 1
  .sharp L0055 0026 T22 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0055 0027 SEND_VAL T22 2
  .sharp L0055 0028 V23 = DO_ICALL
  .sharp L0055 0029 SEPARATE CV0($states)
  .sharp L0055 0030 ASSIGN CV2($closed) V23
  .sharp L0056 0031 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0056 0032 SEND_REF CV0($states) 1
  .sharp L0056 0033 T25 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0056 0034 SEND_VAL T25 2
  .sharp L0056 0035 V26 = DO_ICALL
  .sharp L0056 0036 SEPARATE CV0($states)
  .sharp L0056 0037 ASSIGN CV3($active) V26
  .sharp L0057 0038 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0057 0039 SEND_VAR CV0($states) 1
  .sharp L0057 0040 T28 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp L0057 0041 SEND_VAL T28 2
  .sharp L0057 0042 V29 = DO_ICALL
  .sharp L0057 0043 ASSIGN CV4($has) V29
  .sharp L0058 0044 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0058 0045 SEND_REF CV0($states) 1
  .sharp L0058 0046 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp L0058 0047 SEND_VAL T31 2
  .sharp L0058 0048 V32 = DO_ICALL
  .sharp L0058 0049 SEPARATE CV0($states)
  .sharp L0058 0050 ASSIGN CV5($removed) V32
  .sharp L0059 0051 T34 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .sharp L0059 0052 T34 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .sharp L0059 0053 T34 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .sharp L0059 0054 T34 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .sharp L0059 0055 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0059 0056 SEND_VAR CV0($states) 1
  .sharp L0059 0057 V35 = DO_ICALL
  .sharp L0059 0058 T34 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp L0059 0059 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0059 0060 SEND_VAR CV1($sizes) 1
  .sharp L0059 0061 V36 = DO_ICALL
  .sharp L0059 0062 T34 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp L0059 0063 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0059 0064 SEND_VAR CV1($sizes) 1
  .sharp L0059 0065 T37 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp L0059 0066 SEND_VAL T37 2
  .sharp L0059 0067 V38 = DO_ICALL
  .sharp L0059 0068 T34 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp L0059 0069 VERIFY_RETURN_TYPE T34
  .sharp L0059 0070 RETURN T34
  .sharp L0060 0071 VERIFY_RETURN_TYPE
  .sharp L0060 0072 RETURN null
  .sharp      ; (lines=133, args=1, vars=10, tmps=56)
  .sharp L0064 0002 V10 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp L0064 0003 SEND_REF V10 1
  .sharp L0064 0005 V11 = DO_ICALL
  .sharp L0064 0006 V12 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp L0064 0007 V12 = SEPARATE V12
  .sharp L0064 0008 ASSIGN CV1($vip) V11
  .sharp L0065 0009 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0065 0010 V14 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp L0065 0011 SEND_REF V14 1
  .sharp L0065 0012 SEND_VAL string("vip") 2
  .sharp L0065 0013 V15 = DO_ICALL
  .sharp L0065 0014 V16 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp L0065 0015 V16 = SEPARATE V16
  .sharp L0065 0016 ASSIGN CV2($again) V15
  .sharp L0066 0017 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0066 0018 V18 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp L0066 0019 SEND_REF V18 1
  .sharp L0066 0020 SEND_VAL string("new") 2
  .sharp L0066 0021 V19 = DO_ICALL
  .sharp L0066 0022 V20 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp L0066 0023 V20 = SEPARATE V20
  .sharp L0066 0024 ASSIGN CV3($removed) V19
  .sharp L0067 0025 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0067 0026 T22 = FETCH_OBJ_R THIS string("tags")
  .sharp L0067 0027 SEND_VAL T22 1
  .sharp L0067 0028 V23 = DO_ICALL
  .sharp L0067 0029 ASSIGN CV4($tags) V23
  .sharp L0068 0030 T25 = FETCH_OBJ_R THIS string("states")
  .sharp L0068 0031 T26 = ASSIGN CV6($receiver#1) T25
  .sharp L0068 0032 T27 = INIT_ARRAY 4 (packed) T26 NEXT
  .sharp L0068 0033 T28 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp L0068 0034 T27 = ADD_ARRAY_ELEMENT T28 NEXT
  .sharp L0068 0035 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0068 0036 SEND_REF CV6($receiver#1) 1
  .sharp L0068 0037 SEND_VAR CV0($state) 2
  .sharp L0068 0038 V29 = DO_ICALL
  .sharp L0068 0039 SEPARATE CV6($receiver#1)
  .sharp L0068 0040 T27 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp L0068 0041 T30 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp L0068 0042 T30 = JMPNZ_EX T30 0046
  .sharp L0068 0043 T31 = ASSIGN_OBJ THIS string("states")
  .sharp L0068 0044 OP_DATA CV6($receiver#1)
  .sharp L0068 0045 T30 = BOOL T31
  .sharp L0068 0046 T27 = ADD_ARRAY_ELEMENT T30 NEXT
  .sharp L0068 0047 T32 = FETCH_DIM_R T27 int(2)
  .sharp L0068 0048 ASSIGN CV5($added) T32
  .sharp L0069 0049 T34 = FETCH_OBJ_R THIS string("states")
  .sharp L0069 0050 T35 = ASSIGN CV6($receiver#1) T34
  .sharp L0069 0051 T36 = INIT_ARRAY 4 (packed) T35 NEXT
  .sharp L0069 0052 T37 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp L0069 0053 T36 = ADD_ARRAY_ELEMENT T37 NEXT
  .sharp L0069 0054 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0069 0055 SEND_REF CV6($receiver#1) 1
  .sharp L0069 0056 SEND_VAR CV0($state) 2
  .sharp L0069 0057 V38 = DO_ICALL
  .sharp L0069 0058 SEPARATE CV6($receiver#1)
  .sharp L0069 0059 T36 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp L0069 0060 T39 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp L0069 0061 T39 = JMPNZ_EX T39 0065
  .sharp L0069 0062 T40 = ASSIGN_OBJ THIS string("states")
  .sharp L0069 0063 OP_DATA CV6($receiver#1)
  .sharp L0069 0064 T39 = BOOL T40
  .sharp L0069 0065 T36 = ADD_ARRAY_ELEMENT T39 NEXT
  .sharp L0069 0066 T41 = FETCH_DIM_R T36 int(2)
  .sharp L0069 0067 ASSIGN CV8($twice) T41
  .sharp L0070 0068 T43 = FETCH_OBJ_R THIS string("states")
  .sharp L0070 0069 T44 = ASSIGN CV6($receiver#1) T43
  .sharp L0070 0070 T45 = INIT_ARRAY 4 (packed) T44 NEXT
  .sharp L0070 0071 T46 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp L0070 0072 T45 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp L0070 0073 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp L0070 0074 SEND_REF CV6($receiver#1) 1
  .sharp L0070 0075 SEND_VAR CV0($state) 2
  .sharp L0070 0076 V47 = DO_ICALL
  .sharp L0070 0077 SEPARATE CV6($receiver#1)
  .sharp L0070 0078 T45 = ADD_ARRAY_ELEMENT V47 NEXT
  .sharp L0070 0079 T48 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp L0070 0080 T48 = JMPNZ_EX T48 0084
  .sharp L0070 0081 T49 = ASSIGN_OBJ THIS string("states")
  .sharp L0070 0082 OP_DATA CV6($receiver#1)
  .sharp L0070 0083 T48 = BOOL T49
  .sharp L0070 0084 T45 = ADD_ARRAY_ELEMENT T48 NEXT
  .sharp L0070 0085 T50 = FETCH_DIM_R T45 int(2)
  .sharp L0070 0086 ASSIGN CV9($dropped) T50
  .sharp L0071 0087 T52 = INIT_ARRAY 13 (packed) CV1($vip) NEXT
  .sharp L0071 0088 T52 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp L0071 0089 T52 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp L0071 0090 T52 = ADD_ARRAY_ELEMENT CV4($tags) NEXT
  .sharp L0071 0091 T52 = ADD_ARRAY_ELEMENT CV5($added) NEXT
  .sharp L0071 0092 T52 = ADD_ARRAY_ELEMENT CV8($twice) NEXT
  .sharp L0071 0093 T52 = ADD_ARRAY_ELEMENT CV9($dropped) NEXT
  .sharp L0071 0094 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0071 0095 T53 = FETCH_OBJ_R THIS string("states")
  .sharp L0071 0096 SEND_VAL T53 1
  .sharp L0071 0097 SEND_VAR CV0($state) 2
  .sharp L0071 0098 V54 = DO_ICALL
  .sharp L0071 0099 T52 = ADD_ARRAY_ELEMENT V54 NEXT
  .sharp L0071 0100 T55 = FETCH_OBJ_R THIS string("writes")
  .sharp L0071 0101 T52 = ADD_ARRAY_ELEMENT T55 NEXT
  .sharp L0071 0102 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0071 0103 V56 = FETCH_OBJ_W (ref) THIS string("sizes")
  .sharp L0071 0104 SEND_REF V56 1
  .sharp L0071 0105 SEND_VAL int(4) 2
  .sharp L0071 0106 V57 = DO_ICALL
  .sharp L0071 0107 V58 = FETCH_OBJ_UNSET THIS string("sizes")
  .sharp L0071 0108 V58 = SEPARATE V58
  .sharp L0071 0109 T52 = ADD_ARRAY_ELEMENT V57 NEXT
  .sharp L0071 0110 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0071 0111 T59 = FETCH_OBJ_R THIS string("sizes")
  .sharp L0071 0112 SEND_VAL T59 1
  .sharp L0071 0113 SEND_VAL int(4) 2
  .sharp L0071 0114 V60 = DO_ICALL
  .sharp L0071 0115 T52 = ADD_ARRAY_ELEMENT V60 NEXT
  .sharp L0071 0116 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0071 0117 V61 = FETCH_OBJ_W (ref) THIS string("ids")
  .sharp L0071 0118 SEND_REF V61 1
  .sharp L0071 0119 SEND_VAL int(9) 2
  .sharp L0071 0120 V62 = DO_ICALL
  .sharp L0071 0121 V63 = FETCH_OBJ_UNSET THIS string("ids")
  .sharp L0071 0122 V63 = SEPARATE V63
  .sharp L0071 0123 T52 = ADD_ARRAY_ELEMENT V62 NEXT
  .sharp L0071 0124 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0071 0125 T64 = FETCH_OBJ_R THIS string("ids")
  .sharp L0071 0126 SEND_VAL T64 1
  .sharp L0071 0127 V65 = DO_ICALL
  .sharp L0071 0128 T52 = ADD_ARRAY_ELEMENT V65 NEXT
  .sharp L0071 0129 VERIFY_RETURN_TYPE T52
  .sharp L0071 0130 RETURN T52
  .sharp L0072 0131 VERIFY_RETURN_TYPE
  .sharp L0072 0132 RETURN null
  .sharp      ; (lines=97, args=1, vars=7, tmps=41)
  .sharp L0074 0001 T7 = TYPE_CHECK (array) CV0($maybe)
  .sharp L0074 0002 JMPZ T7 0010
  .sharp L0074 0003 V8 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0074 0004 T9 = SHARP_TYPE_ARGS string("string")
  .sharp L0074 0005 V10 = NEW 1 V8 T9
  .sharp L0074 0006 SEND_VAR_EX CV0($maybe) 1
  .sharp L0074 0007 DO_FCALL
  .sharp L0074 0008 T12 = QM_ASSIGN V10
  .sharp L0074 0009 JMP 0011
  .sharp L0074 0010 T12 = QM_ASSIGN CV0($maybe)
  .sharp L0074 0011 ASSIGN CV0($maybe) T12
  .sharp L0076 0012 INIT_METHOD_CALL 0 THIS string("copy")
  .sharp L0076 0013 V14 = DO_FCALL
  .sharp L0076 0014 ASSIGN CV1($copy) V14
  .sharp L0077 0015 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0077 0016 SEND_REF CV1($copy) 1
  .sharp L0077 0017 SEND_VAL string("copy") 2
  .sharp L0077 0018 V16 = DO_ICALL
  .sharp L0077 0019 SEPARATE CV1($copy)
  .sharp L0077 0020 ASSIGN CV2($copied) V16
  .sharp L0078 0021 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0078 0022 T18 = FETCH_OBJ_R THIS string("tags")
  .sharp L0078 0023 SEND_VAL T18 1
  .sharp L0078 0024 SEND_VAL string("copy") 2
  .sharp L0078 0025 V19 = DO_ICALL
  .sharp L0078 0026 ASSIGN CV3($kept) V19
  .sharp L0079 0028 V21 = DO_FCALL
  .sharp L0079 0029 T22 = ASSIGN CV5($argument#1) V21
  .sharp L0079 0030 T23 = INIT_ARRAY 2 (packed) T22 NEXT
  .sharp L0079 0031 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0079 0032 V24 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp L0079 0033 SEND_REF V24 1
  .sharp L0079 0034 SEND_VAR CV5($argument#1) 2
  .sharp L0079 0035 V25 = DO_ICALL
  .sharp L0079 0036 V26 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp L0079 0037 V26 = SEPARATE V26
  .sharp L0079 0038 T23 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp L0079 0039 T27 = FETCH_DIM_R T23 int(1)
  .sharp L0079 0040 ASSIGN CV4($added) T27
  .sharp L0080 0041 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0080 0042 T29 = FETCH_OBJ_R THIS string("tags")
  .sharp L0080 0043 SEND_VAL T29 1
  .sharp L0080 0044 V30 = DO_ICALL
  .sharp L0080 0045 ASSIGN CV6($named) V30
  .sharp L0081 0046 INIT_FCALL 1 96 string("sharp\\internal\\clear")
  .sharp L0081 0047 V32 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp L0081 0048 SEND_REF V32 1
  .sharp L0081 0049 DO_ICALL
  .sharp L0081 0050 V34 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp L0081 0051 V34 = SEPARATE V34
  .sharp L0082 0052 T35 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .sharp L0082 0053 T35 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .sharp L0082 0054 T35 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .sharp L0082 0055 T36 = FETCH_OBJ_R THIS string("calls")
  .sharp L0082 0056 T35 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp L0082 0057 T35 = ADD_ARRAY_ELEMENT CV6($named) NEXT
  .sharp L0082 0058 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp L0082 0059 T37 = FETCH_OBJ_R THIS string("tags")
  .sharp L0082 0060 SEND_VAL T37 1
  .sharp L0082 0061 V38 = DO_ICALL
  .sharp L0082 0062 T35 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp L0082 0063 T39 = TYPE_CHECK (null) CV0($maybe)
  .sharp L0082 0064 JMPZ T39 0067
  .sharp L0082 0065 T40 = QM_ASSIGN null
  .sharp L0082 0066 JMP 0073
  .sharp L0082 0067 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp L0082 0068 SEND_REF CV0($maybe) 1
  .sharp L0082 0069 SEND_VAL string("x") 2
  .sharp L0082 0070 V41 = DO_ICALL
  .sharp L0082 0071 SEPARATE CV0($maybe)
  .sharp L0082 0072 T40 = QM_ASSIGN V41
  .sharp L0082 0073 T35 = ADD_ARRAY_ELEMENT T40 NEXT
  .sharp L0082 0074 T42 = TYPE_CHECK (null) CV0($maybe)
  .sharp L0082 0075 JMPZ T42 0078
  .sharp L0082 0076 T43 = QM_ASSIGN null
  .sharp L0082 0077 JMP 0083
  .sharp L0082 0078 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0082 0079 SEND_VAR CV0($maybe) 1
  .sharp L0082 0080 SEND_VAL string("x") 2
  .sharp L0082 0081 V44 = DO_ICALL
  .sharp L0082 0082 T43 = QM_ASSIGN V44
  .sharp L0082 0083 T35 = ADD_ARRAY_ELEMENT T43 NEXT
  .sharp L0082 0084 T45 = TYPE_CHECK (null) CV0($maybe)
  .sharp L0082 0085 JMPZ T45 0088
  .sharp L0082 0086 T46 = QM_ASSIGN null
  .sharp L0082 0087 JMP 0092
  .sharp L0082 0088 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp L0082 0089 SEND_VAR CV0($maybe) 1
  .sharp L0082 0090 V47 = DO_ICALL
  .sharp L0082 0091 T46 = QM_ASSIGN V47
  .sharp L0082 0092 T35 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp L0082 0093 VERIFY_RETURN_TYPE T35
  .sharp L0082 0094 RETURN T35
  .sharp L0083 0095 VERIFY_RETURN_TYPE
  .sharp L0083 0096 RETURN null
  .sharp      ; (lines=38, args=1, vars=4, tmps=19)
  .sharp L0100 0008 INIT_FCALL 2 112 string("sharp\\internal\\toset")
  .sharp L0100 0009 SEND_VAR CV0($names) 1
  .sharp L0100 0010 SEND_VAL string("string") 2
  .sharp L0100 0011 V9 = DO_ICALL
  .sharp L0100 0012 SEND_VAR_NO_REF_EX V9 1
  .sharp L0100 0013 V10 = DO_FCALL
  .sharp L0100 0014 T11 = INIT_ARRAY 3 (packed) V10 NEXT
  .sharp L0100 0015 INIT_DYNAMIC_CALL 1 CV2($check)
  .sharp L0100 0016 V12 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0100 0017 T13 = SHARP_TYPE_ARGS string("string")
  .sharp L0100 0018 V14 = NEW 1 V12 T13
  .sharp L0100 0019 T15 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .sharp L0100 0020 SEND_VAL_EX T15 1
  .sharp L0100 0021 DO_FCALL
  .sharp L0100 0022 SEND_VAR_NO_REF_EX V14 1
  .sharp L0100 0023 V17 = DO_FCALL
  .sharp L0100 0024 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp L0100 0025 INIT_DYNAMIC_CALL 1 CV3($size)
  .sharp L0100 0026 V18 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0100 0027 T19 = SHARP_TYPE_ARGS string("string")
  .sharp L0100 0028 V20 = NEW 1 V18 T19
  .sharp L0100 0029 SEND_VAL_EX array(...) 1
  .sharp L0100 0030 DO_FCALL
  .sharp L0100 0031 SEND_VAR_NO_REF_EX V20 1
  .sharp L0100 0032 V22 = DO_FCALL
  .sharp L0100 0033 T11 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp L0100 0034 VERIFY_RETURN_TYPE T11
  .sharp L0100 0035 RETURN T11
  .sharp L0101 0036 VERIFY_RETURN_TYPE
  .sharp L0101 0037 RETURN null
  .sharp      ; (lines=19, args=1, vars=2, tmps=8)
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
  .sharp L0096 0013 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0096 0014 SEND_VAR CV0($tags) 1
  .sharp L0096 0015 SEND_VAR CV1($prefix) 2
  .sharp L0096 0016 V9 = DO_ICALL
  .sharp L0096 0017 RETURN V9
  .sharp L0096 0018 RETURN null
  .sharp      ; (lines=17, args=1, vars=1, tmps=8)
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
  .sharp L0098 0012 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp L0098 0013 SEND_VAR CV0($tags) 1
  .sharp L0098 0014 V8 = DO_ICALL
  .sharp L0098 0015 RETURN V8
  .sharp L0099 0016 RETURN null
  .sharp      ; (lines=37, args=0, vars=5, tmps=15)
  .sharp L0103 0002 T5 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0103 0003 SEND_VAL T5 1
  .sharp L0103 0005 V6 = DO_ICALL
  .sharp L0103 0006 ASSIGN CV0($groups) V6
  .sharp L0106 0008 V9 = FE_RESET_R CV0($groups) 0032
  .sharp L0106 0009 FE_FETCH_R V9 CV2($group) 0032
  .sharp L0107 0010 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0107 0011 T10 = ASSIGN CV4($crossing#2) CV2($group)
  .sharp L0107 0012 T11 = TYPE_CHECK (array) T10
  .sharp L0107 0013 JMPZ T11 0021
  .sharp L0107 0014 V12 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0107 0015 T13 = SHARP_TYPE_ARGS string("string")
  .sharp L0107 0016 V14 = NEW 1 V12 T13
  .sharp L0107 0017 SEND_VAR_EX CV4($crossing#2) 1
  .sharp L0107 0018 DO_FCALL
  .sharp L0107 0019 T16 = QM_ASSIGN V14
  .sharp L0107 0020 JMP 0022
  .sharp L0107 0021 T16 = QM_ASSIGN CV4($crossing#2)
  .sharp L0107 0022 SEND_VAL T16 1
  .sharp L0107 0023 SEND_VAL string("a") 2
  .sharp L0107 0024 V17 = DO_ICALL
  .sharp L0107 0025 ASSIGN CV3($argument#1) V17
  .sharp L0107 0026 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0107 0027 SEND_REF CV1($found) 1
  .sharp L0107 0028 SEND_VAR CV3($argument#1) 2
  .sharp L0107 0029 DO_ICALL
  .sharp L0107 0030 SEPARATE CV1($found)
  .sharp L0106 0031 JMP 0009
  .sharp L0106 0032 FE_FREE V9
  .sharp L0109 0033 VERIFY_RETURN_TYPE CV1($found)
  .sharp L0109 0034 RETURN CV1($found)
  .sharp L0110 0035 VERIFY_RETURN_TYPE
  .sharp L0110 0036 RETURN null
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
  .sharp      ; (lines=42, args=0, vars=5, tmps=17)
  .sharp L0112 0002 T5 = DECLARE_LAMBDA_FUNCTION 0
  .sharp L0112 0003 SEND_VAL T5 1
  .sharp L0112 0005 V6 = DO_ICALL
  .sharp L0112 0006 ASSIGN CV0($groups) V6
  .sharp L0115 0008 V9 = FE_RESET_R CV0($groups) 0037
  .sharp L0115 0009 FE_FETCH_R V9 CV2($group) 0037
  .sharp L0116 0010 T10 = TYPE_CHECK (null) CV2($group)
  .sharp L0116 0011 JMPZ T10 0014
  .sharp L0116 0012 T11 = QM_ASSIGN null
  .sharp L0116 0013 JMP 0030
  .sharp L0116 0014 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp L0116 0015 T12 = ASSIGN CV4($crossing#2) CV2($group)
  .sharp L0116 0016 T13 = TYPE_CHECK (array) T12
  .sharp L0116 0017 JMPZ T13 0025
  .sharp L0116 0018 V14 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp L0116 0019 T15 = SHARP_TYPE_ARGS string("string")
  .sharp L0116 0020 V16 = NEW 1 V14 T15
  .sharp L0116 0021 SEND_VAR_EX CV4($crossing#2) 1
  .sharp L0116 0022 DO_FCALL
  .sharp L0116 0023 T18 = QM_ASSIGN V16
  .sharp L0116 0024 JMP 0026
  .sharp L0116 0025 T18 = QM_ASSIGN CV4($crossing#2)
  .sharp L0116 0026 SEND_VAL T18 1
  .sharp L0116 0027 SEND_VAL string("a") 2
  .sharp L0116 0028 V19 = DO_ICALL
  .sharp L0116 0029 T11 = QM_ASSIGN V19
  .sharp L0116 0030 ASSIGN CV3($argument#1) T11
  .sharp L0116 0031 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0116 0032 SEND_REF CV1($found) 1
  .sharp L0116 0033 SEND_VAR CV3($argument#1) 2
  .sharp L0116 0034 DO_ICALL
  .sharp L0116 0035 SEPARATE CV1($found)
  .sharp L0115 0036 JMP 0009
  .sharp L0115 0037 FE_FREE V9
  .sharp L0118 0038 VERIFY_RETURN_TYPE CV1($found)
  .sharp L0118 0039 RETURN CV1($found)
  .sharp L0119 0040 VERIFY_RETURN_TYPE
  .sharp L0119 0041 RETURN null
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
  .sharp      ; (lines=71, args=1, vars=7, tmps=25)
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
  .sharp 0012 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0013 SEND_REF CV0($tags) 1
  .sharp 0014 SEND_VAL string("vip") 2
  .sharp 0015 V14 = DO_ICALL
  .sharp 0016 SEPARATE CV0($tags)
  .sharp 0017 ASSIGN CV1($added) V14
  .sharp 0018 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0019 SEND_REF CV0($tags) 1
  .sharp 0020 SEND_VAL string("vip") 2
  .sharp 0021 V16 = DO_ICALL
  .sharp 0022 SEPARATE CV0($tags)
  .sharp 0023 ASSIGN CV2($again) V16
  .sharp 0025 SEND_REF CV0($tags) 1
  .sharp 0026 SEND_VAL string("new") 2
  .sharp 0027 V18 = DO_ICALL
  .sharp 0028 SEPARATE CV0($tags)
  .sharp 0029 ASSIGN CV3($removed) V18
  .sharp 0030 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0031 SEND_REF CV0($tags) 1
  .sharp 0032 SEND_VAL string("none") 2
  .sharp 0033 V20 = DO_ICALL
  .sharp 0034 SEPARATE CV0($tags)
  .sharp 0035 ASSIGN CV4($absent) V20
  .sharp 0036 ASSIGN CV5($joined) string("")
  .sharp 0037 V23 = FE_RESET_R CV0($tags) 0043
  .sharp 0038 FE_FETCH_R V23 CV6($tag) 0043
  .sharp 0039 T24 = CONCAT CV5($joined) CV6($tag)
  .sharp 0040 T25 = CONCAT T24 string(";")
  .sharp 0041 ASSIGN CV5($joined) T25
  .sharp 0042 JMP 0038
  .sharp 0043 FE_FREE V23
  .sharp 0044 T27 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .sharp 0045 T27 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp 0046 T27 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp 0047 T27 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .sharp 0048 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0049 SEND_VAR CV0($tags) 1
  .sharp 0050 SEND_VAL string("5") 2
  .sharp 0051 V28 = DO_ICALL
  .sharp 0052 T27 = ADD_ARRAY_ELEMENT V28 NEXT
  .sharp 0053 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0054 SEND_VAR CV0($tags) 1
  .sharp 0055 SEND_VAL string("new") 2
  .sharp 0056 V29 = DO_ICALL
  .sharp 0057 T27 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp 0058 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp 0059 SEND_VAR CV0($tags) 1
  .sharp 0060 V30 = DO_ICALL
  .sharp 0061 T27 = ADD_ARRAY_ELEMENT V30 NEXT
  .sharp 0062 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0063 SEND_VAR CV0($tags) 1
  .sharp 0064 V31 = DO_ICALL
  .sharp 0065 T27 = ADD_ARRAY_ELEMENT V31 NEXT
  .sharp 0066 T27 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .sharp 0067 VERIFY_RETURN_TYPE T27
  .sharp 0068 RETURN T27
  .sharp 0069 VERIFY_RETURN_TYPE
  .sharp 0070 RETURN null
  .sharp      10: 0006 - 0008 (new)
  .sharp      14: 0016 - 0017 (tmp/var)
  .sharp      16: 0022 - 0023 (tmp/var)
  .sharp      18: 0028 - 0029 (tmp/var)
  .sharp      20: 0034 - 0035 (tmp/var)
  .sharp      23: 0038 - 0043 (loop)
  .sharp      27: 0045 - 0068 (tmp/var)
  .sharp      ; (lines=58, args=1, vars=1, tmps=21)
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
  .sharp 0012 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0013 INIT_FCALL 2 112 string("sharp\\internal\\filterelements")
  .sharp 0014 SEND_VAR CV0($tags) 1
  .sharp 0015 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0016 SEND_VAL T8 2
  .sharp 0017 V9 = DO_ICALL
  .sharp 0018 SEND_VAR V9 1
  .sharp 0020 T11 = INIT_ARRAY 6 (packed) V10 NEXT
  .sharp 0021 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .sharp 0022 SEND_VAR CV0($tags) 1
  .sharp 0023 T12 = DECLARE_LAMBDA_FUNCTION 1
  .sharp 0024 SEND_VAL T12 2
  .sharp 0025 V13 = DO_ICALL
  .sharp 0026 T11 = ADD_ARRAY_ELEMENT V13 NEXT
  .sharp 0027 INIT_FCALL 2 112 string("sharp\\internal\\any")
  .sharp 0028 SEND_VAR CV0($tags) 1
  .sharp 0029 T14 = DECLARE_LAMBDA_FUNCTION 2
  .sharp 0030 SEND_VAL T14 2
  .sharp 0031 V15 = DO_ICALL
  .sharp 0032 T11 = ADD_ARRAY_ELEMENT V15 NEXT
  .sharp 0033 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .sharp 0034 SEND_VAR CV0($tags) 1
  .sharp 0035 T16 = DECLARE_LAMBDA_FUNCTION 3
  .sharp 0036 SEND_VAL T16 2
  .sharp 0037 V17 = DO_ICALL
  .sharp 0038 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp 0039 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .sharp 0040 SEND_VAR CV0($tags) 1
  .sharp 0041 T18 = DECLARE_LAMBDA_FUNCTION 4
  .sharp 0042 SEND_VAL T18 2
  .sharp 0043 SEND_VAL null 3
  .sharp 0044 V19 = DO_ICALL
  .sharp 0045 T11 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp 0046 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .sharp 0047 SEND_VAL_EX string(",") 1
  .sharp 0048 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .sharp 0049 SEND_VAR CV0($tags) 1
  .sharp 0050 V20 = DO_ICALL
  .sharp 0051 SEND_VAR_NO_REF_EX V20 2
  .sharp 0052 V21 = DO_FCALL
  .sharp 0053 T11 = ADD_ARRAY_ELEMENT V21 NEXT
  .sharp 0054 VERIFY_RETURN_TYPE T11
  .sharp 0055 RETURN T11
  .sharp 0056 VERIFY_RETURN_TYPE
  .sharp 0057 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      11: 0021 - 0055 (tmp/var)
  .sharp      ; (lines=21, args=1, vars=1, tmps=9)
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
  .sharp 0012 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .sharp 0013 SEND_VAR CV0($tags) 1
  .sharp 0014 T8 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0015 SEND_VAL T8 2
  .sharp 0016 V9 = DO_ICALL
  .sharp 0017 VERIFY_RETURN_TYPE V9
  .sharp 0018 RETURN V9
  .sharp 0019 VERIFY_RETURN_TYPE
  .sharp 0020 RETURN null
  .sharp      4: 0006 - 0008 (new)
  .sharp      9: 0017 - 0018 (tmp/var)
  .sharp      ; (lines=59, args=1, vars=2, tmps=21)
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
  .sharp 0018 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0019 SEND_REF CV0($ids) 1
  .sharp 0020 SEND_VAL int(4) 2
  .sharp 0021 V14 = DO_ICALL
  .sharp 0022 SEPARATE CV0($ids)
  .sharp 0023 T15 = INIT_ARRAY 7 (packed) V14 NEXT
  .sharp 0026 SEND_VAL int(4) 2
  .sharp 0027 V16 = DO_ICALL
  .sharp 0028 SEPARATE CV0($ids)
  .sharp 0029 T15 = ADD_ARRAY_ELEMENT V16 NEXT
  .sharp 0030 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0031 SEND_REF CV0($ids) 1
  .sharp 0032 SEND_VAL int(7) 2
  .sharp 0033 V17 = DO_ICALL
  .sharp 0034 SEPARATE CV0($ids)
  .sharp 0035 T15 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp 0036 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0037 SEND_VAR CV0($ids) 1
  .sharp 0038 SEND_VAL int(2) 2
  .sharp 0039 V18 = DO_ICALL
  .sharp 0040 T15 = ADD_ARRAY_ELEMENT V18 NEXT
  .sharp 0041 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0042 SEND_VAR CV0($ids) 1
  .sharp 0043 V19 = DO_ICALL
  .sharp 0044 T15 = ADD_ARRAY_ELEMENT V19 NEXT
  .sharp 0045 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0046 SEND_VAR CV1($more) 1
  .sharp 0047 V20 = DO_ICALL
  .sharp 0048 T15 = ADD_ARRAY_ELEMENT V20 NEXT
  .sharp 0049 INIT_FCALL 2 112 string("sharp\\internal\\sumof")
  .sharp 0050 SEND_VAR CV0($ids) 1
  .sharp 0051 T21 = DECLARE_LAMBDA_FUNCTION 0
  .sharp 0052 SEND_VAL T21 2
  .sharp 0053 V22 = DO_ICALL
  .sharp 0054 T15 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp 0055 VERIFY_RETURN_TYPE T15
  .sharp 0056 RETURN T15
  .sharp 0057 VERIFY_RETURN_TYPE
  .sharp 0058 RETURN null
  .sharp      5: 0006 - 0008 (new)
  .sharp      11: 0015 - 0017 (new)
  .sharp      14: 0022 - 0023 (tmp/var)
  .sharp      15: 0024 - 0056 (tmp/var)
  .sharp      16: 0028 - 0029 (tmp/var)
  .sharp      17: 0034 - 0035 (tmp/var)
  .sharp      ; (lines=73, args=1, vars=6, tmps=33)
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
  .sharp 0025 SEND_REF CV0($states) 1
  .sharp 0026 T22 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0027 SEND_VAL T22 2
  .sharp 0028 V23 = DO_ICALL
  .sharp 0029 SEPARATE CV0($states)
  .sharp 0030 ASSIGN CV2($closed) V23
  .sharp 0031 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0032 SEND_REF CV0($states) 1
  .sharp 0033 T25 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0034 SEND_VAL T25 2
  .sharp 0035 V26 = DO_ICALL
  .sharp 0036 SEPARATE CV0($states)
  .sharp 0037 ASSIGN CV3($active) V26
  .sharp 0038 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0039 SEND_VAR CV0($states) 1
  .sharp 0040 T28 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .sharp 0041 SEND_VAL T28 2
  .sharp 0042 V29 = DO_ICALL
  .sharp 0043 ASSIGN CV4($has) V29
  .sharp 0044 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0045 SEND_REF CV0($states) 1
  .sharp 0046 T31 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .sharp 0047 SEND_VAL T31 2
  .sharp 0048 V32 = DO_ICALL
  .sharp 0049 SEPARATE CV0($states)
  .sharp 0050 ASSIGN CV5($removed) V32
  .sharp 0051 T34 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .sharp 0052 T34 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .sharp 0053 T34 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .sharp 0054 T34 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .sharp 0055 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0056 SEND_VAR CV0($states) 1
  .sharp 0057 V35 = DO_ICALL
  .sharp 0058 T34 = ADD_ARRAY_ELEMENT V35 NEXT
  .sharp 0059 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0060 SEND_VAR CV1($sizes) 1
  .sharp 0061 V36 = DO_ICALL
  .sharp 0062 T34 = ADD_ARRAY_ELEMENT V36 NEXT
  .sharp 0063 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0064 SEND_VAR CV1($sizes) 1
  .sharp 0065 T37 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .sharp 0066 SEND_VAL T37 2
  .sharp 0067 V38 = DO_ICALL
  .sharp 0068 T34 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp 0069 VERIFY_RETURN_TYPE T34
  .sharp 0070 RETURN T34
  .sharp 0071 VERIFY_RETURN_TYPE
  .sharp 0072 RETURN null
  .sharp      9: 0006 - 0008 (new)
  .sharp      15: 0015 - 0023 (new)
  .sharp      17: 0017 - 0021 (tmp/var)
  .sharp      23: 0029 - 0030 (tmp/var)
  .sharp      26: 0036 - 0037 (tmp/var)
  .sharp      32: 0049 - 0050 (tmp/var)
  .sharp      34: 0052 - 0070 (tmp/var)
  .sharp      ; (lines=133, args=1, vars=10, tmps=56)
  .sharp 0002 V10 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0003 SEND_REF V10 1
  .sharp 0005 V11 = DO_ICALL
  .sharp 0006 V12 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0007 V12 = SEPARATE V12
  .sharp 0008 ASSIGN CV1($vip) V11
  .sharp 0009 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0010 V14 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0011 SEND_REF V14 1
  .sharp 0013 V15 = DO_ICALL
  .sharp 0014 V16 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0015 V16 = SEPARATE V16
  .sharp 0016 ASSIGN CV2($again) V15
  .sharp 0017 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0018 V18 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0019 SEND_REF V18 1
  .sharp 0020 SEND_VAL string("new") 2
  .sharp 0021 V19 = DO_ICALL
  .sharp 0022 V20 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0023 V20 = SEPARATE V20
  .sharp 0024 ASSIGN CV3($removed) V19
  .sharp 0025 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0026 T22 = FETCH_OBJ_R THIS string("tags")
  .sharp 0027 SEND_VAL T22 1
  .sharp 0028 V23 = DO_ICALL
  .sharp 0029 ASSIGN CV4($tags) V23
  .sharp 0030 T25 = FETCH_OBJ_R THIS string("states")
  .sharp 0031 T26 = ASSIGN CV6($receiver#1) T25
  .sharp 0032 T27 = INIT_ARRAY 4 (packed) T26 NEXT
  .sharp 0033 T28 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp 0034 T27 = ADD_ARRAY_ELEMENT T28 NEXT
  .sharp 0035 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0036 SEND_REF CV6($receiver#1) 1
  .sharp 0037 SEND_VAR CV0($state) 2
  .sharp 0038 V29 = DO_ICALL
  .sharp 0039 SEPARATE CV6($receiver#1)
  .sharp 0040 T27 = ADD_ARRAY_ELEMENT V29 NEXT
  .sharp 0041 T30 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp 0042 T30 = JMPNZ_EX T30 0046
  .sharp 0043 T31 = ASSIGN_OBJ THIS string("states")
  .sharp 0044 OP_DATA CV6($receiver#1)
  .sharp 0045 T30 = BOOL T31
  .sharp 0046 T27 = ADD_ARRAY_ELEMENT T30 NEXT
  .sharp 0047 T32 = FETCH_DIM_R T27 int(2)
  .sharp 0048 ASSIGN CV5($added) T32
  .sharp 0049 T34 = FETCH_OBJ_R THIS string("states")
  .sharp 0050 T35 = ASSIGN CV6($receiver#1) T34
  .sharp 0051 T36 = INIT_ARRAY 4 (packed) T35 NEXT
  .sharp 0052 T37 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp 0053 T36 = ADD_ARRAY_ELEMENT T37 NEXT
  .sharp 0054 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0055 SEND_REF CV6($receiver#1) 1
  .sharp 0056 SEND_VAR CV0($state) 2
  .sharp 0057 V38 = DO_ICALL
  .sharp 0058 SEPARATE CV6($receiver#1)
  .sharp 0059 T36 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp 0060 T39 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp 0061 T39 = JMPNZ_EX T39 0065
  .sharp 0062 T40 = ASSIGN_OBJ THIS string("states")
  .sharp 0063 OP_DATA CV6($receiver#1)
  .sharp 0064 T39 = BOOL T40
  .sharp 0065 T36 = ADD_ARRAY_ELEMENT T39 NEXT
  .sharp 0066 T41 = FETCH_DIM_R T36 int(2)
  .sharp 0067 ASSIGN CV8($twice) T41
  .sharp 0068 T43 = FETCH_OBJ_R THIS string("states")
  .sharp 0069 T44 = ASSIGN CV6($receiver#1) T43
  .sharp 0070 T45 = INIT_ARRAY 4 (packed) T44 NEXT
  .sharp 0071 T46 = ASSIGN CV7($kept#2) CV6($receiver#1)
  .sharp 0072 T45 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp 0073 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .sharp 0074 SEND_REF CV6($receiver#1) 1
  .sharp 0075 SEND_VAR CV0($state) 2
  .sharp 0076 V47 = DO_ICALL
  .sharp 0077 SEPARATE CV6($receiver#1)
  .sharp 0078 T45 = ADD_ARRAY_ELEMENT V47 NEXT
  .sharp 0079 T48 = IS_IDENTICAL CV6($receiver#1) CV7($kept#2)
  .sharp 0080 T48 = JMPNZ_EX T48 0084
  .sharp 0081 T49 = ASSIGN_OBJ THIS string("states")
  .sharp 0082 OP_DATA CV6($receiver#1)
  .sharp 0083 T48 = BOOL T49
  .sharp 0084 T45 = ADD_ARRAY_ELEMENT T48 NEXT
  .sharp 0085 T50 = FETCH_DIM_R T45 int(2)
  .sharp 0086 ASSIGN CV9($dropped) T50
  .sharp 0087 T52 = INIT_ARRAY 13 (packed) CV1($vip) NEXT
  .sharp 0088 T52 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .sharp 0089 T52 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .sharp 0090 T52 = ADD_ARRAY_ELEMENT CV4($tags) NEXT
  .sharp 0091 T52 = ADD_ARRAY_ELEMENT CV5($added) NEXT
  .sharp 0092 T52 = ADD_ARRAY_ELEMENT CV8($twice) NEXT
  .sharp 0093 T52 = ADD_ARRAY_ELEMENT CV9($dropped) NEXT
  .sharp 0094 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0095 T53 = FETCH_OBJ_R THIS string("states")
  .sharp 0096 SEND_VAL T53 1
  .sharp 0097 SEND_VAR CV0($state) 2
  .sharp 0098 V54 = DO_ICALL
  .sharp 0099 T52 = ADD_ARRAY_ELEMENT V54 NEXT
  .sharp 0100 T55 = FETCH_OBJ_R THIS string("writes")
  .sharp 0101 T52 = ADD_ARRAY_ELEMENT T55 NEXT
  .sharp 0102 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0103 V56 = FETCH_OBJ_W (ref) THIS string("sizes")
  .sharp 0104 SEND_REF V56 1
  .sharp 0105 SEND_VAL int(4) 2
  .sharp 0106 V57 = DO_ICALL
  .sharp 0107 V58 = FETCH_OBJ_UNSET THIS string("sizes")
  .sharp 0108 V58 = SEPARATE V58
  .sharp 0109 T52 = ADD_ARRAY_ELEMENT V57 NEXT
  .sharp 0110 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0111 T59 = FETCH_OBJ_R THIS string("sizes")
  .sharp 0112 SEND_VAL T59 1
  .sharp 0113 SEND_VAL int(4) 2
  .sharp 0114 V60 = DO_ICALL
  .sharp 0115 T52 = ADD_ARRAY_ELEMENT V60 NEXT
  .sharp 0116 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0117 V61 = FETCH_OBJ_W (ref) THIS string("ids")
  .sharp 0118 SEND_REF V61 1
  .sharp 0119 SEND_VAL int(9) 2
  .sharp 0120 V62 = DO_ICALL
  .sharp 0121 V63 = FETCH_OBJ_UNSET THIS string("ids")
  .sharp 0122 V63 = SEPARATE V63
  .sharp 0123 T52 = ADD_ARRAY_ELEMENT V62 NEXT
  .sharp 0124 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0125 T64 = FETCH_OBJ_R THIS string("ids")
  .sharp 0126 SEND_VAL T64 1
  .sharp 0127 V65 = DO_ICALL
  .sharp 0128 T52 = ADD_ARRAY_ELEMENT V65 NEXT
  .sharp 0129 VERIFY_RETURN_TYPE T52
  .sharp 0130 RETURN T52
  .sharp 0131 VERIFY_RETURN_TYPE
  .sharp 0132 RETURN null
  .sharp      11: 0006 - 0008 (tmp/var)
  .sharp      15: 0014 - 0016 (tmp/var)
  .sharp      19: 0022 - 0024 (tmp/var)
  .sharp      27: 0033 - 0047 (tmp/var)
  .sharp      29: 0039 - 0040 (tmp/var)
  .sharp      31: 0044 - 0045 (tmp/var)
  .sharp      36: 0052 - 0066 (tmp/var)
  .sharp      38: 0058 - 0059 (tmp/var)
  .sharp      40: 0063 - 0064 (tmp/var)
  .sharp      45: 0071 - 0085 (tmp/var)
  .sharp      47: 0077 - 0078 (tmp/var)
  .sharp      49: 0082 - 0083 (tmp/var)
  .sharp      52: 0088 - 0130 (tmp/var)
  .sharp      57: 0107 - 0109 (tmp/var)
  .sharp      62: 0121 - 0123 (tmp/var)
  .sharp      ; (lines=97, args=1, vars=7, tmps=41)
  .sharp 0001 T7 = TYPE_CHECK (array) CV0($maybe)
  .sharp 0002 JMPZ T7 0010
  .sharp 0003 V8 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0004 T9 = SHARP_TYPE_ARGS string("string")
  .sharp 0005 V10 = NEW 1 V8 T9
  .sharp 0006 SEND_VAR_EX CV0($maybe) 1
  .sharp 0007 DO_FCALL
  .sharp 0008 T12 = QM_ASSIGN V10
  .sharp 0009 JMP 0011
  .sharp 0010 T12 = QM_ASSIGN CV0($maybe)
  .sharp 0011 ASSIGN CV0($maybe) T12
  .sharp 0012 INIT_METHOD_CALL 0 THIS string("copy")
  .sharp 0013 V14 = DO_FCALL
  .sharp 0014 ASSIGN CV1($copy) V14
  .sharp 0016 SEND_REF CV1($copy) 1
  .sharp 0017 SEND_VAL string("copy") 2
  .sharp 0018 V16 = DO_ICALL
  .sharp 0019 SEPARATE CV1($copy)
  .sharp 0020 ASSIGN CV2($copied) V16
  .sharp 0021 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0022 T18 = FETCH_OBJ_R THIS string("tags")
  .sharp 0023 SEND_VAL T18 1
  .sharp 0024 SEND_VAL string("copy") 2
  .sharp 0025 V19 = DO_ICALL
  .sharp 0026 ASSIGN CV3($kept) V19
  .sharp 0028 V21 = DO_FCALL
  .sharp 0029 T22 = ASSIGN CV5($argument#1) V21
  .sharp 0030 T23 = INIT_ARRAY 2 (packed) T22 NEXT
  .sharp 0031 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0032 V24 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0033 SEND_REF V24 1
  .sharp 0034 SEND_VAR CV5($argument#1) 2
  .sharp 0035 V25 = DO_ICALL
  .sharp 0036 V26 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0037 V26 = SEPARATE V26
  .sharp 0038 T23 = ADD_ARRAY_ELEMENT V25 NEXT
  .sharp 0039 T27 = FETCH_DIM_R T23 int(1)
  .sharp 0040 ASSIGN CV4($added) T27
  .sharp 0041 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0042 T29 = FETCH_OBJ_R THIS string("tags")
  .sharp 0043 SEND_VAL T29 1
  .sharp 0044 V30 = DO_ICALL
  .sharp 0045 ASSIGN CV6($named) V30
  .sharp 0046 INIT_FCALL 1 96 string("sharp\\internal\\clear")
  .sharp 0047 V32 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0048 SEND_REF V32 1
  .sharp 0049 DO_ICALL
  .sharp 0050 V34 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0051 V34 = SEPARATE V34
  .sharp 0052 T35 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .sharp 0053 T35 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .sharp 0054 T35 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .sharp 0055 T36 = FETCH_OBJ_R THIS string("calls")
  .sharp 0056 T35 = ADD_ARRAY_ELEMENT T36 NEXT
  .sharp 0057 T35 = ADD_ARRAY_ELEMENT CV6($named) NEXT
  .sharp 0058 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .sharp 0059 T37 = FETCH_OBJ_R THIS string("tags")
  .sharp 0060 SEND_VAL T37 1
  .sharp 0061 V38 = DO_ICALL
  .sharp 0062 T35 = ADD_ARRAY_ELEMENT V38 NEXT
  .sharp 0063 T39 = TYPE_CHECK (null) CV0($maybe)
  .sharp 0064 JMPZ T39 0067
  .sharp 0065 T40 = QM_ASSIGN null
  .sharp 0066 JMP 0073
  .sharp 0067 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .sharp 0068 SEND_REF CV0($maybe) 1
  .sharp 0069 SEND_VAL string("x") 2
  .sharp 0070 V41 = DO_ICALL
  .sharp 0071 SEPARATE CV0($maybe)
  .sharp 0072 T40 = QM_ASSIGN V41
  .sharp 0073 T35 = ADD_ARRAY_ELEMENT T40 NEXT
  .sharp 0074 T42 = TYPE_CHECK (null) CV0($maybe)
  .sharp 0075 JMPZ T42 0078
  .sharp 0076 T43 = QM_ASSIGN null
  .sharp 0077 JMP 0083
  .sharp 0078 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0079 SEND_VAR CV0($maybe) 1
  .sharp 0080 SEND_VAL string("x") 2
  .sharp 0081 V44 = DO_ICALL
  .sharp 0082 T43 = QM_ASSIGN V44
  .sharp 0083 T35 = ADD_ARRAY_ELEMENT T43 NEXT
  .sharp 0084 T45 = TYPE_CHECK (null) CV0($maybe)
  .sharp 0085 JMPZ T45 0088
  .sharp 0086 T46 = QM_ASSIGN null
  .sharp 0087 JMP 0092
  .sharp 0088 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp 0089 SEND_VAR CV0($maybe) 1
  .sharp 0090 V47 = DO_ICALL
  .sharp 0091 T46 = QM_ASSIGN V47
  .sharp 0092 T35 = ADD_ARRAY_ELEMENT T46 NEXT
  .sharp 0093 VERIFY_RETURN_TYPE T35
  .sharp 0094 RETURN T35
  .sharp 0095 VERIFY_RETURN_TYPE
  .sharp 0096 RETURN null
  .sharp      10: 0006 - 0008 (new)
  .sharp      16: 0019 - 0020 (tmp/var)
  .sharp      23: 0031 - 0039 (tmp/var)
  .sharp      25: 0036 - 0038 (tmp/var)
  .sharp      35: 0053 - 0094 (tmp/var)
  .sharp      41: 0071 - 0072 (tmp/var)
  .sharp      ; (lines=38, args=1, vars=4, tmps=19)
  .sharp 0008 INIT_FCALL 2 112 string("sharp\\internal\\toset")
  .sharp 0009 SEND_VAR CV0($names) 1
  .sharp 0010 SEND_VAL string("string") 2
  .sharp 0011 V9 = DO_ICALL
  .sharp 0012 SEND_VAR_NO_REF_EX V9 1
  .sharp 0013 V10 = DO_FCALL
  .sharp 0014 T11 = INIT_ARRAY 3 (packed) V10 NEXT
  .sharp 0015 INIT_DYNAMIC_CALL 1 CV2($check)
  .sharp 0016 V12 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0017 T13 = SHARP_TYPE_ARGS string("string")
  .sharp 0018 V14 = NEW 1 V12 T13
  .sharp 0019 T15 = INIT_ARRAY 1 (packed) CV1($prefix) NEXT
  .sharp 0020 SEND_VAL_EX T15 1
  .sharp 0021 DO_FCALL
  .sharp 0022 SEND_VAR_NO_REF_EX V14 1
  .sharp 0023 V17 = DO_FCALL
  .sharp 0024 T11 = ADD_ARRAY_ELEMENT V17 NEXT
  .sharp 0025 INIT_DYNAMIC_CALL 1 CV3($size)
  .sharp 0026 V18 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0027 T19 = SHARP_TYPE_ARGS string("string")
  .sharp 0028 V20 = NEW 1 V18 T19
  .sharp 0029 SEND_VAL_EX array(...) 1
  .sharp 0030 DO_FCALL
  .sharp 0031 SEND_VAR_NO_REF_EX V20 1
  .sharp 0032 V22 = DO_FCALL
  .sharp 0033 T11 = ADD_ARRAY_ELEMENT V22 NEXT
  .sharp 0034 VERIFY_RETURN_TYPE T11
  .sharp 0035 RETURN T11
  .sharp 0036 VERIFY_RETURN_TYPE
  .sharp 0037 RETURN null
  .sharp      11: 0015 - 0035 (tmp/var)
  .sharp      14: 0019 - 0022 (new)
  .sharp      20: 0029 - 0031 (new)
  .sharp      ; (lines=19, args=1, vars=2, tmps=8)
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
  .sharp 0013 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0014 SEND_VAR CV0($tags) 1
  .sharp 0015 SEND_VAR CV1($prefix) 2
  .sharp 0016 V9 = DO_ICALL
  .sharp 0017 RETURN V9
  .sharp      5: 0007 - 0009 (new)
  .sharp      ; (lines=17, args=1, vars=1, tmps=8)
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
  .sharp 0012 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .sharp 0013 SEND_VAR CV0($tags) 1
  .sharp 0014 V8 = DO_ICALL
  .sharp 0015 RETURN V8
  .sharp      4: 0006 - 0008 (new)
  .sharp      ; (lines=37, args=0, vars=5, tmps=15)
  .sharp 0003 SEND_VAL T5 1
  .sharp 0005 V6 = DO_ICALL
  .sharp 0006 ASSIGN CV0($groups) V6
  .sharp 0008 V9 = FE_RESET_R CV0($groups) 0032
  .sharp 0009 FE_FETCH_R V9 CV2($group) 0032
  .sharp 0010 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0011 T10 = ASSIGN CV4($crossing#2) CV2($group)
  .sharp 0012 T11 = TYPE_CHECK (array) T10
  .sharp 0013 JMPZ T11 0021
  .sharp 0014 V12 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0015 T13 = SHARP_TYPE_ARGS string("string")
  .sharp 0016 V14 = NEW 1 V12 T13
  .sharp 0017 SEND_VAR_EX CV4($crossing#2) 1
  .sharp 0019 T16 = QM_ASSIGN V14
  .sharp 0020 JMP 0022
  .sharp 0021 T16 = QM_ASSIGN CV4($crossing#2)
  .sharp 0022 SEND_VAL T16 1
  .sharp 0023 SEND_VAL string("a") 2
  .sharp 0024 V17 = DO_ICALL
  .sharp 0025 ASSIGN CV3($argument#1) V17
  .sharp 0026 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0027 SEND_REF CV1($found) 1
  .sharp 0028 SEND_VAR CV3($argument#1) 2
  .sharp 0029 DO_ICALL
  .sharp 0030 SEPARATE CV1($found)
  .sharp 0031 JMP 0009
  .sharp 0032 FE_FREE V9
  .sharp 0033 VERIFY_RETURN_TYPE CV1($found)
  .sharp 0034 RETURN CV1($found)
  .sharp 0035 VERIFY_RETURN_TYPE
  .sharp 0036 RETURN null
  .sharp      9: 0009 - 0032 (loop)
  .sharp      14: 0017 - 0019 (new)
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
  .sharp      ; (lines=42, args=0, vars=5, tmps=17)
  .sharp 0003 SEND_VAL T5 1
  .sharp 0005 V6 = DO_ICALL
  .sharp 0006 ASSIGN CV0($groups) V6
  .sharp 0008 V9 = FE_RESET_R CV0($groups) 0037
  .sharp 0009 FE_FETCH_R V9 CV2($group) 0037
  .sharp 0010 T10 = TYPE_CHECK (null) CV2($group)
  .sharp 0011 JMPZ T10 0014
  .sharp 0012 T11 = QM_ASSIGN null
  .sharp 0013 JMP 0030
  .sharp 0014 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .sharp 0015 T12 = ASSIGN CV4($crossing#2) CV2($group)
  .sharp 0016 T13 = TYPE_CHECK (array) T12
  .sharp 0017 JMPZ T13 0025
  .sharp 0018 V14 = FETCH_CLASS (exception) string("Sharp\\Set")
  .sharp 0019 T15 = SHARP_TYPE_ARGS string("string")
  .sharp 0020 V16 = NEW 1 V14 T15
  .sharp 0021 SEND_VAR_EX CV4($crossing#2) 1
  .sharp 0022 DO_FCALL
  .sharp 0023 T18 = QM_ASSIGN V16
  .sharp 0024 JMP 0026
  .sharp 0025 T18 = QM_ASSIGN CV4($crossing#2)
  .sharp 0026 SEND_VAL T18 1
  .sharp 0027 SEND_VAL string("a") 2
  .sharp 0028 V19 = DO_ICALL
  .sharp 0029 T11 = QM_ASSIGN V19
  .sharp 0030 ASSIGN CV3($argument#1) T11
  .sharp 0031 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0032 SEND_REF CV1($found) 1
  .sharp 0033 SEND_VAR CV3($argument#1) 2
  .sharp 0034 DO_ICALL
  .sharp 0035 SEPARATE CV1($found)
  .sharp 0036 JMP 0009
  .sharp 0037 FE_FREE V9
  .sharp 0038 VERIFY_RETURN_TYPE CV1($found)
  .sharp 0039 RETURN CV1($found)
  .sharp 0040 VERIFY_RETURN_TYPE
  .sharp 0041 RETURN null
  .sharp      9: 0009 - 0037 (loop)
  .sharp      16: 0021 - 0023 (new)
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
  .php        ; (lines=65, args=1, vars=7, tmps=23)
  .php   L0019 0002 JMPZ T7 0008
  .php   L0019 0003 V8 = NEW 1 string("Sharp\\Set")
  .php   L0019 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0019 0005 DO_FCALL
  .php   L0019 0006 T10 = QM_ASSIGN V8
  .php   L0019 0007 JMP 0009
  .php   L0019 0008 T10 = QM_ASSIGN CV0($tags)
  .php   L0019 0009 ASSIGN CV0($tags) T10
  .php   L0021 0010 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0021 0011 SEND_REF CV0($tags) 1
  .php   L0021 0012 SEND_VAL string("vip") 2
  .php   L0021 0013 V12 = DO_ICALL
  .php   L0021 0014 ASSIGN CV1($added) V12
  .php   L0022 0015 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0022 0016 SEND_REF CV0($tags) 1
  .php   L0022 0017 SEND_VAL string("vip") 2
  .php   L0022 0018 V14 = DO_ICALL
  .php   L0022 0019 ASSIGN CV2($again) V14
  .php   L0023 0020 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0023 0021 SEND_REF CV0($tags) 1
  .php   L0023 0022 SEND_VAL string("new") 2
  .php   L0023 0023 V16 = DO_ICALL
  .php   L0023 0024 ASSIGN CV3($removed) V16
  .php   L0024 0025 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0024 0026 SEND_REF CV0($tags) 1
  .php   L0024 0027 SEND_VAL string("none") 2
  .php   L0024 0028 V18 = DO_ICALL
  .php   L0024 0029 ASSIGN CV4($absent) V18
  .php   L0025 0030 ASSIGN CV5($joined) string("")
  .php   L0026 0031 V21 = FE_RESET_R CV0($tags) 0037
  .php   L0026 0032 FE_FETCH_R V21 CV6($tag) 0037
  .php   L0027 0033 T22 = CONCAT CV5($joined) CV6($tag)
  .php   L0027 0034 T23 = CONCAT T22 string(";")
  .php   L0027 0035 ASSIGN CV5($joined) T23
  .php   L0026 0036 JMP 0032
  .php   L0026 0037 FE_FREE V21
  .php   L0029 0038 T25 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .php   L0029 0039 T25 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   L0029 0040 T25 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   L0029 0041 T25 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .php   L0029 0042 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0029 0043 SEND_VAR CV0($tags) 1
  .php   L0029 0044 SEND_VAL string("5") 2
  .php   L0029 0045 V26 = DO_ICALL
  .php   L0029 0046 T25 = ADD_ARRAY_ELEMENT V26 NEXT
  .php   L0029 0047 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0029 0048 SEND_VAR CV0($tags) 1
  .php   L0029 0049 SEND_VAL string("new") 2
  .php   L0029 0050 V27 = DO_ICALL
  .php   L0029 0051 T25 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   L0029 0052 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   L0029 0053 SEND_VAR CV0($tags) 1
  .php   L0029 0054 V28 = DO_ICALL
  .php   L0029 0055 T25 = ADD_ARRAY_ELEMENT V28 NEXT
  .php   L0029 0056 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0029 0057 SEND_VAR CV0($tags) 1
  .php   L0029 0058 V29 = DO_ICALL
  .php   L0029 0059 T25 = ADD_ARRAY_ELEMENT V29 NEXT
  .php   L0029 0060 T25 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .php   L0029 0061 VERIFY_RETURN_TYPE T25
  .php   L0029 0062 RETURN T25
  .php   L0030 0063 VERIFY_RETURN_TYPE
  .php   L0030 0064 RETURN null
  .php        ; (lines=56, args=1, vars=1, tmps=19)
  .php   L0032 0002 JMPZ T1 0008
  .php   L0032 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0032 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0032 0005 DO_FCALL
  .php   L0032 0006 T4 = QM_ASSIGN V2
  .php   L0032 0007 JMP 0009
  .php   L0032 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0032 0009 ASSIGN CV0($tags) T4
  .php   L0035 0010 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0035 0011 INIT_FCALL 2 112 string("sharp\\internal\\filterelements")
  .php   L0035 0012 SEND_VAR CV0($tags) 1
  .php   L0035 0013 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0035 0014 SEND_VAL T6 2
  .php   L0035 0015 V7 = DO_ICALL
  .php   L0035 0016 SEND_VAR V7 1
  .php   L0035 0017 V8 = DO_ICALL
  .php   L0035 0018 T9 = INIT_ARRAY 6 (packed) V8 NEXT
  .php   L0036 0019 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   L0036 0020 SEND_VAR CV0($tags) 1
  .php   L0036 0021 T10 = DECLARE_LAMBDA_FUNCTION 1
  .php   L0036 0022 SEND_VAL T10 2
  .php   L0036 0023 V11 = DO_ICALL
  .php   L0036 0024 T9 = ADD_ARRAY_ELEMENT V11 NEXT
  .php   L0037 0025 INIT_FCALL 2 112 string("sharp\\internal\\any")
  .php   L0037 0026 SEND_VAR CV0($tags) 1
  .php   L0037 0027 T12 = DECLARE_LAMBDA_FUNCTION 2
  .php   L0037 0028 SEND_VAL T12 2
  .php   L0037 0029 V13 = DO_ICALL
  .php   L0037 0030 T9 = ADD_ARRAY_ELEMENT V13 NEXT
  .php   L0038 0031 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .php   L0038 0032 SEND_VAR CV0($tags) 1
  .php   L0038 0033 T14 = DECLARE_LAMBDA_FUNCTION 3
  .php   L0038 0034 SEND_VAL T14 2
  .php   L0038 0035 V15 = DO_ICALL
  .php   L0038 0036 T9 = ADD_ARRAY_ELEMENT V15 NEXT
  .php   L0039 0037 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   L0039 0038 SEND_VAR CV0($tags) 1
  .php   L0039 0039 T16 = DECLARE_LAMBDA_FUNCTION 4
  .php   L0039 0040 SEND_VAL T16 2
  .php   L0039 0041 SEND_VAL null 3
  .php   L0039 0042 V17 = DO_ICALL
  .php   L0039 0043 T9 = ADD_ARRAY_ELEMENT V17 NEXT
  .php   L0040 0044 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   L0040 0045 SEND_VAL_EX string(",") 1
  .php   L0040 0046 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   L0040 0047 SEND_VAR CV0($tags) 1
  .php   L0040 0048 V18 = DO_ICALL
  .php   L0040 0049 SEND_VAR_NO_REF_EX V18 2
  .php   L0040 0050 V19 = DO_FCALL
  .php   L0040 0051 T9 = ADD_ARRAY_ELEMENT V19 NEXT
  .php   L0040 0052 VERIFY_RETURN_TYPE T9
  .php   L0040 0053 RETURN T9
  .php   L0042 0054 VERIFY_RETURN_TYPE
  .php   L0042 0055 RETURN null
  .php        ; (lines=19, args=1, vars=1, tmps=7)
  .php   L0044 0002 JMPZ T1 0008
  .php   L0044 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0044 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0044 0005 DO_FCALL
  .php   L0044 0006 T4 = QM_ASSIGN V2
  .php   L0044 0007 JMP 0009
  .php   L0044 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0044 0009 ASSIGN CV0($tags) T4
  .php   L0044 0010 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .php   L0044 0011 SEND_VAR CV0($tags) 1
  .php   L0044 0012 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0044 0013 SEND_VAL T6 2
  .php   L0044 0014 V7 = DO_ICALL
  .php   L0044 0015 VERIFY_RETURN_TYPE V7
  .php   L0044 0016 RETURN V7
  .php   L0044 0017 VERIFY_RETURN_TYPE
  .php   L0044 0018 RETURN null
  .php        ; (lines=52, args=1, vars=2, tmps=17)
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
  .php   L0049 0014 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0049 0015 SEND_REF CV0($ids) 1
  .php   L0049 0016 SEND_VAL int(4) 2
  .php   L0049 0017 V10 = DO_ICALL
  .php   L0049 0018 T11 = INIT_ARRAY 7 (packed) V10 NEXT
  .php   L0049 0019 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0049 0020 SEND_REF CV0($ids) 1
  .php   L0049 0021 SEND_VAL int(4) 2
  .php   L0049 0022 V12 = DO_ICALL
  .php   L0049 0023 T11 = ADD_ARRAY_ELEMENT V12 NEXT
  .php   L0049 0024 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0049 0026 SEND_VAL int(7) 2
  .php   L0049 0027 V13 = DO_ICALL
  .php   L0049 0028 T11 = ADD_ARRAY_ELEMENT V13 NEXT
  .php   L0049 0029 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0049 0030 SEND_VAR CV0($ids) 1
  .php   L0049 0031 SEND_VAL int(2) 2
  .php   L0049 0032 V14 = DO_ICALL
  .php   L0049 0033 T11 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   L0049 0034 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0049 0035 SEND_VAR CV0($ids) 1
  .php   L0049 0036 V15 = DO_ICALL
  .php   L0049 0037 T11 = ADD_ARRAY_ELEMENT V15 NEXT
  .php   L0049 0038 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0049 0039 SEND_VAR CV1($more) 1
  .php   L0049 0040 V16 = DO_ICALL
  .php   L0049 0041 T11 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   L0049 0042 INIT_FCALL 2 112 string("sharp\\internal\\sumof")
  .php   L0049 0043 SEND_VAR CV0($ids) 1
  .php   L0049 0044 T17 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0049 0045 SEND_VAL T17 2
  .php   L0049 0046 V18 = DO_ICALL
  .php   L0049 0047 T11 = ADD_ARRAY_ELEMENT V18 NEXT
  .php   L0049 0048 VERIFY_RETURN_TYPE T11
  .php   L0049 0049 RETURN T11
  .php   L0050 0050 VERIFY_RETURN_TYPE
  .php   L0050 0051 RETURN null
  .php        ; (lines=66, args=1, vars=6, tmps=29)
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
  .php   L0055 0020 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0055 0021 SEND_REF CV0($states) 1
  .php   L0055 0022 T18 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0055 0023 SEND_VAL T18 2
  .php   L0055 0024 V19 = DO_ICALL
  .php   L0055 0025 ASSIGN CV2($closed) V19
  .php   L0056 0026 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0056 0027 SEND_REF CV0($states) 1
  .php   L0056 0028 T21 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0056 0029 SEND_VAL T21 2
  .php   L0056 0030 V22 = DO_ICALL
  .php   L0056 0031 ASSIGN CV3($active) V22
  .php   L0057 0032 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0057 0033 SEND_VAR CV0($states) 1
  .php   L0057 0034 T24 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   L0057 0035 SEND_VAL T24 2
  .php   L0057 0036 V25 = DO_ICALL
  .php   L0057 0037 ASSIGN CV4($has) V25
  .php   L0058 0038 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0058 0039 SEND_REF CV0($states) 1
  .php   L0058 0040 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   L0058 0041 SEND_VAL T27 2
  .php   L0058 0042 V28 = DO_ICALL
  .php   L0058 0043 ASSIGN CV5($removed) V28
  .php   L0059 0044 T30 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .php   L0059 0045 T30 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .php   L0059 0046 T30 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .php   L0059 0047 T30 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .php   L0059 0048 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0059 0049 SEND_VAR CV0($states) 1
  .php   L0059 0050 V31 = DO_ICALL
  .php   L0059 0051 T30 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   L0059 0052 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0059 0053 SEND_VAR CV1($sizes) 1
  .php   L0059 0054 V32 = DO_ICALL
  .php   L0059 0055 T30 = ADD_ARRAY_ELEMENT V32 NEXT
  .php   L0059 0056 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0059 0057 SEND_VAR CV1($sizes) 1
  .php   L0059 0058 T33 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   L0059 0059 SEND_VAL T33 2
  .php   L0059 0060 V34 = DO_ICALL
  .php   L0059 0061 T30 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   L0059 0062 VERIFY_RETURN_TYPE T30
  .php   L0059 0063 RETURN T30
  .php   L0060 0064 VERIFY_RETURN_TYPE
  .php   L0060 0065 RETURN null
  .php        ; (lines=76, args=1, vars=8, tmps=33)
  .php   L0064 0002 V8 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   L0064 0003 SEND_REF V8 1
  .php   L0064 0005 V9 = DO_ICALL
  .php   L0064 0006 ASSIGN CV1($vip) V9
  .php   L0065 0007 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0065 0008 V11 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   L0065 0009 SEND_REF V11 1
  .php   L0065 0010 SEND_VAL string("vip") 2
  .php   L0065 0011 V12 = DO_ICALL
  .php   L0065 0012 ASSIGN CV2($again) V12
  .php   L0066 0013 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   L0066 0014 V14 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   L0066 0015 SEND_REF V14 1
  .php   L0066 0016 SEND_VAL string("new") 2
  .php   L0066 0017 V15 = DO_ICALL
  .php   L0066 0018 ASSIGN CV3($removed) V15
  .php   L0067 0019 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0067 0020 T17 = FETCH_OBJ_R THIS string("tags")
  .php   L0067 0021 SEND_VAL T17 1
  .php   L0067 0022 V18 = DO_ICALL
  .php   L0067 0023 ASSIGN CV4($tags) V18
  .php   L0068 0024 T20 = FETCH_OBJ_R THIS string("states")
  .php   L0068 0025 INIT_METHOD_CALL 1 T20 string("add")
  .php   L0068 0026 SEND_VAR_EX CV0($state) 1
  .php   L0068 0027 V21 = DO_FCALL
  .php   L0068 0028 ASSIGN CV5($added) V21
  .php   L0069 0029 T23 = FETCH_OBJ_R THIS string("states")
  .php   L0069 0030 INIT_METHOD_CALL 1 T23 string("add")
  .php   L0069 0031 SEND_VAR_EX CV0($state) 1
  .php   L0069 0032 V24 = DO_FCALL
  .php   L0069 0033 ASSIGN CV6($twice) V24
  .php   L0070 0034 T26 = FETCH_OBJ_R THIS string("states")
  .php   L0070 0035 INIT_METHOD_CALL 1 T26 string("remove")
  .php   L0070 0036 SEND_VAR_EX CV0($state) 1
  .php   L0070 0037 V27 = DO_FCALL
  .php   L0070 0038 ASSIGN CV7($dropped) V27
  .php   L0071 0039 T29 = INIT_ARRAY 13 (packed) CV1($vip) NEXT
  .php   L0071 0040 T29 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   L0071 0041 T29 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   L0071 0042 T29 = ADD_ARRAY_ELEMENT CV4($tags) NEXT
  .php   L0071 0043 T29 = ADD_ARRAY_ELEMENT CV5($added) NEXT
  .php   L0071 0044 T29 = ADD_ARRAY_ELEMENT CV6($twice) NEXT
  .php   L0071 0045 T29 = ADD_ARRAY_ELEMENT CV7($dropped) NEXT
  .php   L0071 0046 T30 = FETCH_OBJ_R THIS string("states")
  .php   L0071 0047 INIT_METHOD_CALL 1 T30 string("contains")
  .php   L0071 0048 SEND_VAR_EX CV0($state) 1
  .php   L0071 0049 V31 = DO_FCALL
  .php   L0071 0050 T29 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   L0071 0051 T32 = FETCH_OBJ_R THIS string("writes")
  .php   L0071 0052 T29 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   L0071 0053 T33 = FETCH_OBJ_R THIS string("sizes")
  .php   L0071 0054 INIT_METHOD_CALL 1 T33 string("add")
  .php   L0071 0055 SEND_VAL_EX int(4) 1
  .php   L0071 0056 V34 = DO_FCALL
  .php   L0071 0057 T29 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   L0071 0058 T35 = FETCH_OBJ_R THIS string("sizes")
  .php   L0071 0059 INIT_METHOD_CALL 1 T35 string("contains")
  .php   L0071 0060 SEND_VAL_EX int(4) 1
  .php   L0071 0061 V36 = DO_FCALL
  .php   L0071 0062 T29 = ADD_ARRAY_ELEMENT V36 NEXT
  .php   L0071 0063 T37 = FETCH_OBJ_R THIS string("ids")
  .php   L0071 0064 INIT_METHOD_CALL 1 T37 string("add")
  .php   L0071 0065 SEND_VAL_EX int(9) 1
  .php   L0071 0066 V38 = DO_FCALL
  .php   L0071 0067 T29 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   L0071 0068 T39 = FETCH_OBJ_R THIS string("ids")
  .php   L0071 0069 INIT_METHOD_CALL 0 T39 string("toList")
  .php   L0071 0070 V40 = DO_FCALL
  .php   L0071 0071 T29 = ADD_ARRAY_ELEMENT V40 NEXT
  .php   L0071 0072 VERIFY_RETURN_TYPE T29
  .php   L0071 0073 RETURN T29
  .php   L0072 0074 VERIFY_RETURN_TYPE
  .php   L0072 0075 RETURN null
  .php        ; (lines=85, args=1, vars=6, tmps=34)
  .php   L0074 0001 T6 = TYPE_CHECK (array) CV0($maybe)
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
  .php   L0077 0013 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0077 0014 SEND_REF CV1($copy) 1
  .php   L0077 0015 SEND_VAL string("copy") 2
  .php   L0077 0016 V13 = DO_ICALL
  .php   L0077 0017 ASSIGN CV2($copied) V13
  .php   L0078 0018 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0078 0019 T15 = FETCH_OBJ_R THIS string("tags")
  .php   L0078 0020 SEND_VAL T15 1
  .php   L0078 0021 SEND_VAL string("copy") 2
  .php   L0078 0022 V16 = DO_ICALL
  .php   L0078 0023 ASSIGN CV3($kept) V16
  .php   L0079 0024 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0079 0025 V18 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   L0079 0026 SEND_REF V18 1
  .php   L0079 0028 V19 = DO_FCALL
  .php   L0079 0029 SEND_VAR V19 2
  .php   L0079 0030 V20 = DO_ICALL
  .php   L0079 0031 ASSIGN CV4($added) V20
  .php   L0080 0032 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0080 0033 T22 = FETCH_OBJ_R THIS string("tags")
  .php   L0080 0034 SEND_VAL T22 1
  .php   L0080 0035 V23 = DO_ICALL
  .php   L0080 0036 ASSIGN CV5($named) V23
  .php   L0081 0037 INIT_FCALL 1 96 string("sharp\\internal\\clear")
  .php   L0081 0038 V25 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   L0081 0039 SEND_REF V25 1
  .php   L0081 0040 DO_ICALL
  .php   L0082 0041 T27 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .php   L0082 0042 T27 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .php   L0082 0043 T27 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .php   L0082 0044 T28 = FETCH_OBJ_R THIS string("calls")
  .php   L0082 0045 T27 = ADD_ARRAY_ELEMENT T28 NEXT
  .php   L0082 0046 T27 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .php   L0082 0047 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   L0082 0048 T29 = FETCH_OBJ_R THIS string("tags")
  .php   L0082 0049 SEND_VAL T29 1
  .php   L0082 0050 V30 = DO_ICALL
  .php   L0082 0051 T27 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   L0082 0052 T31 = TYPE_CHECK (null) CV0($maybe)
  .php   L0082 0053 JMPZ T31 0056
  .php   L0082 0054 T32 = QM_ASSIGN null
  .php   L0082 0055 JMP 0061
  .php   L0082 0056 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   L0082 0057 SEND_REF CV0($maybe) 1
  .php   L0082 0058 SEND_VAL string("x") 2
  .php   L0082 0059 V33 = DO_ICALL
  .php   L0082 0060 T32 = QM_ASSIGN V33
  .php   L0082 0061 T27 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   L0082 0062 T34 = TYPE_CHECK (null) CV0($maybe)
  .php   L0082 0063 JMPZ T34 0066
  .php   L0082 0064 T35 = QM_ASSIGN null
  .php   L0082 0065 JMP 0071
  .php   L0082 0066 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0082 0067 SEND_VAR CV0($maybe) 1
  .php   L0082 0068 SEND_VAL string("x") 2
  .php   L0082 0069 V36 = DO_ICALL
  .php   L0082 0070 T35 = QM_ASSIGN V36
  .php   L0082 0071 T27 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   L0082 0072 T37 = TYPE_CHECK (null) CV0($maybe)
  .php   L0082 0073 JMPZ T37 0076
  .php   L0082 0074 T38 = QM_ASSIGN null
  .php   L0082 0075 JMP 0080
  .php   L0082 0076 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   L0082 0077 SEND_VAR CV0($maybe) 1
  .php   L0082 0078 V39 = DO_ICALL
  .php   L0082 0079 T38 = QM_ASSIGN V39
  .php   L0082 0080 T27 = ADD_ARRAY_ELEMENT T38 NEXT
  .php   L0082 0081 VERIFY_RETURN_TYPE T27
  .php   L0082 0082 RETURN T27
  .php   L0083 0083 VERIFY_RETURN_TYPE
  .php   L0083 0084 RETURN null
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
  .php        ; (lines=17, args=1, vars=2, tmps=6)
  .php   L0096 0003 JMPZ T2 0009
  .php   L0096 0004 V3 = NEW 1 string("Sharp\\Set")
  .php   L0096 0005 SEND_VAR_EX CV0($tags) 1
  .php   L0096 0006 DO_FCALL
  .php   L0096 0007 T5 = QM_ASSIGN V3
  .php   L0096 0008 JMP 0010
  .php   L0096 0009 T5 = QM_ASSIGN CV0($tags)
  .php   L0096 0010 ASSIGN CV0($tags) T5
  .php   L0096 0011 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0096 0012 SEND_VAR CV0($tags) 1
  .php   L0096 0013 SEND_VAR CV1($prefix) 2
  .php   L0096 0014 V7 = DO_ICALL
  .php   L0096 0015 RETURN V7
  .php   L0096 0016 RETURN null
  .php        ; (lines=15, args=1, vars=1, tmps=6)
  .php   L0097 0002 JMPZ T1 0008
  .php   L0097 0003 V2 = NEW 1 string("Sharp\\Set")
  .php   L0097 0004 SEND_VAR_EX CV0($tags) 1
  .php   L0097 0005 DO_FCALL
  .php   L0097 0006 T4 = QM_ASSIGN V2
  .php   L0097 0007 JMP 0009
  .php   L0097 0008 T4 = QM_ASSIGN CV0($tags)
  .php   L0097 0009 ASSIGN CV0($tags) T4
  .php   L0098 0010 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   L0098 0011 SEND_VAR CV0($tags) 1
  .php   L0098 0012 V6 = DO_ICALL
  .php   L0098 0013 RETURN V6
  .php   L0099 0014 RETURN null
  .php        ; (lines=24, args=0, vars=3, tmps=7)
  .php   L0103 0002 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0103 0003 SEND_VAL T3 1
  .php   L0103 0005 V4 = DO_ICALL
  .php   L0103 0006 ASSIGN CV0($groups) V4
  .php   L0106 0008 V7 = FE_RESET_R CV0($groups) 0019
  .php   L0106 0009 FE_FETCH_R V7 CV2($group) 0019
  .php   L0107 0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0107 0011 SEND_REF CV1($found) 1
  .php   L0107 0012 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0107 0013 SEND_VAR CV2($group) 1
  .php   L0107 0014 SEND_VAL string("a") 2
  .php   L0107 0015 V8 = DO_ICALL
  .php   L0107 0016 SEND_VAR V8 2
  .php   L0107 0017 DO_ICALL
  .php   L0106 0018 JMP 0009
  .php   L0106 0019 FE_FREE V7
  .php   L0109 0020 VERIFY_RETURN_TYPE CV1($found)
  .php   L0109 0021 RETURN CV1($found)
  .php   L0110 0022 VERIFY_RETURN_TYPE
  .php   L0110 0023 RETURN null
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
  .php        ; (lines=29, args=0, vars=3, tmps=9)
  .php   L0112 0002 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   L0112 0003 SEND_VAL T3 1
  .php   L0112 0005 V4 = DO_ICALL
  .php   L0112 0006 ASSIGN CV0($groups) V4
  .php   L0115 0008 V7 = FE_RESET_R CV0($groups) 0024
  .php   L0115 0009 FE_FETCH_R V7 CV2($group) 0024
  .php   L0116 0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0116 0011 SEND_REF CV1($found) 1
  .php   L0116 0012 T8 = TYPE_CHECK (null) CV2($group)
  .php   L0116 0013 JMPZ T8 0016
  .php   L0116 0014 T9 = QM_ASSIGN null
  .php   L0116 0015 JMP 0021
  .php   L0116 0016 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   L0116 0017 SEND_VAR CV2($group) 1
  .php   L0116 0018 SEND_VAL string("a") 2
  .php   L0116 0019 V10 = DO_ICALL
  .php   L0116 0020 T9 = QM_ASSIGN V10
  .php   L0116 0021 SEND_VAL T9 2
  .php   L0116 0022 DO_ICALL
  .php   L0115 0023 JMP 0009
  .php   L0115 0024 FE_FREE V7
  .php   L0118 0025 VERIFY_RETURN_TYPE CV1($found)
  .php   L0118 0026 RETURN CV1($found)
  .php   L0119 0027 VERIFY_RETURN_TYPE
  .php   L0119 0028 RETURN null
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
  .php        ; (lines=65, args=1, vars=7, tmps=23)
  .php   0002 JMPZ T7 0008
  .php   0003 V8 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T10 = QM_ASSIGN V8
  .php   0007 JMP 0009
  .php   0008 T10 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T10
  .php   0010 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0011 SEND_REF CV0($tags) 1
  .php   0013 V12 = DO_ICALL
  .php   0014 ASSIGN CV1($added) V12
  .php   0016 SEND_REF CV0($tags) 1
  .php   0017 SEND_VAL string("vip") 2
  .php   0018 V14 = DO_ICALL
  .php   0019 ASSIGN CV2($again) V14
  .php   0020 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0021 SEND_REF CV0($tags) 1
  .php   0022 SEND_VAL string("new") 2
  .php   0023 V16 = DO_ICALL
  .php   0024 ASSIGN CV3($removed) V16
  .php   0025 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0026 SEND_REF CV0($tags) 1
  .php   0027 SEND_VAL string("none") 2
  .php   0028 V18 = DO_ICALL
  .php   0029 ASSIGN CV4($absent) V18
  .php   0030 ASSIGN CV5($joined) string("")
  .php   0031 V21 = FE_RESET_R CV0($tags) 0037
  .php   0032 FE_FETCH_R V21 CV6($tag) 0037
  .php   0033 T22 = CONCAT CV5($joined) CV6($tag)
  .php   0034 T23 = CONCAT T22 string(";")
  .php   0035 ASSIGN CV5($joined) T23
  .php   0036 JMP 0032
  .php   0037 FE_FREE V21
  .php   0038 T25 = INIT_ARRAY 9 (packed) CV1($added) NEXT
  .php   0039 T25 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   0040 T25 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   0041 T25 = ADD_ARRAY_ELEMENT CV4($absent) NEXT
  .php   0042 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0043 SEND_VAR CV0($tags) 1
  .php   0044 SEND_VAL string("5") 2
  .php   0045 V26 = DO_ICALL
  .php   0046 T25 = ADD_ARRAY_ELEMENT V26 NEXT
  .php   0047 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0048 SEND_VAR CV0($tags) 1
  .php   0049 SEND_VAL string("new") 2
  .php   0050 V27 = DO_ICALL
  .php   0051 T25 = ADD_ARRAY_ELEMENT V27 NEXT
  .php   0052 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   0053 SEND_VAR CV0($tags) 1
  .php   0054 V28 = DO_ICALL
  .php   0055 T25 = ADD_ARRAY_ELEMENT V28 NEXT
  .php   0056 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0057 SEND_VAR CV0($tags) 1
  .php   0058 V29 = DO_ICALL
  .php   0059 T25 = ADD_ARRAY_ELEMENT V29 NEXT
  .php   0060 T25 = ADD_ARRAY_ELEMENT CV5($joined) NEXT
  .php   0061 VERIFY_RETURN_TYPE T25
  .php   0062 RETURN T25
  .php   0063 VERIFY_RETURN_TYPE
  .php   0064 RETURN null
  .php        8: 0004 - 0006 (new)
  .php        21: 0032 - 0037 (loop)
  .php        25: 0039 - 0062 (tmp/var)
  .php        ; (lines=56, args=1, vars=1, tmps=19)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0011 INIT_FCALL 2 112 string("sharp\\internal\\filterelements")
  .php   0012 SEND_VAR CV0($tags) 1
  .php   0013 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   0014 SEND_VAL T6 2
  .php   0015 V7 = DO_ICALL
  .php   0016 SEND_VAR V7 1
  .php   0017 V8 = DO_ICALL
  .php   0018 T9 = INIT_ARRAY 6 (packed) V8 NEXT
  .php   0019 INIT_FCALL 2 112 string("sharp\\internal\\map")
  .php   0020 SEND_VAR CV0($tags) 1
  .php   0021 T10 = DECLARE_LAMBDA_FUNCTION 1
  .php   0022 SEND_VAL T10 2
  .php   0023 V11 = DO_ICALL
  .php   0024 T9 = ADD_ARRAY_ELEMENT V11 NEXT
  .php   0025 INIT_FCALL 2 112 string("sharp\\internal\\any")
  .php   0026 SEND_VAR CV0($tags) 1
  .php   0027 T12 = DECLARE_LAMBDA_FUNCTION 2
  .php   0028 SEND_VAL T12 2
  .php   0029 V13 = DO_ICALL
  .php   0030 T9 = ADD_ARRAY_ELEMENT V13 NEXT
  .php   0031 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .php   0032 SEND_VAR CV0($tags) 1
  .php   0033 T14 = DECLARE_LAMBDA_FUNCTION 3
  .php   0034 SEND_VAL T14 2
  .php   0035 V15 = DO_ICALL
  .php   0036 T9 = ADD_ARRAY_ELEMENT V15 NEXT
  .php   0037 INIT_FCALL 3 128 string("sharp\\internal\\sortedby")
  .php   0038 SEND_VAR CV0($tags) 1
  .php   0039 T16 = DECLARE_LAMBDA_FUNCTION 4
  .php   0040 SEND_VAL T16 2
  .php   0041 SEND_VAL null 3
  .php   0042 V17 = DO_ICALL
  .php   0043 T9 = ADD_ARRAY_ELEMENT V17 NEXT
  .php   0044 INIT_STATIC_METHOD_CALL 2 string("Lib\\Probe") string("join")
  .php   0045 SEND_VAL_EX string(",") 1
  .php   0046 INIT_FCALL 1 96 string("sharp\\internal\\arrayof")
  .php   0047 SEND_VAR CV0($tags) 1
  .php   0048 V18 = DO_ICALL
  .php   0049 SEND_VAR_NO_REF_EX V18 2
  .php   0050 V19 = DO_FCALL
  .php   0051 T9 = ADD_ARRAY_ELEMENT V19 NEXT
  .php   0052 VERIFY_RETURN_TYPE T9
  .php   0053 RETURN T9
  .php   0054 VERIFY_RETURN_TYPE
  .php   0055 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        9: 0019 - 0053 (tmp/var)
  .php        ; (lines=19, args=1, vars=1, tmps=7)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 INIT_FCALL 2 112 string("sharp\\internal\\first")
  .php   0011 SEND_VAR CV0($tags) 1
  .php   0012 T6 = DECLARE_LAMBDA_FUNCTION 0
  .php   0013 SEND_VAL T6 2
  .php   0014 V7 = DO_ICALL
  .php   0015 VERIFY_RETURN_TYPE V7
  .php   0016 RETURN V7
  .php   0017 VERIFY_RETURN_TYPE
  .php        2: 0004 - 0006 (new)
  .php        7: 0015 - 0016 (tmp/var)
  .php        ; (lines=52, args=1, vars=2, tmps=17)
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
  .php   0012 DO_FCALL
  .php   0013 ASSIGN CV1($more) V7
  .php   0014 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0015 SEND_REF CV0($ids) 1
  .php   0016 SEND_VAL int(4) 2
  .php   0017 V10 = DO_ICALL
  .php   0018 T11 = INIT_ARRAY 7 (packed) V10 NEXT
  .php   0019 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0020 SEND_REF CV0($ids) 1
  .php   0021 SEND_VAL int(4) 2
  .php   0022 V12 = DO_ICALL
  .php   0023 T11 = ADD_ARRAY_ELEMENT V12 NEXT
  .php   0026 SEND_VAL int(7) 2
  .php   0027 V13 = DO_ICALL
  .php   0028 T11 = ADD_ARRAY_ELEMENT V13 NEXT
  .php   0029 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0030 SEND_VAR CV0($ids) 1
  .php   0031 SEND_VAL int(2) 2
  .php   0032 V14 = DO_ICALL
  .php   0033 T11 = ADD_ARRAY_ELEMENT V14 NEXT
  .php   0034 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0035 SEND_VAR CV0($ids) 1
  .php   0036 V15 = DO_ICALL
  .php   0037 T11 = ADD_ARRAY_ELEMENT V15 NEXT
  .php   0038 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0039 SEND_VAR CV1($more) 1
  .php   0040 V16 = DO_ICALL
  .php   0041 T11 = ADD_ARRAY_ELEMENT V16 NEXT
  .php   0042 INIT_FCALL 2 112 string("sharp\\internal\\sumof")
  .php   0043 SEND_VAR CV0($ids) 1
  .php   0044 T17 = DECLARE_LAMBDA_FUNCTION 0
  .php   0045 SEND_VAL T17 2
  .php   0046 V18 = DO_ICALL
  .php   0047 T11 = ADD_ARRAY_ELEMENT V18 NEXT
  .php   0048 VERIFY_RETURN_TYPE T11
  .php   0049 RETURN T11
  .php   0050 VERIFY_RETURN_TYPE
  .php   0051 RETURN null
  .php        3: 0004 - 0006 (new)
  .php        7: 0011 - 0013 (new)
  .php        11: 0019 - 0049 (tmp/var)
  .php        ; (lines=66, args=1, vars=6, tmps=29)
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
  .php   0020 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0021 SEND_REF CV0($states) 1
  .php   0022 T18 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0023 SEND_VAL T18 2
  .php   0024 V19 = DO_ICALL
  .php   0025 ASSIGN CV2($closed) V19
  .php   0026 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0027 SEND_REF CV0($states) 1
  .php   0028 T21 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0029 SEND_VAL T21 2
  .php   0030 V22 = DO_ICALL
  .php   0031 ASSIGN CV3($active) V22
  .php   0032 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0033 SEND_VAR CV0($states) 1
  .php   0034 T24 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Closed")
  .php   0035 SEND_VAL T24 2
  .php   0036 V25 = DO_ICALL
  .php   0037 ASSIGN CV4($has) V25
  .php   0038 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0039 SEND_REF CV0($states) 1
  .php   0040 T27 = FETCH_CLASS_CONSTANT string("Lib\\Standing") string("Active")
  .php   0041 SEND_VAL T27 2
  .php   0042 V28 = DO_ICALL
  .php   0043 ASSIGN CV5($removed) V28
  .php   0044 T30 = INIT_ARRAY 7 (packed) CV2($closed) NEXT
  .php   0045 T30 = ADD_ARRAY_ELEMENT CV3($active) NEXT
  .php   0046 T30 = ADD_ARRAY_ELEMENT CV4($has) NEXT
  .php   0047 T30 = ADD_ARRAY_ELEMENT CV5($removed) NEXT
  .php   0048 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0049 SEND_VAR CV0($states) 1
  .php   0050 V31 = DO_ICALL
  .php   0051 T30 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   0052 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0053 SEND_VAR CV1($sizes) 1
  .php   0054 V32 = DO_ICALL
  .php   0055 T30 = ADD_ARRAY_ELEMENT V32 NEXT
  .php   0056 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0057 SEND_VAR CV1($sizes) 1
  .php   0058 T33 = FETCH_CLASS_CONSTANT string("Lib\\Size") string("Small")
  .php   0059 SEND_VAL T33 2
  .php   0060 V34 = DO_ICALL
  .php   0061 T30 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   0062 VERIFY_RETURN_TYPE T30
  .php   0063 RETURN T30
  .php   0064 VERIFY_RETURN_TYPE
  .php   0065 RETURN null
  .php        7: 0004 - 0006 (new)
  .php        11: 0011 - 0019 (new)
  .php        13: 0013 - 0017 (tmp/var)
  .php        30: 0045 - 0063 (tmp/var)
  .php        ; (lines=76, args=1, vars=8, tmps=33)
  .php   0002 V8 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0003 SEND_REF V8 1
  .php   0005 V9 = DO_ICALL
  .php   0006 ASSIGN CV1($vip) V9
  .php   0007 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0008 V11 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0009 SEND_REF V11 1
  .php   0010 SEND_VAL string("vip") 2
  .php   0011 V12 = DO_ICALL
  .php   0012 ASSIGN CV2($again) V12
  .php   0013 INIT_FCALL 2 112 string("sharp\\internal\\removekey")
  .php   0014 V14 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0015 SEND_REF V14 1
  .php   0016 SEND_VAL string("new") 2
  .php   0017 V15 = DO_ICALL
  .php   0018 ASSIGN CV3($removed) V15
  .php   0019 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0020 T17 = FETCH_OBJ_R THIS string("tags")
  .php   0021 SEND_VAL T17 1
  .php   0022 V18 = DO_ICALL
  .php   0023 ASSIGN CV4($tags) V18
  .php   0024 T20 = FETCH_OBJ_R THIS string("states")
  .php   0025 INIT_METHOD_CALL 1 T20 string("add")
  .php   0026 SEND_VAR_EX CV0($state) 1
  .php   0027 V21 = DO_FCALL
  .php   0028 ASSIGN CV5($added) V21
  .php   0029 T23 = FETCH_OBJ_R THIS string("states")
  .php   0030 INIT_METHOD_CALL 1 T23 string("add")
  .php   0031 SEND_VAR_EX CV0($state) 1
  .php   0032 V24 = DO_FCALL
  .php   0033 ASSIGN CV6($twice) V24
  .php   0034 T26 = FETCH_OBJ_R THIS string("states")
  .php   0035 INIT_METHOD_CALL 1 T26 string("remove")
  .php   0036 SEND_VAR_EX CV0($state) 1
  .php   0037 V27 = DO_FCALL
  .php   0038 ASSIGN CV7($dropped) V27
  .php   0039 T29 = INIT_ARRAY 13 (packed) CV1($vip) NEXT
  .php   0040 T29 = ADD_ARRAY_ELEMENT CV2($again) NEXT
  .php   0041 T29 = ADD_ARRAY_ELEMENT CV3($removed) NEXT
  .php   0042 T29 = ADD_ARRAY_ELEMENT CV4($tags) NEXT
  .php   0043 T29 = ADD_ARRAY_ELEMENT CV5($added) NEXT
  .php   0044 T29 = ADD_ARRAY_ELEMENT CV6($twice) NEXT
  .php   0045 T29 = ADD_ARRAY_ELEMENT CV7($dropped) NEXT
  .php   0046 T30 = FETCH_OBJ_R THIS string("states")
  .php   0047 INIT_METHOD_CALL 1 T30 string("contains")
  .php   0048 SEND_VAR_EX CV0($state) 1
  .php   0049 V31 = DO_FCALL
  .php   0050 T29 = ADD_ARRAY_ELEMENT V31 NEXT
  .php   0051 T32 = FETCH_OBJ_R THIS string("writes")
  .php   0052 T29 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   0053 T33 = FETCH_OBJ_R THIS string("sizes")
  .php   0054 INIT_METHOD_CALL 1 T33 string("add")
  .php   0055 SEND_VAL_EX int(4) 1
  .php   0056 V34 = DO_FCALL
  .php   0057 T29 = ADD_ARRAY_ELEMENT V34 NEXT
  .php   0058 T35 = FETCH_OBJ_R THIS string("sizes")
  .php   0059 INIT_METHOD_CALL 1 T35 string("contains")
  .php   0060 SEND_VAL_EX int(4) 1
  .php   0061 V36 = DO_FCALL
  .php   0062 T29 = ADD_ARRAY_ELEMENT V36 NEXT
  .php   0063 T37 = FETCH_OBJ_R THIS string("ids")
  .php   0064 INIT_METHOD_CALL 1 T37 string("add")
  .php   0065 SEND_VAL_EX int(9) 1
  .php   0066 V38 = DO_FCALL
  .php   0067 T29 = ADD_ARRAY_ELEMENT V38 NEXT
  .php   0068 T39 = FETCH_OBJ_R THIS string("ids")
  .php   0069 INIT_METHOD_CALL 0 T39 string("toList")
  .php   0070 V40 = DO_FCALL
  .php   0071 T29 = ADD_ARRAY_ELEMENT V40 NEXT
  .php   0072 VERIFY_RETURN_TYPE T29
  .php   0073 RETURN T29
  .php   0074 VERIFY_RETURN_TYPE
  .php   0075 RETURN null
  .php        29: 0040 - 0073 (tmp/var)
  .php        ; (lines=85, args=1, vars=6, tmps=34)
  .php   0001 T6 = TYPE_CHECK (array) CV0($maybe)
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
  .php   0013 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0014 SEND_REF CV1($copy) 1
  .php   0015 SEND_VAL string("copy") 2
  .php   0016 V13 = DO_ICALL
  .php   0017 ASSIGN CV2($copied) V13
  .php   0018 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0019 T15 = FETCH_OBJ_R THIS string("tags")
  .php   0020 SEND_VAL T15 1
  .php   0021 SEND_VAL string("copy") 2
  .php   0022 V16 = DO_ICALL
  .php   0023 ASSIGN CV3($kept) V16
  .php   0025 V18 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0026 SEND_REF V18 1
  .php   0028 V19 = DO_FCALL
  .php   0029 SEND_VAR V19 2
  .php   0030 V20 = DO_ICALL
  .php   0031 ASSIGN CV4($added) V20
  .php   0032 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0033 T22 = FETCH_OBJ_R THIS string("tags")
  .php   0034 SEND_VAL T22 1
  .php   0035 V23 = DO_ICALL
  .php   0036 ASSIGN CV5($named) V23
  .php   0037 INIT_FCALL 1 96 string("sharp\\internal\\clear")
  .php   0038 V25 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0039 SEND_REF V25 1
  .php   0040 DO_ICALL
  .php   0041 T27 = INIT_ARRAY 9 (packed) CV2($copied) NEXT
  .php   0042 T27 = ADD_ARRAY_ELEMENT CV3($kept) NEXT
  .php   0043 T27 = ADD_ARRAY_ELEMENT CV4($added) NEXT
  .php   0044 T28 = FETCH_OBJ_R THIS string("calls")
  .php   0045 T27 = ADD_ARRAY_ELEMENT T28 NEXT
  .php   0046 T27 = ADD_ARRAY_ELEMENT CV5($named) NEXT
  .php   0047 INIT_FCALL 1 96 string("sharp\\internal\\keys")
  .php   0048 T29 = FETCH_OBJ_R THIS string("tags")
  .php   0049 SEND_VAL T29 1
  .php   0050 V30 = DO_ICALL
  .php   0051 T27 = ADD_ARRAY_ELEMENT V30 NEXT
  .php   0052 T31 = TYPE_CHECK (null) CV0($maybe)
  .php   0053 JMPZ T31 0056
  .php   0054 T32 = QM_ASSIGN null
  .php   0055 JMP 0061
  .php   0056 INIT_FCALL 2 112 string("sharp\\internal\\addkey")
  .php   0057 SEND_REF CV0($maybe) 1
  .php   0058 SEND_VAL string("x") 2
  .php   0059 V33 = DO_ICALL
  .php   0060 T32 = QM_ASSIGN V33
  .php   0061 T27 = ADD_ARRAY_ELEMENT T32 NEXT
  .php   0062 T34 = TYPE_CHECK (null) CV0($maybe)
  .php   0063 JMPZ T34 0066
  .php   0064 T35 = QM_ASSIGN null
  .php   0065 JMP 0071
  .php   0066 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0067 SEND_VAR CV0($maybe) 1
  .php   0068 SEND_VAL string("x") 2
  .php   0069 V36 = DO_ICALL
  .php   0070 T35 = QM_ASSIGN V36
  .php   0071 T27 = ADD_ARRAY_ELEMENT T35 NEXT
  .php   0072 T37 = TYPE_CHECK (null) CV0($maybe)
  .php   0073 JMPZ T37 0076
  .php   0074 T38 = QM_ASSIGN null
  .php   0075 JMP 0080
  .php   0076 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   0077 SEND_VAR CV0($maybe) 1
  .php   0078 V39 = DO_ICALL
  .php   0079 T38 = QM_ASSIGN V39
  .php   0080 T27 = ADD_ARRAY_ELEMENT T38 NEXT
  .php   0081 VERIFY_RETURN_TYPE T27
  .php   0082 RETURN T27
  .php   0083 VERIFY_RETURN_TYPE
  .php   0084 RETURN null
  .php        7: 0004 - 0006 (new)
  .php        27: 0042 - 0082 (tmp/var)
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
  .php        ; (lines=17, args=1, vars=2, tmps=6)
  .php   0003 JMPZ T2 0009
  .php   0004 V3 = NEW 1 string("Sharp\\Set")
  .php   0005 SEND_VAR_EX CV0($tags) 1
  .php   0006 DO_FCALL
  .php   0007 T5 = QM_ASSIGN V3
  .php   0008 JMP 0010
  .php   0009 T5 = QM_ASSIGN CV0($tags)
  .php   0010 ASSIGN CV0($tags) T5
  .php   0011 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0012 SEND_VAR CV0($tags) 1
  .php   0013 SEND_VAR CV1($prefix) 2
  .php   0014 V7 = DO_ICALL
  .php   0015 RETURN V7
  .php        3: 0005 - 0007 (new)
  .php        ; (lines=15, args=1, vars=1, tmps=6)
  .php   0002 JMPZ T1 0008
  .php   0003 V2 = NEW 1 string("Sharp\\Set")
  .php   0004 SEND_VAR_EX CV0($tags) 1
  .php   0005 DO_FCALL
  .php   0006 T4 = QM_ASSIGN V2
  .php   0007 JMP 0009
  .php   0008 T4 = QM_ASSIGN CV0($tags)
  .php   0009 ASSIGN CV0($tags) T4
  .php   0010 INIT_FCALL 1 96 string("sharp\\internal\\size")
  .php   0011 SEND_VAR CV0($tags) 1
  .php   0012 V6 = DO_ICALL
  .php   0013 RETURN V6
  .php   0014 RETURN null
  .php        2: 0004 - 0006 (new)
  .php        ; (lines=24, args=0, vars=3, tmps=7)
  .php   0002 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   0003 SEND_VAL T3 1
  .php   0005 V4 = DO_ICALL
  .php   0006 ASSIGN CV0($groups) V4
  .php   0008 V7 = FE_RESET_R CV0($groups) 0019
  .php   0009 FE_FETCH_R V7 CV2($group) 0019
  .php   0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0011 SEND_REF CV1($found) 1
  .php   0012 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0013 SEND_VAR CV2($group) 1
  .php   0014 SEND_VAL string("a") 2
  .php   0015 V8 = DO_ICALL
  .php   0016 SEND_VAR V8 2
  .php   0017 DO_ICALL
  .php   0018 JMP 0009
  .php   0019 FE_FREE V7
  .php   0020 VERIFY_RETURN_TYPE CV1($found)
  .php   0021 RETURN CV1($found)
  .php   0022 VERIFY_RETURN_TYPE
  .php   0023 RETURN null
  .php        7: 0009 - 0019 (loop)
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
  .php        ; (lines=29, args=0, vars=3, tmps=9)
  .php   0002 T3 = DECLARE_LAMBDA_FUNCTION 0
  .php   0003 SEND_VAL T3 1
  .php   0005 V4 = DO_ICALL
  .php   0006 ASSIGN CV0($groups) V4
  .php   0008 V7 = FE_RESET_R CV0($groups) 0024
  .php   0009 FE_FETCH_R V7 CV2($group) 0024
  .php   0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0011 SEND_REF CV1($found) 1
  .php   0012 T8 = TYPE_CHECK (null) CV2($group)
  .php   0013 JMPZ T8 0016
  .php   0014 T9 = QM_ASSIGN null
  .php   0015 JMP 0021
  .php   0016 INIT_FCALL 2 112 string("sharp\\internal\\containskey")
  .php   0017 SEND_VAR CV2($group) 1
  .php   0018 SEND_VAL string("a") 2
  .php   0020 T9 = QM_ASSIGN V10
  .php   0021 SEND_VAL T9 2
  .php   0022 DO_ICALL
  .php   0023 JMP 0009
  .php   0024 FE_FREE V7
  .php   0025 VERIFY_RETURN_TYPE CV1($found)
  .php   0026 RETURN CV1($found)
  .php   0027 VERIFY_RETURN_TYPE
  .php   0028 RETURN null
  .php        7: 0009 - 0024 (loop)
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
  .sharp      ; (lines=14, args=1, vars=2, tmps=5)
  .sharp L0028 0003 V2 = DO_FCALL
  .sharp L0028 0004 ASSIGN CV1($value#1) V2
  .sharp L0028 0005 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0028 0006 V4 = FETCH_OBJ_W (ref) THIS string("saved")
  .sharp L0028 0007 SEND_REF V4 1
  .sharp L0028 0008 SEND_VAR CV0($key) 2
  .sharp L0028 0009 SEND_VAR CV1($value#1) 3
  .sharp L0028 0010 DO_ICALL
  .sharp L0028 0011 V6 = FETCH_OBJ_UNSET THIS string("saved")
  .sharp L0028 0012 V6 = SEPARATE V6
  .sharp L0029 0013 RETURN null
  .sharp      ; (lines=14, args=1, vars=2, tmps=5)
  .sharp 0004 ASSIGN CV1($value#1) V2
  .sharp 0005 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0006 V4 = FETCH_OBJ_W (ref) THIS string("saved")
  .sharp 0007 SEND_REF V4 1
  .sharp 0008 SEND_VAR CV0($key) 2
  .sharp 0009 SEND_VAR CV1($value#1) 3
  .sharp 0010 DO_ICALL
  .sharp 0011 V6 = FETCH_OBJ_UNSET THIS string("saved")
  .sharp 0012 V6 = SEPARATE V6
  .sharp 0013 RETURN null
  .sharp     Property [ private Sharp\Map $saved = Closure({closure:<file>:6})() ]
  .php        ; (lines=8, args=1, vars=1, tmps=3)
  .php   L0028 0003 V3 = DO_FCALL
  .php   L0028 0004 V1 = FETCH_OBJ_W (dim write) THIS string("saved")
  .php   L0028 0005 ASSIGN_DIM V1 CV0($key)
  .php   L0028 0006 OP_DATA V3
  .php   L0029 0007 RETURN null
  .php        ; (lines=8, args=1, vars=1, tmps=3)
  .php   0003 V3 = DO_FCALL
  .php   0004 V1 = FETCH_OBJ_W (dim write) THIS string("saved")
  .php   0005 ASSIGN_DIM V1 CV0($key)
  .php   0006 OP_DATA V3
  .php   0007 RETURN null
  .php        3: 0004 - 0005 (tmp/var)
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
Accessors/Product: different opcodes and lines in 11 op arrays, same signatures in 1 classes
  .sharp      ; (lines=15, args=1, vars=3, tmps=6)
  .sharp L0038 0002 ASSIGN CV1($receiver#1) T3
  .sharp L0038 0003 ASSIGN CV2($kept#2) CV1($receiver#1)
  .sharp L0038 0004 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp L0038 0005 SEND_REF CV1($receiver#1) 1
  .sharp L0038 0006 SEND_VAR CV0($tag) 2
  .sharp L0038 0007 DO_ICALL
  .sharp L0038 0008 SEPARATE CV1($receiver#1)
  .sharp L0038 0009 T7 = IS_IDENTICAL CV1($receiver#1) CV2($kept#2)
  .sharp L0038 0010 T7 = JMPNZ_EX T7 0014
  .sharp L0038 0011 T8 = ASSIGN_OBJ THIS string("tags")
  .sharp L0038 0012 OP_DATA CV1($receiver#1)
  .sharp L0038 0013 T7 = BOOL T8
  .sharp L0039 0014 RETURN null
  .sharp      ; (lines=15, args=1, vars=3, tmps=6)
  .sharp 0002 ASSIGN CV1($receiver#1) T3
  .sharp 0003 ASSIGN CV2($kept#2) CV1($receiver#1)
  .sharp 0004 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0005 SEND_REF CV1($receiver#1) 1
  .sharp 0006 SEND_VAR CV0($tag) 2
  .sharp 0007 DO_ICALL
  .sharp 0008 SEPARATE CV1($receiver#1)
  .sharp 0009 T7 = IS_IDENTICAL CV1($receiver#1) CV2($kept#2)
  .sharp 0010 T7 = JMPNZ_EX T7 0014
  .sharp 0011 T8 = ASSIGN_OBJ THIS string("tags")
  .sharp 0012 OP_DATA CV1($receiver#1)
  .sharp 0013 T7 = BOOL T8
  .sharp 0014 RETURN null
  .sharp      8: 0012 - 0013 (tmp/var)
  .sharp      ; (lines=20, args=1, vars=3, tmps=7)
  .sharp 0003 V4 = FE_RESET_R CV0($value) 0018
  .sharp 0004 FE_FETCH_R V4 CV1($tag) 0018
  .sharp 0005 INIT_FCALL 2 112 string("mb_strtolower")
  .sharp 0006 SEND_VAR CV1($tag) 1
  .sharp 0007 SEND_VAL string("UTF-8") 2
  .sharp 0008 V5 = DO_ICALL
  .sharp 0009 ASSIGN CV2($argument#1) V5
  .sharp 0010 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .sharp 0011 V7 = FETCH_OBJ_W (ref) THIS string("tags")
  .sharp 0012 SEND_REF V7 1
  .sharp 0013 SEND_VAR CV2($argument#1) 2
  .sharp 0014 DO_ICALL
  .sharp 0015 V9 = FETCH_OBJ_UNSET THIS string("tags")
  .sharp 0016 V9 = SEPARATE V9
  .sharp 0017 JMP 0004
  .sharp 0018 FE_FREE V4
  .sharp      4: 0004 - 0018 (loop)
  .sharp      ; (lines=9, args=1, vars=1, tmps=3)
  .sharp 0006 V3 = FETCH_OBJ_UNSET THIS string("ratings")
  .sharp 0007 V3 = SEPARATE V3
  .sharp 0008 RETURN null
  .php        ; (lines=20, args=1, vars=3, tmps=8)
  .php   L0038 0002 T4 = ASSIGN CV1($receiver#1) T3
  .php   L0038 0003 T5 = INIT_ARRAY 4 (packed) T4 NEXT
  .php   L0038 0004 T6 = ASSIGN CV2($kept#2) CV1($receiver#1)
  .php   L0038 0005 T5 = ADD_ARRAY_ELEMENT T6 NEXT
  .php   L0038 0006 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   L0038 0007 SEND_REF CV1($receiver#1) 1
  .php   L0038 0008 SEND_VAR CV0($tag) 2
  .php   L0038 0009 V7 = DO_ICALL
  .php   L0038 0010 T5 = ADD_ARRAY_ELEMENT V7 NEXT
  .php   L0038 0011 T8 = IS_IDENTICAL CV1($receiver#1) CV2($kept#2)
  .php   L0038 0012 T8 = JMPNZ_EX T8 0016
  .php   L0038 0013 T9 = ASSIGN_OBJ THIS string("tags")
  .php   L0038 0014 OP_DATA CV1($receiver#1)
  .php   L0038 0015 T8 = BOOL T9
  .php   L0038 0016 T5 = ADD_ARRAY_ELEMENT T8 NEXT
  .php   L0038 0017 T10 = FETCH_DIM_R T5 int(2)
  .php   L0038 0018 FREE T10
  .php   L0039 0019 RETURN null
  .php        ; (lines=20, args=1, vars=3, tmps=8)
  .php   0002 T4 = ASSIGN CV1($receiver#1) T3
  .php   0003 T5 = INIT_ARRAY 4 (packed) T4 NEXT
  .php   0004 T6 = ASSIGN CV2($kept#2) CV1($receiver#1)
  .php   0005 T5 = ADD_ARRAY_ELEMENT T6 NEXT
  .php   0006 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0007 SEND_REF CV1($receiver#1) 1
  .php   0008 SEND_VAR CV0($tag) 2
  .php   0009 V7 = DO_ICALL
  .php   0010 T5 = ADD_ARRAY_ELEMENT V7 NEXT
  .php   0011 T8 = IS_IDENTICAL CV1($receiver#1) CV2($kept#2)
  .php   0012 T8 = JMPNZ_EX T8 0016
  .php   0013 T9 = ASSIGN_OBJ THIS string("tags")
  .php   0014 OP_DATA CV1($receiver#1)
  .php   0015 T8 = BOOL T9
  .php   0016 T5 = ADD_ARRAY_ELEMENT T8 NEXT
  .php   0017 T10 = FETCH_DIM_R T5 int(2)
  .php   0018 FREE T10
  .php        5: 0004 - 0017 (tmp/var)
  .php        9: 0014 - 0015 (tmp/var)
  .php        ; (lines=17, args=1, vars=2, tmps=5)
  .php   0003 V3 = FE_RESET_R CV0($value) 0015
  .php   0004 FE_FETCH_R V3 CV1($tag) 0015
  .php   0005 INIT_FCALL 2 112 string("sharp\\internal\\add")
  .php   0006 V4 = FETCH_OBJ_W (ref) THIS string("tags")
  .php   0007 SEND_REF V4 1
  .php   0008 INIT_FCALL 2 112 string("mb_strtolower")
  .php   0009 SEND_VAR CV1($tag) 1
  .php   0010 SEND_VAL string("UTF-8") 2
  .php   0011 V5 = DO_ICALL
  .php   0012 SEND_VAR V5 2
  .php   0013 DO_ICALL
  .php   0014 JMP 0004
  .php   0015 FE_FREE V3
  .php   0016 RETURN null
  .php        3: 0004 - 0015 (loop)
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
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp L0008 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0008 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0008 0002 V2 = NEW 1 V0 T1
  .sharp L0008 0003 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .sharp L0008 0004 V3 = DO_FCALL
  .sharp L0008 0005 SEND_VAR_NO_REF_EX V3 1
  .sharp L0008 0006 DO_FCALL
  .sharp L0008 0007 INIT_METHOD_CALL 0 V2 string("first")
  .sharp L0008 0008 V5 = DO_FCALL
  .sharp L0008 0009 T6 = FETCH_OBJ_R V5 string("id")
  .sharp L0008 0010 VERIFY_RETURN_TYPE T6
  .sharp L0008 0011 RETURN T6
  .sharp L0008 0012 VERIFY_RETURN_TYPE
  .sharp L0008 0013 RETURN null
  .sharp      ; (lines=19, args=0, vars=0, tmps=10)
  .sharp L0010 0009 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0010 0010 V6 = DO_FCALL T5
  .sharp L0010 0011 T7 = SHARP_VERIFY_TYPE_ARGS V6 string("Paging.PaginatedList<Paging.Order>")
  .sharp L0010 0012 INIT_METHOD_CALL 0 T7 string("first")
  .sharp L0010 0013 V8 = DO_FCALL
  .sharp L0010 0014 T9 = FETCH_OBJ_R V8 string("id")
  .sharp L0010 0015 VERIFY_RETURN_TYPE T9
  .sharp L0010 0016 RETURN T9
  .sharp L0010 0017 VERIFY_RETURN_TYPE
  .sharp L0010 0018 RETURN null
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
  .sharp      ; (lines=10, args=2, vars=2, tmps=2)
  .sharp L0022 0005 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("bool")
  .sharp L0022 0006 VERIFY_RETURN_TYPE T3
  .sharp L0022 0007 RETURN T3
  .sharp L0022 0008 VERIFY_RETURN_TYPE
  .sharp L0022 0009 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp L0026 0006 T3 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0026 0007 V4 = DO_FCALL T3
  .sharp L0026 0008 T5 = SHARP_VERIFY_TYPE_ARGS V4 string("Paging.Order")
  .sharp L0026 0009 T6 = FETCH_OBJ_R T5 string("id")
  .sharp L0026 0010 VERIFY_RETURN_TYPE T6
  .sharp L0026 0011 RETURN T6
  .sharp L0026 0012 VERIFY_RETURN_TYPE
  .sharp L0026 0013 RETURN null
  .sharp      ; (lines=13, args=0, vars=0, tmps=6)
  .sharp L0028 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp L0028 0008 V5 = DO_FCALL T4
  .sharp L0028 0009 VERIFY_RETURN_TYPE V5
  .sharp L0028 0010 RETURN V5
  .sharp L0028 0011 VERIFY_RETURN_TYPE
  .sharp L0028 0012 RETURN null
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp 0000 V0 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0001 T1 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0002 V2 = NEW 1 V0 T1
  .sharp 0004 V3 = DO_UCALL
  .sharp 0005 SEND_VAR_NO_REF_EX V3 1
  .sharp 0007 INIT_METHOD_CALL 0 V2 string("first")
  .sharp 0008 V5 = DO_FCALL
  .sharp 0009 T6 = FETCH_OBJ_R V5 string("id")
  .sharp 0010 VERIFY_RETURN_TYPE T6
  .sharp 0011 RETURN T6
  .sharp      2: 0003 - 0007 (new)
  .sharp      6: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=19, args=0, vars=0, tmps=10)
  .sharp 0009 T5 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0010 V6 = DO_FCALL T5
  .sharp 0011 T7 = SHARP_VERIFY_TYPE_ARGS V6 string("Paging.PaginatedList<Paging.Order>")
  .sharp 0012 INIT_METHOD_CALL 0 T7 string("first")
  .sharp 0013 V8 = DO_FCALL
  .sharp 0014 T9 = FETCH_OBJ_R V8 string("id")
  .sharp 0015 VERIFY_RETURN_TYPE T9
  .sharp 0016 RETURN T9
  .sharp 0017 VERIFY_RETURN_TYPE
  .sharp 0018 RETURN null
  .sharp      9: 0015 - 0016 (tmp/var)
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
  .sharp 0008 SEND_VAR V2 1
  .sharp 0009 V5 = DO_UCALL
  .sharp 0010 VERIFY_RETURN_TYPE V5
  .sharp 0011 RETURN V5
  .sharp      5: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=10, args=2, vars=2, tmps=2)
  .sharp 0005 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("bool")
  .sharp 0006 VERIFY_RETURN_TYPE T3
  .sharp 0007 RETURN T3
  .sharp 0008 VERIFY_RETURN_TYPE
  .sharp 0009 RETURN null
  .sharp      3: 0006 - 0007 (tmp/var)
  .sharp      ; (lines=14, args=0, vars=0, tmps=7)
  .sharp 0006 T3 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0007 V4 = DO_FCALL T3
  .sharp 0008 T5 = SHARP_VERIFY_TYPE_ARGS V4 string("Paging.Order")
  .sharp 0009 T6 = FETCH_OBJ_R T5 string("id")
  .sharp 0010 VERIFY_RETURN_TYPE T6
  .sharp 0011 RETURN T6
  .sharp      6: 0010 - 0011 (tmp/var)
  .sharp      ; (lines=13, args=0, vars=0, tmps=6)
  .sharp 0007 T4 = SHARP_TYPE_ARGS string("Paging.Order")
  .sharp 0008 V5 = DO_FCALL T4
  .sharp 0009 VERIFY_RETURN_TYPE V5
  .sharp 0010 RETURN V5
  .sharp      5: 0009 - 0010 (tmp/var)
  .php   L0008 0000 V0 = NEW 1 string("Paging\\PaginatedList")
  .php   L0008 0001 INIT_STATIC_METHOD_CALL 0 string("Paging\\Catalog") string("orders")
  .php   L0008 0002 V1 = DO_FCALL
  .php   L0008 0003 SEND_VAR_NO_REF_EX V1 1
  .php   L0008 0004 DO_FCALL
  .php   L0008 0005 INIT_METHOD_CALL 0 V0 string("first")
  .php   L0008 0006 V3 = DO_FCALL
  .php   L0008 0007 T4 = FETCH_OBJ_R V3 string("id")
  .php   L0008 0008 VERIFY_RETURN_TYPE T4
  .php   L0008 0009 RETURN T4
  .php   L0008 0010 VERIFY_RETURN_TYPE
  .php   L0008 0011 RETURN null
  .php        ; (lines=17, args=0, vars=0, tmps=8)
  .php   L0010 0009 V5 = DO_FCALL
  .php   L0010 0010 INIT_METHOD_CALL 0 V5 string("first")
  .php   L0010 0011 V6 = DO_FCALL
  .php   L0010 0012 T7 = FETCH_OBJ_R V6 string("id")
  .php   L0010 0013 VERIFY_RETURN_TYPE T7
  .php   L0010 0014 RETURN T7
  .php   L0010 0015 VERIFY_RETURN_TYPE
  .php   L0010 0016 RETURN null
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
  .php        ; (lines=9, args=2, vars=2, tmps=1)
  .php   L0022 0005 VERIFY_RETURN_TYPE V2
  .php   L0022 0006 RETURN V2
  .php   L0022 0007 VERIFY_RETURN_TYPE
  .php   L0022 0008 RETURN null
  .php   L0026 0006 V3 = DO_FCALL
  .php   L0026 0007 T4 = FETCH_OBJ_R V3 string("id")
  .php   L0026 0008 VERIFY_RETURN_TYPE T4
  .php   L0026 0009 RETURN T4
  .php   L0026 0010 VERIFY_RETURN_TYPE
  .php   L0026 0011 RETURN null
  .php   L0028 0007 V4 = DO_FCALL
  .php   L0028 0008 VERIFY_RETURN_TYPE V4
  .php   L0028 0009 RETURN V4
  .php   L0028 0010 VERIFY_RETURN_TYPE
  .php   L0028 0011 RETURN null
  .php   0000 V0 = NEW 1 string("Paging\\PaginatedList")
  .php        ; (lines=17, args=0, vars=0, tmps=8)
  .php   0009 V5 = DO_FCALL
  .php   0010 INIT_METHOD_CALL 0 V5 string("first")
  .php   0011 V6 = DO_FCALL
  .php   0012 T7 = FETCH_OBJ_R V6 string("id")
  .php   0013 VERIFY_RETURN_TYPE T7
  .php   0014 RETURN T7
  .php   0015 VERIFY_RETURN_TYPE
  .php   0016 RETURN null
  .php        7: 0013 - 0014 (tmp/var)
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
  .php   0006 SEND_VAR V0 1
  .php   0007 V3 = DO_UCALL
  .php   0008 VERIFY_RETURN_TYPE V3
  .php   0009 RETURN V3
  .php        0: 0002 - 0006 (new)
  .php        ; (lines=9, args=2, vars=2, tmps=1)
  .php   0005 VERIFY_RETURN_TYPE V2
  .php   0006 RETURN V2
  .php   0007 VERIFY_RETURN_TYPE
  .php   0008 RETURN null
  .php        2: 0005 - 0006 (tmp/var)
  .php   0007 V4 = DO_FCALL
  .php   0008 VERIFY_RETURN_TYPE V4
  .php   0009 RETURN V4
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
  .sharp      ; (lines=14, args=1, vars=2, tmps=6)
  .sharp L0008 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0008 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp L0008 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp L0008 0004 V4 = NEW 1 V2 T3
  .sharp L0008 0005 INIT_METHOD_CALL 0 CV0($query) string("rows")
  .sharp L0008 0006 V5 = DO_FCALL
  .sharp L0008 0007 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("List<Any?>, List<#0>")
  .sharp L0008 0008 SEND_VAL_EX T6 1
  .sharp L0008 0009 DO_FCALL
  .sharp L0008 0010 VERIFY_RETURN_TYPE V4
  .sharp L0008 0011 RETURN V4
  .sharp L0008 0012 VERIFY_RETURN_TYPE
  .sharp L0008 0013 RETURN null
  .sharp      ; (lines=14, args=1, vars=2, tmps=6)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp 0002 V2 = FETCH_CLASS (exception) string("Paging\\PaginatedList")
  .sharp 0003 T3 = SHARP_TYPE_ARGS CV1($) string("#0")
  .sharp 0004 V4 = NEW 1 V2 T3
  .sharp 0005 INIT_METHOD_CALL 0 CV0($query) string("rows")
  .sharp 0006 V5 = DO_FCALL
  .sharp 0007 T6 = SHARP_VERIFY_TYPE_ARGS V5 string("List<Any?>, List<#0>")
  .sharp 0008 SEND_VAL_EX T6 1
  .sharp 0009 DO_FCALL
  .sharp 0010 VERIFY_RETURN_TYPE V4
  .sharp 0011 RETURN V4
  .sharp 0012 VERIFY_RETURN_TYPE
  .sharp 0013 RETURN null
  .sharp      4: 0005 - 0010 (new)
  .sharp      4: 0010 - 0011 (tmp/var)
  .php        ; (lines=10, args=1, vars=1, tmps=3)
  .php   L0008 0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   L0008 0002 INIT_METHOD_CALL 0 CV0($query) string("rows")
  .php   L0008 0003 V2 = DO_FCALL
  .php   L0008 0004 SEND_VAR_NO_REF_EX V2 1
  .php   L0008 0005 DO_FCALL
  .php   L0008 0006 VERIFY_RETURN_TYPE V1
  .php   L0008 0007 RETURN V1
  .php   L0008 0008 VERIFY_RETURN_TYPE
  .php   L0008 0009 RETURN null
  .php        ; (lines=10, args=1, vars=1, tmps=3)
  .php   0001 V1 = NEW 1 string("Paging\\PaginatedList")
  .php   0002 INIT_METHOD_CALL 0 CV0($query) string("rows")
  .php   0003 V2 = DO_FCALL
  .php   0004 SEND_VAR_NO_REF_EX V2 1
  .php   0005 DO_FCALL
  .php   0006 VERIFY_RETURN_TYPE V1
  .php   0007 RETURN V1
  .php   0008 VERIFY_RETURN_TYPE
  .php   0009 RETURN null
  .php        1: 0002 - 0006 (new)
  .php        1: 0006 - 0007 (tmp/var)
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
  .sharp      ; (lines=9, args=1, vars=2, tmps=2)
  .sharp L0032 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp L0032 0002 INIT_METHOD_CALL 0 CV0($item) string("link")
  .sharp L0032 0003 V2 = DO_FCALL
  .sharp L0032 0004 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("string")
  .sharp L0032 0005 VERIFY_RETURN_TYPE T3
  .sharp L0032 0006 RETURN T3
  .sharp L0032 0007 VERIFY_RETURN_TYPE
  .sharp L0032 0008 RETURN null
  .sharp      ; (lines=28, args=2, vars=4, tmps=13)
  .sharp L0034 0004 V4 = DO_FCALL
  .sharp L0034 0005 T5 = CONCAT V4 string("#")
  .sharp L0034 0006 T6 = FETCH_OBJ_R CV1($entity) string("id")
  .sharp L0034 0007 T7 = CAST (string) T6
  .sharp L0034 0008 T8 = CONCAT T5 T7
  .sharp L0034 0009 T9 = ASSIGN CV2($index#1) T8
  .sharp L0034 0010 T10 = INIT_ARRAY 2 (packed) T9 NEXT
  .sharp L0034 0011 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp L0034 0012 V11 = FETCH_OBJ_W (ref) THIS string("saved")
  .sharp L0034 0013 SEND_REF V11 1
  .sharp L0034 0014 SEND_VAR CV2($index#1) 2
  .sharp L0034 0015 T12 = ASSIGN CV3($stored#2) CV1($entity)
  .sharp L0034 0016 SEND_VAL T12 3
  .sharp L0034 0017 V13 = DO_ICALL
  .sharp L0034 0018 V14 = FETCH_OBJ_UNSET THIS string("saved")
  .sharp L0034 0019 V14 = SEPARATE V14
  .sharp L0034 0020 JMPZ V13 0023
  .sharp L0034 0021 T15 = QM_ASSIGN CV3($stored#2)
  .sharp L0034 0022 JMP 0024
  .sharp L0034 0023 T15 = QM_ASSIGN CV3($stored#2)
  .sharp L0034 0024 T10 = ADD_ARRAY_ELEMENT T15 NEXT
  .sharp L0034 0025 T16 = FETCH_DIM_R T10 int(1)
  .sharp L0034 0026 FREE T16
  .sharp L0034 0027 RETURN null
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
  .sharp      ; (lines=9, args=1, vars=2, tmps=2)
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable")
  .sharp 0002 INIT_METHOD_CALL 0 CV0($item) string("link")
  .sharp 0003 V2 = DO_FCALL
  .sharp 0004 T3 = SHARP_VERIFY_TYPE_ARGS V2 string("string")
  .sharp      ; (lines=28, args=2, vars=4, tmps=13)
  .sharp 0004 V4 = DO_FCALL
  .sharp 0005 T5 = CONCAT V4 string("#")
  .sharp 0006 T6 = FETCH_OBJ_R CV1($entity) string("id")
  .sharp 0007 T7 = CAST (string) T6
  .sharp 0008 T8 = CONCAT T5 T7
  .sharp 0009 T9 = ASSIGN CV2($index#1) T8
  .sharp 0010 T10 = INIT_ARRAY 2 (packed) T9 NEXT
  .sharp 0011 INIT_FCALL 3 128 string("sharp\\internal\\put")
  .sharp 0012 V11 = FETCH_OBJ_W (ref) THIS string("saved")
  .sharp 0013 SEND_REF V11 1
  .sharp 0014 SEND_VAR CV2($index#1) 2
  .sharp 0015 T12 = ASSIGN CV3($stored#2) CV1($entity)
  .sharp 0016 SEND_VAL T12 3
  .sharp 0017 V13 = DO_ICALL
  .sharp 0018 V14 = FETCH_OBJ_UNSET THIS string("saved")
  .sharp 0019 V14 = SEPARATE V14
  .sharp 0020 JMPZ V13 0023
  .sharp 0021 T15 = QM_ASSIGN CV3($stored#2)
  .sharp 0022 JMP 0024
  .sharp 0023 T15 = QM_ASSIGN CV3($stored#2)
  .sharp 0024 T10 = ADD_ARRAY_ELEMENT T15 NEXT
  .sharp 0025 T16 = FETCH_DIM_R T10 int(1)
  .sharp 0026 FREE T16
  .sharp 0027 RETURN null
  .sharp      5: 0006 - 0008 (tmp/var)
  .sharp      10: 0011 - 0025 (tmp/var)
  .sharp      13: 0018 - 0020 (tmp/var)
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
  .php        ; (lines=7, args=1, vars=1, tmps=1)
  .php   L0032 0001 INIT_METHOD_CALL 0 CV0($item) string("link")
  .php   L0032 0002 V1 = DO_FCALL
  .php   L0032 0003 VERIFY_RETURN_TYPE V1
  .php   L0032 0004 RETURN V1
  .php   L0032 0005 VERIFY_RETURN_TYPE
  .php   L0032 0006 RETURN null
  .php        ; (lines=13, args=2, vars=2, tmps=7)
  .php   L0034 0004 V3 = DO_FCALL
  .php   L0034 0005 T4 = CONCAT V3 string("#")
  .php   L0034 0006 T5 = FETCH_OBJ_R CV1($entity) string("id")
  .php   L0034 0007 T6 = CAST (string) T5
  .php   L0034 0008 T7 = CONCAT T4 T6
  .php   L0034 0009 V2 = FETCH_OBJ_W (dim write) THIS string("saved")
  .php   L0034 0010 ASSIGN_DIM V2 T7
  .php   L0034 0011 OP_DATA CV1($entity)
  .php   L0034 0012 RETURN null
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
  .php   0012 RETURN null
  .php   0013 VERIFY_RETURN_TYPE
  .php   0014 RETURN null
  .php        3: 0003 - 0008 (loop)
  .php        3: 0010 - 0011 (loop)
  .php        ; (lines=4, args=1, vars=1, tmps=1)
  .php   0001 T1 = FETCH_DIM_R CV0($items) int(0)
  .php   0002 RETURN T1
  .php   0003 RETURN null
  .php        ; (lines=7, args=1, vars=1, tmps=1)
  .php   0001 INIT_METHOD_CALL 0 CV0($item) string("link")
  .php   0002 V1 = DO_FCALL
  .php   0003 VERIFY_RETURN_TYPE V1
  .php   0004 RETURN V1
  .php   0005 VERIFY_RETURN_TYPE
  .php   0006 RETURN null
  .php        1: 0003 - 0004 (tmp/var)
  .php        ; (lines=13, args=2, vars=2, tmps=7)
  .php   0006 T5 = FETCH_OBJ_R CV1($entity) string("id")
  .php   0007 T6 = CAST (string) T5
  .php   0008 T7 = CONCAT T4 T6
  .php   0009 V2 = FETCH_OBJ_W (dim write) THIS string("saved")
  .php   0010 ASSIGN_DIM V2 T7
  .php   0011 OP_DATA CV1($entity)
  .php   0012 RETURN null
  .php        4: 0006 - 0008 (tmp/var)
  .php        7: 0009 - 0010 (tmp/var)
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
  .sharp      ; (lines=86, args=3, vars=5, tmps=34)
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
  .sharp L0195 0053 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0195 0054 SEND_VAR CV0($payload) 1
  .sharp L0195 0055 SEND_VAL string("orderId") 2
  .sharp L0195 0056 V27 = DO_ICALL
  .sharp L0195 0057 T28 = ASSIGN CV3($orderId) V27
  .sharp L0195 0058 T29 = TYPE_CHECK (long) T28
  .sharp L0195 0059 T30 = BOOL_NOT T29
  .sharp L0195 0060 JMPZ T30 0065
  .sharp L0195 0061 V31 = NEW 1 string("Billing\\BadPayload")
  .sharp L0195 0062 SEND_VAL_EX string("orderId") 1
  .sharp L0195 0063 DO_FCALL
  .sharp L0195 0064 THROW V31
  .sharp L0196 0065 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp L0196 0066 SEND_VAR CV2($prices) 1
  .sharp L0196 0067 SEND_VAR CV1($plan) 2
  .sharp L0196 0068 V33 = DO_ICALL
  .sharp L0196 0069 T34 = ASSIGN CV4($price) V33
  .sharp L0196 0070 T35 = TYPE_CHECK (long) T34
  .sharp L0196 0071 T36 = BOOL_NOT T35
  .sharp L0196 0072 JMPZ T36 0078
  .sharp L0196 0073 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .sharp L0196 0074 SEND_VAR_EX CV1($plan) 1
  .sharp L0196 0075 V37 = DO_FCALL
  .sharp L0196 0076 VERIFY_RETURN_TYPE V37
  .sharp L0196 0077 RETURN V37
  .sharp L0197 0078 INIT_METHOD_CALL 2 THIS string("charge")
  .sharp L0197 0079 SEND_VAR_EX CV3($orderId) 1
  .sharp L0197 0080 SEND_VAR_EX CV4($price) 2
  .sharp L0197 0081 V38 = DO_FCALL
  .sharp L0197 0082 VERIFY_RETURN_TYPE V38
  .sharp L0197 0083 RETURN V38
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
  .sharp      ; (lines=86, args=3, vars=5, tmps=34)
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
  .sharp 0053 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0054 SEND_VAR CV0($payload) 1
  .sharp 0055 SEND_VAL string("orderId") 2
  .sharp 0056 V27 = DO_ICALL
  .sharp 0057 T28 = ASSIGN CV3($orderId) V27
  .sharp 0058 T29 = TYPE_CHECK (long) T28
  .sharp 0059 T30 = BOOL_NOT T29
  .sharp 0060 JMPZ T30 0065
  .sharp 0061 V31 = NEW 1 string("Billing\\BadPayload")
  .sharp 0062 SEND_VAL_EX string("orderId") 1
  .sharp 0063 DO_FCALL
  .sharp 0064 THROW V31
  .sharp 0065 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .sharp 0066 SEND_VAR CV2($prices) 1
  .sharp 0067 SEND_VAR CV1($plan) 2
  .sharp 0068 V33 = DO_ICALL
  .sharp 0069 T34 = ASSIGN CV4($price) V33
  .sharp 0070 T35 = TYPE_CHECK (long) T34
  .sharp 0071 T36 = BOOL_NOT T35
  .sharp 0072 JMPZ T36 0078
  .sharp 0073 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .sharp 0074 SEND_VAR_EX CV1($plan) 1
  .sharp 0075 V37 = DO_FCALL
  .sharp 0076 VERIFY_RETURN_TYPE V37
  .sharp 0077 RETURN V37
  .sharp 0078 INIT_METHOD_CALL 2 THIS string("charge")
  .sharp 0079 SEND_VAR_EX CV3($orderId) 1
  .sharp 0080 SEND_VAR_EX CV4($price) 2
  .sharp 0081 V38 = DO_FCALL
  .sharp 0082 VERIFY_RETURN_TYPE V38
  .sharp 0083 RETURN V38
  .sharp 0084 VERIFY_RETURN_TYPE
  .sharp 0085 RETURN null
  .sharp      8: 0008 - 0024 (new)
  .sharp      19: 0033 - 0049 (new)
  .sharp      31: 0062 - 0064 (new)
  .sharp      37: 0076 - 0077 (tmp/var)
  .sharp      38: 0082 - 0083 (tmp/var)
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .sharp 0000 CV0($key#1) = RECV 1
  .sharp 0001 CV1($value#2) = RECV 2
  .sharp 0002 T2 = CAST (string) CV0($key#1)
  .sharp 0004 T3 = ADD_ARRAY_ELEMENT CV1($value#2) NEXT
  .php        ; (lines=82, args=3, vars=5, tmps=30)
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
  .php   L0195 0049 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0195 0050 SEND_VAR CV0($payload) 1
  .php   L0195 0051 SEND_VAL string("orderId") 2
  .php   L0195 0052 V23 = DO_ICALL
  .php   L0195 0053 T24 = ASSIGN CV3($orderId) V23
  .php   L0195 0054 T25 = TYPE_CHECK (long) T24
  .php   L0195 0055 T26 = BOOL_NOT T25
  .php   L0195 0056 JMPZ T26 0061
  .php   L0195 0057 V27 = NEW 1 string("Billing\\BadPayload")
  .php   L0195 0058 SEND_VAL_EX string("orderId") 1
  .php   L0195 0059 DO_FCALL
  .php   L0195 0060 THROW V27
  .php   L0196 0061 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   L0196 0062 SEND_VAR CV2($prices) 1
  .php   L0196 0063 SEND_VAR CV1($plan) 2
  .php   L0196 0064 V29 = DO_ICALL
  .php   L0196 0065 T30 = ASSIGN CV4($price) V29
  .php   L0196 0066 T31 = TYPE_CHECK (long) T30
  .php   L0196 0067 T32 = BOOL_NOT T31
  .php   L0196 0068 JMPZ T32 0074
  .php   L0196 0069 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .php   L0196 0070 SEND_VAR_EX CV1($plan) 1
  .php   L0196 0071 V33 = DO_FCALL
  .php   L0196 0072 VERIFY_RETURN_TYPE V33
  .php   L0196 0073 RETURN V33
  .php   L0197 0074 INIT_METHOD_CALL 2 THIS string("charge")
  .php   L0197 0075 SEND_VAR_EX CV3($orderId) 1
  .php   L0197 0076 SEND_VAR_EX CV4($price) 2
  .php   L0197 0077 V34 = DO_FCALL
  .php   L0197 0078 VERIFY_RETURN_TYPE V34
  .php   L0197 0079 RETURN V34
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
  .php        ; (lines=82, args=3, vars=5, tmps=30)
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
  .php   0049 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0050 SEND_VAR CV0($payload) 1
  .php   0051 SEND_VAL string("orderId") 2
  .php   0052 V23 = DO_ICALL
  .php   0053 T24 = ASSIGN CV3($orderId) V23
  .php   0054 T25 = TYPE_CHECK (long) T24
  .php   0055 T26 = BOOL_NOT T25
  .php   0056 JMPZ T26 0061
  .php   0057 V27 = NEW 1 string("Billing\\BadPayload")
  .php   0058 SEND_VAL_EX string("orderId") 1
  .php   0059 DO_FCALL
  .php   0060 THROW V27
  .php   0061 INIT_FCALL 2 112 string("sharp\\internal\\get")
  .php   0062 SEND_VAR CV2($prices) 1
  .php   0063 SEND_VAR CV1($plan) 2
  .php   0064 V29 = DO_ICALL
  .php   0065 T30 = ASSIGN CV4($price) V29
  .php   0066 T31 = TYPE_CHECK (long) T30
  .php   0067 T32 = BOOL_NOT T31
  .php   0068 JMPZ T32 0074
  .php   0069 INIT_STATIC_METHOD_CALL 1 string("Billing\\Receipt") string("unknownPlan")
  .php   0070 SEND_VAR_EX CV1($plan) 1
  .php   0071 V33 = DO_FCALL
  .php   0072 VERIFY_RETURN_TYPE V33
  .php   0073 RETURN V33
  .php   0074 INIT_METHOD_CALL 2 THIS string("charge")
  .php   0075 SEND_VAR_EX CV3($orderId) 1
  .php   0076 SEND_VAR_EX CV4($price) 2
  .php   0077 V34 = DO_FCALL
  .php   0078 VERIFY_RETURN_TYPE V34
  .php   0079 RETURN V34
  .php   0080 VERIFY_RETURN_TYPE
  .php   0081 RETURN null
  .php        6: 0006 - 0022 (new)
  .php        15: 0029 - 0045 (new)
  .php        27: 0058 - 0060 (new)
  .php        33: 0072 - 0073 (tmp/var)
  .php        34: 0078 - 0079 (tmp/var)
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