<?php

require __DIR__ . '/harness/Settings.inc';
require __DIR__ . '/Inbox.sharp';

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
