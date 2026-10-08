--TEST--
PHP# Position.current() gives the file, directory, line, column and dotted function name where it is written
--FILE--
<?php

require __DIR__ . '/../sharp/Positions.sharp';

$here = (new Demo\Positions())->here();
var_dump($here->file === realpath(__DIR__ . '/Positions.sharp'));
var_dump($here->file === (new ReflectionClass(Demo\Positions::class))->getFileName());
var_dump($here->directory === __DIR__);
echo $here->line, ':', $here->column, ' ', $here->function, "\n";
$folder = (new Demo\Positions())->folder;
echo $folder->line, ':', $folder->column, ' ', $folder->function, "\n";
$positions = new Demo\Positions();
echo $positions->slug, "\n";
$positions->title = null;
echo $positions->title, "\n";
$positions->title = 'given';
echo $positions->title, "\n";

$given = new Sharp\Position('/srv/app/Reports.sharp', 12, 9, 'App.Reports.run');
echo $given->file, ' ', $given->directory, ' ', $given->line, ':', $given->column, ' ', $given->function, "\n";
echo (new Sharp\Position('Reports.sharp', 1, 1, 'App.run'))->directory, "\n";

try {
    $given->line = 13;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $given->__construct('/srv/other.sharp', 1, 1, 'App.other');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
echo $given->file, "\n";
var_dump((new ReflectionClass(Sharp\Position::class))->isFinal(), method_exists(Sharp\Position::class, 'current'));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
7:16 Demo.Positions.here
10:31 Demo.Positions.folder
Demo.Positions.slug
Demo.Positions.title
given
/srv/app/Reports.sharp /srv/app 12:9 App.Reports.run
.
Cannot modify readonly property Sharp\Position::$line
Cannot modify readonly property Sharp\Position::$file
/srv/app/Reports.sharp
bool(true)
bool(false)
