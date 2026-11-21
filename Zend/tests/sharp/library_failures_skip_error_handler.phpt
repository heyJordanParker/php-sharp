--TEST--
A standard library call whose failure the library reads gives the result it documents when the app's error handler throws on every warning: the warning reaches no handler. A plain PHP @ still calls the handler
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Sharp\Encoding\Compression;
use Sharp\Encoding\Hex;
use Sharp\Encoding\Serialized;
use Sharp\IO\Files;
use Sharp\System\Environment;
use Sharp\Text\Regex;

set_error_handler(function (int $level, string $message) {
    throw new ErrorException($message, 0, $level);
});

function outcome(callable $call): void
{
    try {
        var_dump($call());
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}

$folder = sys_get_temp_dir() . '/sharp-quiet-' . getmypid();
$file = "$folder/file";
mkdir($folder);
touch($file);
$files = new Files();

outcome(fn () => Regex::tryParse('(['));
outcome(fn () => Regex::parse('(['));
outcome(fn () => Compression::uncompress('nope'));
outcome(fn () => Hex::decode('abc'));
outcome(fn () => Serialized::decode('not serialized'));
outcome(fn () => $files->copy("$folder/missing", "$folder/copy"));
outcome(fn () => $files->move("$folder/missing", "$folder/moved"));
outcome(fn () => $files->createDirectory("$file/below"));
outcome(fn () => $files->deleteDirectory("$folder/missing"));
outcome(fn () => $files->write("$folder/missing/file", 'text'));
outcome(fn () => (new Environment())->changeDirectory("$folder/missing"));
outcome(fn () => @hex2bin('abc'));

unlink($file);
rmdir($folder);
?>
--EXPECTF--
NULL
ValueError: Regex.parse: "([" does not compile
ValueError: Compression.uncompress: the data is not zlib-compressed
ValueError: Hex.decode: the text is not hexadecimal with an even length
NULL
RuntimeException: Files.copy: "%s/missing" cannot be copied to "%s/copy"
RuntimeException: Files.move: "%s/missing" cannot be moved to "%s/moved"
RuntimeException: Files.createDirectory: "%s/file/below" cannot be created
RuntimeException: Files.deleteDirectory: "%s/missing" cannot be deleted
RuntimeException: Files.write: "%s/missing/file" cannot be written
RuntimeException: Environment.changeDirectory: the directory "%s/missing" cannot be entered
ErrorException: hex2bin(): Hexadecimal input string must have an even length
