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
  .php   0000 V0 = NEW 1 string("Paging\\PaginatedList")
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
