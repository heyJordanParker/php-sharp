--TEST--
A preload script reads a binding, calls a PHP# method that checks its arguments from plain PHP, and runs a listener, and each request then does the same, as a request with no preloading does
--ENV--
SHARP_GREETING=hello
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('container-preload', [
    'app/Fresh/Paid.sharp' => "namespace App.Fresh;\n\npublic event Paid(int orderId);\n",
    'app/Fresh/Notices.sharp' => <<<'SHARP'
        namespace App.Fresh;

        public class Notices
        {
            public static List<string> log { get; set; } = [];

            public void hear(Paid e) on Paid { Notices.log.add("heard"); }

            public static int count(List<string> words) => words.count();
        }

        SHARP,
]);
// Each script reads the library's module as the library's autoloader does, and the app's modules as the container does.
$use = static fn (string $who): string => '<?php require ' . var_export(__DIR__ . '/Container.inc', true) . ';'
    . ' container_load(' . var_export($root, true) . ');'
    . ' $checkout = (new Sharp\Container())->get("App\\\\Shop\\\\Checkout");'
    . ' echo "' . $who . ', a binding and an expression over its parameters: ", $checkout->usedMailer()->name(), " ", $checkout->usedGreeting()->usedText(), "\n";'
    . ' echo "' . $who . ', a checked call from plain PHP: ", App\Fresh\Notices::count(["a", "b"]), "\n";'
    . ' Sharp\Internal\emit(new App\Fresh\Paid(1));'
    . ' echo "' . $who . ', a listener: ", implode(" ", App\Fresh\Notices::$log), "\n";';
file_put_contents("$root/preload.php", $use('preload'));
file_put_contents("$root/request.php", $use('request'));

echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d opcache.enable=1 -d opcache.enable_cli=1'
    . ' -d ' . escapeshellarg("opcache.preload=$root/preload.php") . ' ' . escapeshellarg("$root/request.php") . ' 2>&1');
remove_project($root);
?>
--EXPECT--
preload, a binding and an expression over its parameters: smtp hello
preload, a checked call from plain PHP: 2
preload, a listener: heard
request, a binding and an expression over its parameters: smtp hello
request, a checked call from plain PHP: 2
request, a listener: heard
