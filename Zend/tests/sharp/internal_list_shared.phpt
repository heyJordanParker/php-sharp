--TEST--
A Sharp\Internal function that changes a List finds the change first, so a List another variable shares is copied only when it changes: remove that finds nothing, and set, insert and removeAt outside the List, leave it shared
--INI--
zend.exception_ignore_args=1
--FILE--
<?php
$attempts = [
    'remove' => fn (array &$list) => var_export(Sharp\Internal\remove($list, 9)),
    'set' => fn (array &$list) => Sharp\Internal\set($list, 7, 9),
    'insert' => fn (array &$list) => Sharp\Internal\insert($list, 7, 9),
    'removeAt' => fn (array &$list) => Sharp\Internal\removeAt($list, 7),
    'add' => fn (array &$list) => Sharp\Internal\add($list, 9),
];
foreach ($attempts as $name => $attempt) {
    $original = range(1, 3);
    $shared = $original;
    try {
        $attempt($shared);
    } catch (OutOfRangeException $error) {
        echo $error->getMessage();
    }
    echo " $name: ", $shared === $original ? 'unchanged' : 'changed', ', ';
    // The array's holders: $original, and $shared unless the function copied it, and debug_zval_dump's argument.
    ob_start();
    debug_zval_dump($original);
    preg_match('~refcount\((\d+)\)~', ob_get_clean(), $refcount);
    echo $refcount[1] - 1 === 2 ? 'shared' : 'copied', "\n";
}
?>
--EXPECT--
false remove: unchanged, shared
Undefined array key 7 set: unchanged, shared
Undefined array key 7 insert: unchanged, shared
Undefined array key 7 removeAt: unchanged, shared
 add: changed, copied
