--TEST--
A preloaded PHP# template shows what toString() returns, and plain PHP's string conversion of the object gives the same text, a toString() an interface's default body gives included
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/template_to_string_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(class_exists(Templates\Label::class, false));
$sku = new Templates\Sku('A-12');
echo (new Templates\Label())->show($sku, Templates\Grade::Gold), "\n";
echo (string) $sku, "\n";
echo "plain: {$sku}\n";
$widget = new Templates\Widget();
echo $widget->shown(), ' | ', (string) $widget, ' | ', htmlspecialchars($widget), "\n";
?>
--EXPECT--
bool(true)
Sku: A-12, grade: gold
A-12
plain: A-12
Widget: named widget | named widget | named widget
