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
Collections: same opcodes and lines in 7 op arrays, same signatures in 2 classes
  check  Demo\Cart::fill string("List<Demo.Item>")
  check  Demo\Cart::withPrices string("List<int>, List<int>")
EnumKeys: same opcodes and lines in 16 op arrays, same signatures in 1 classes
  check  Demo\Headcount::count string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::ensure string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::lookup string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::without string("Map<Lib.Standing, int>, Any?")
  check  Demo\Headcount::byName string("Map<Lib.Standing, int>")
  check  Demo\Headcount::skus string("Map<string, int>")
  check  Demo\Headcount::grouped string("List<int>")
  check  Demo\Headcount::groupNames string("List<int>")
Interop: same opcodes and lines in 5 op arrays, same signatures in 1 classes
Lambdas: same opcodes and lines in 35 op arrays, same signatures in 1 classes
  check  Demo\Lambdas::doubled string("List<int>, Any?")
  check  Demo\Lambdas::pipeline string("List<int>, Map<string, int>")
  check  Demo\Lambdas::total string("List<int>")
  check  Demo\Lambdas::failures string("List<int>")
  check  Demo\Lambdas::captured string("List<int>")
  check  Demo\Lambdas::scaled string("List<int>")
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
Roster: same opcodes and lines in 6 op arrays, same signatures in 1 classes
  check  Demo\Roster::report string("Map<Lib.Standing, int>")
Sets: same opcodes and lines in 26 op arrays, same signatures in 1 classes
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
Store: same opcodes and lines in 3 op arrays, same signatures in 1 classes
RushOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
TypedOrders: same opcodes and lines in 2 op arrays, same signatures in 2 classes
Permalink: same opcodes and lines in 2 op arrays, same signatures in 1 classes
Library: same opcodes and lines in 19 op arrays, same signatures in 1 classes
  check  Demo\Library::query string("Map<string, Any?>")
Inbox: same opcodes and lines in 9 op arrays, same signatures in 1 classes
Patterns: same opcodes and lines in 15 op arrays, same signatures in 1 classes
  check  Demo\Patterns::lines string("List<Demo.Figure>")
  check  Demo\Patterns::head string("List<Demo.Figure>?")
  check  Demo\Patterns::ends string("List<Demo.Figure>")
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
  .php       /** @var array<string, DatabaseEntity> */
  .php       /** @var array<string, class-string<DatabaseEntity>> */
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
Operators: same opcodes and lines in 72 op arrays, same signatures in 9 classes
  check  Billing\Operators::byPrice string("List<Billing.Line>")
  check  Billing\Operators::byCode string("List<string>")
  check  Billing\Operators::checkout string("Map<string, Any?>, Any?, Map<string, Any?>")
  check  Billing\Grade::ranked string("List<Billing.Grade>")
a check: same
a check and another opcode: different
