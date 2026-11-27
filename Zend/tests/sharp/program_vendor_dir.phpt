--TEST--
mago compile records the Composer autoloader of the vendor folder composer.json's config.vendor-dir names in .sharp/autoload.php, which a program loads
--FILE--
<?php
require __DIR__ . '/program.inc';

$root = program_project('program-vendor-dir', 'lib');

run_program($root, 'app/Console/Import.sharp data.csv');
run_program($root, 'app/Console/Import.sharp');

echo file_get_contents("$root/.sharp/autoload.php");

remove_project($root);
?>
--EXPECT--
php app/Console/Import.sharp data.csv
exit 0
php app/Console/Import.sharp
exit 64
<?php

// mago compile writes this file, the project's Composer autoloader. It rewrites it on every compile.

require_once __DIR__ . '/../lib/autoload.php';
