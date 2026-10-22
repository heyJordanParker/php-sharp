--TEST--
PHP# Files reads and writes whole files and folders, returns null for a missing one, and refuses a URL
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

$files = new Sharp\IO\Files();
$folder = sys_get_temp_dir() . '/sharp-effect-files-' . getmypid();
$files->createDirectory("$folder/nested/deeper");
$files->createDirectory("$folder/nested/deeper");
var_dump($files->isDirectory("$folder/nested/deeper"), $files->exists("$folder/nested"));

$file = "$folder/notes.txt";
var_dump($files->read($file), $files->readLines($file), $files->size($file), $files->lastModified($file));
$files->write($file, "one\r\ntwo\n");
$files->append($file, "three\n");
var_dump(json_encode($files->read($file)), $files->readLines($file), $files->size($file), $files->exists($file));
var_dump(abs($files->lastModified($file) - time()) <= 5);
// Lines read one at a time, each line ending dropped, and null for a missing file.
$lazy = $files->readLinesLazily($file);
foreach ($lazy as $line) {
    echo $line, '|';
}
echo "\n";
var_dump($lazy instanceof Generator, $files->readLinesLazily("$folder/missing.txt"));

$files->copy($file, "$folder/copy.txt");
$files->move("$folder/copy.txt", "$folder/moved.txt");
$listed = $files->list($folder);
sort($listed);
var_dump($listed, $files->list("$folder/missing"));

$files->write("$folder/empty.txt", '');
var_dump($files->readLines("$folder/empty.txt"));

$temporary = $files->temporaryFile();
var_dump($files->exists($temporary), $files->read($temporary));

foreach ([fn () => $files->read('http://example.com/'), fn () => $files->write('php://memory', 'x')] as $refused) {
    try {
        $refused();
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
foreach ([fn () => $files->copy("$folder/missing.txt", "$folder/other.txt"), fn () => $files->deleteDirectory($folder)] as $refused) {
    try {
        $refused();
    } catch (RuntimeException $e) {
        echo $e::class, ': ', str_replace($folder, '<folder>', $e->getMessage()), "\n";
    }
}

foreach ([$file, "$folder/moved.txt", "$folder/empty.txt", $temporary, "$folder/never-written.txt"] as $written) {
    $files->delete($written);
}
var_dump($files->exists($file));
$files->deleteDirectory("$folder/nested/deeper");
$files->deleteDirectory("$folder/nested");
$files->deleteDirectory($folder);
var_dump($files->isDirectory($folder));
?>
--EXPECT--
bool(true)
bool(false)
NULL
NULL
NULL
NULL
string(21) ""one\r\ntwo\nthree\n""
array(3) {
  [0]=>
  string(3) "one"
  [1]=>
  string(3) "two"
  [2]=>
  string(5) "three"
}
int(15)
bool(true)
bool(true)
one|two|three|
bool(true)
NULL
array(3) {
  [0]=>
  string(9) "moved.txt"
  [1]=>
  string(6) "nested"
  [2]=>
  string(9) "notes.txt"
}
NULL
array(0) {
}
bool(true)
string(0) ""
ValueError: Files.read: "http://example.com/" is a URL, and Files reads only files
ValueError: Files.write: "php://memory" is a URL, and Files reads only files
RuntimeException: Files.copy: "<folder>/missing.txt" cannot be copied to "<folder>/other.txt"
RuntimeException: Files.deleteDirectory: "<folder>" cannot be deleted
bool(false)
bool(false)
