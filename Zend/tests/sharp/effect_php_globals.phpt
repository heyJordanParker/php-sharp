--TEST--
PHP# PhpGlobals sets a PHP global variable and a key of $_SERVER, runs a PHP file in the global scope, which reads both and leaves globals of its own, and reads them back, and the script's own variables see each change
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

$page = sys_get_temp_dir() . '/sharp-legacy-page-' . getmypid() . '.php';
file_put_contents($page, <<<'PHP'
<?php
echo "tenant $tenant on {$_SERVER['HTTP_HOST']}\n";
$tenant = strtoupper($tenant);
$counter++;
$rendered = "page for $tenant";
PHP);

$counter = 1;
$globals = new Sharp\Interop\SystemPhpGlobals();
echo implode("\n", Demo\GlobalQueries::page($globals, $page)), "\n";
var_dump($tenant, $counter, $rendered, $_SERVER['HTTP_HOST']);

try {
    Demo\GlobalQueries::missing($globals);
} catch (RuntimeException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
unlink($page);
?>
--EXPECT--
tenant acme on shop.example
page for ACME
ACME
2
shop.example
unset
string(4) "ACME"
int(2)
string(13) "page for ACME"
string(12) "shop.example"
RuntimeException: PhpGlobals.include: "/nonexistent/legacy-page.php" cannot be read
