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
Generics: different opcodes and lines in 33 op arrays, same signatures in 10 classes
  .sharp      ; (lines=14, args=1, vars=2, tmps=6)
  .sharp L0055 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity") string("Paging.Query<#0>")
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
  .sharp L0071 0001 SHARP_RECV_TYPE_ARGS string("$0")
  .sharp L0071 0002 VERIFY_RETURN_TYPE
  .sharp L0071 0003 RETURN null
  .sharp      ; (lines=16, args=2, vars=4, tmps=3)
  .sharp L0089 0002 CV2($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity")
  .sharp L0091 0003 V4 = FE_RESET_R CV0($items) 0012
  .sharp L0091 0004 FE_FETCH_R V4 CV3($item) 0012
  .sharp L0092 0005 T5 = FETCH_OBJ_R CV3($item) string("id")
  .sharp L0092 0006 T6 = IS_EQUAL CV1($id) T5
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
  .sharp L0106 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable") string("#0")
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
  .sharp      ; (lines=10, args=1, vars=1, tmps=3)
  .sharp L0130 0001 SHARP_RECV_TYPE_ARGS string("Paging.Feed<Paging.DatabaseEntity>")
  .sharp L0130 0002 T1 = CAST (object) CV0($feed)
  .sharp L0130 0003 INIT_METHOD_CALL 0 T1 string("next")
  .sharp L0130 0004 V2 = DO_FCALL
  .sharp L0130 0005 T3 = FETCH_OBJ_R V2 string("id")
  .sharp L0130 0006 VERIFY_RETURN_TYPE T3
  .sharp L0130 0007 RETURN T3
  .sharp L0130 0008 VERIFY_RETURN_TYPE
  .sharp L0130 0009 RETURN null
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
  .sharp      ; (lines=11, args=2, vars=2, tmps=2)
  .sharp L0134 0002 SHARP_RECV_TYPE_ARGS string("Paging.Validator<Paging.Order>, Any?")
  .sharp L0134 0003 T2 = CAST (object) CV0($validator)
  .sharp L0134 0004 INIT_METHOD_CALL 1 T2 string("validate")
  .sharp L0134 0005 SEND_VAR_EX CV1($order) 1
  .sharp L0134 0006 V3 = DO_FCALL
  .sharp L0134 0007 VERIFY_RETURN_TYPE V3
  .sharp L0134 0008 RETURN V3
  .sharp L0134 0009 VERIFY_RETURN_TYPE
  .sharp L0134 0010 RETURN null
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
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity") string("Paging.Query<#0>")
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
  .sharp 0006 T6 = IS_EQUAL CV1($id) T5
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
  .sharp 0001 CV1($) = SHARP_RECV_TYPE_ARGS string("Paging.DatabaseEntity & Paging.Shareable") string("#0")
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
  .sharp      ; (lines=10, args=1, vars=1, tmps=3)
  .sharp 0001 SHARP_RECV_TYPE_ARGS string("Paging.Feed<Paging.DatabaseEntity>")
  .sharp 0002 T1 = CAST (object) CV0($feed)
  .sharp 0003 INIT_METHOD_CALL 0 T1 string("next")
  .sharp 0004 V2 = DO_FCALL
  .sharp 0005 T3 = FETCH_OBJ_R V2 string("id")
  .sharp 0006 VERIFY_RETURN_TYPE T3
  .sharp 0007 RETURN T3
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
  .sharp      ; (lines=11, args=2, vars=2, tmps=2)
  .sharp 0002 SHARP_RECV_TYPE_ARGS string("Paging.Validator<Paging.Order>, Any?")
  .sharp 0003 T2 = CAST (object) CV0($validator)
  .sharp 0004 INIT_METHOD_CALL 1 T2 string("validate")
  .sharp 0005 SEND_VAR_EX CV1($order) 1
  .sharp 0006 V3 = DO_FCALL
  .sharp 0007 VERIFY_RETURN_TYPE V3
  .sharp 0008 RETURN V3
  .sharp      3: 0007 - 0008 (tmp/var)
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
  .php        ; (lines=3, args=1, vars=1, tmps=0)
  .php   L0071 0001 VERIFY_RETURN_TYPE
  .php   L0071 0002 RETURN null
  .php        ; (lines=15, args=2, vars=3, tmps=3)
  .php   L0091 0002 V3 = FE_RESET_R CV0($items) 0011
  .php   L0091 0003 FE_FETCH_R V3 CV2($item) 0011
  .php   L0092 0004 T4 = FETCH_OBJ_R CV2($item) string("id")
  .php   L0092 0005 T5 = IS_EQUAL CV1($id) T4
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
  .php   L0130 0001 T1 = CAST (object) CV0($feed)
  .php   L0130 0002 INIT_METHOD_CALL 0 T1 string("next")
  .php   L0130 0003 V2 = DO_FCALL
  .php   L0130 0004 T3 = FETCH_OBJ_R V2 string("id")
  .php   L0130 0005 VERIFY_RETURN_TYPE T3
  .php   L0130 0006 RETURN T3
  .php   L0130 0007 VERIFY_RETURN_TYPE
  .php   L0130 0008 RETURN null
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
  .php        ; (lines=10, args=2, vars=2, tmps=2)
  .php   L0134 0002 T2 = CAST (object) CV0($validator)
  .php   L0134 0003 INIT_METHOD_CALL 1 T2 string("validate")
  .php   L0134 0004 SEND_VAR_EX CV1($order) 1
  .php   L0134 0005 V3 = DO_FCALL
  .php   L0134 0006 VERIFY_RETURN_TYPE V3
  .php   L0134 0007 RETURN V3
  .php   L0134 0008 VERIFY_RETURN_TYPE
  .php   L0134 0009 RETURN null
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
  .php        1: 0002 - 0007 (new)
  .php        1: 0007 - 0008 (tmp/var)
  .php        ; (lines=15, args=2, vars=3, tmps=3)
  .php   0002 V3 = FE_RESET_R CV0($items) 0011
  .php   0003 FE_FETCH_R V3 CV2($item) 0011
  .php   0004 T4 = FETCH_OBJ_R CV2($item) string("id")
  .php   0005 T5 = IS_EQUAL CV1($id) T4
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
  .php   0003 V2 = DO_FCALL
  .php   0004 VERIFY_RETURN_TYPE V2
  .php   0005 RETURN V2
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
  .php   0001 T1 = CAST (object) CV0($feed)
  .php   0002 INIT_METHOD_CALL 0 T1 string("next")
  .php   0003 V2 = DO_FCALL
  .php   0004 T3 = FETCH_OBJ_R V2 string("id")
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
  .php        ; (lines=10, args=2, vars=2, tmps=2)
  .php   0002 T2 = CAST (object) CV0($validator)
  .php   0003 INIT_METHOD_CALL 1 T2 string("validate")
  .php   0004 SEND_VAR_EX CV1($order) 1
  .php   0005 V3 = DO_FCALL
  .php   0006 VERIFY_RETURN_TYPE V3
  .php   0007 RETURN V3
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
  .php   /** @template-covariant TItem of DatabaseEntity */
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
  .php       /** @param Feed<DatabaseEntity> $feed */
  .php       /** @param Validator<Order> $validator */
