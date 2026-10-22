--TEST--
The standard library's effect classes run when opcache preloads them
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/effect_classes.inc
--EXTENSIONS--
opcache
pdo_sqlite
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
// Preloading declared the classes before this request, which never ran the file that declares them.
var_dump(class_exists(Sharp\Data\Database::class, false));
require __DIR__ . '/effect_classes.inc';

var_dump(abs((new Sharp\Time\Clock())->now()->epochSeconds - time()) <= 1);
var_dump((new Sharp\Random(7))->nextInt(0, 100) === (new Sharp\Random(7))->nextInt(0, 100));
(new Sharp\IO\Console())->writeLine('written');
$result = (new Sharp\System\Process())->run(getenv('TEST_PHP_EXECUTABLE'), ['-n', '-r', 'echo "ran";']);
var_dump($result->output, $result->successful);
$files = new Sharp\IO\Files();
$temporary = $files->temporaryFile();
$files->write($temporary, 'kept');
var_dump($files->read($temporary));
$files->delete($temporary);
$database = new Sharp\Data\Database('sqlite::memory:');
var_dump($database->scalar('SELECT 6 * 7'));
$path = sys_get_temp_dir() . '/sharp-effect-preload-' . getmypid() . '.sqlite';
$cache = new Sharp\Data\Cache($path);
$cache->set('k', 'v');
var_dump($cache->get('k'));
unset($cache);
unlink($path);
try {
    (new Sharp\Net\Http())->get('not a url');
} catch (ValueError $e) {
    echo $e->getMessage(), "\n";
}
var_dump((new Sharp\Net\MailAddress('ann@example.com', 'Ann'))->header);
var_dump(Sharp\Time\Duration::ofSeconds(2)->seconds);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
written
string(3) "ran"
bool(true)
string(4) "kept"
int(42)
string(1) "v"
Http.send: "not a url" is not a valid URL
string(21) "Ann <ann@example.com>"
int(2)
