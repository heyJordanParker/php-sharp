--TEST--
PHP# receives Any? from plain PHP, keeps it and passes it back to plain PHP unchanged
--FILE--
<?php

require __DIR__ . '/harness/Settings.inc';
require __DIR__ . '/vendor/autoload.php';

use Demo\Inbox;

$inbox = new Inbox();
foreach (['plan', 'seats', 'tags', 'trial', 'missing'] as $key) {
    echo $key, ': ', $inbox->show($key), "\n";
}
var_dump($inbox->read('seats'), $inbox->raw('tags'), $inbox->raw('trial'));
var_dump($inbox->keep(new ArrayObject([1])) instanceof ArrayObject, $inbox->keep(1.5));
var_dump($inbox->isPlan('pro'), $inbox->isPlan('free'));

foreach (['plan', 'tags', 'trial'] as $key) {
    $inbox->save($key);
}
foreach (['plan', 'tags', 'trial', 'missing'] as $key) {
    echo "saved $key: ", $inbox->showSaved($key), "\n";
}
var_dump($inbox->load('tags') === ['a', 'b'], $inbox->load('missing'));

$plans = 0;
for ($i = 0; $i < 200; $i++) {
    $plans += $inbox->isPlan($i % 2 ? 'pro' : 'free') ? 1 : 0;
    $inbox->keep($inbox->read($i % 3 ? 'seats' : 'plan'));
}
echo "plans $plans, last ", json_encode($inbox->keep($inbox->read('seats'))), "\n";
?>
--EXPECT--
plan: string "pro"
seats: int 5
tags: array ["a","b"]
trial: null null
missing: null null
int(5)
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
NULL
bool(true)
float(1.5)
bool(true)
bool(false)
saved plan: string "pro"
saved tags: array ["a","b"]
saved trial: null null
saved missing: null null
bool(true)
NULL
plans 100, last 5
