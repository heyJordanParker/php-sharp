--TEST--
PHP# ZipArchive creates an archive with an entry from text and one from a file, writes it when closed, an empty one too, reopens it, lists, reads and extracts every entry, one added since it opened too, gives null for a missing entry, and throws with the path and libzip's reason for a file that isn't a ZIP, a missing file to add, or a close that cannot write, with no PHP warning
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/../../../sharp/composer/library/Sharp/IO/ZipArchive.sharp';
require __DIR__ . '/ZipQueries.sharp';

use Demo\ZipQueries;

$folder = sys_get_temp_dir() . '/sharp-zip-' . getmypid();
mkdir($folder);
file_put_contents("$folder/report.csv", "id,total\n1,30\n");

var_dump(ZipQueries::roundTrip($folder));
foreach (['readme.txt', 'data/report.csv', 'later.txt'] as $entry) {
    var_dump(file_get_contents("$folder/out/$entry"));
}
var_dump(ZipQueries::emptyCount("$folder/empty.zip"));

foreach ([fn () => ZipQueries::openArchive("$folder/report.csv"), fn () => ZipQueries::openArchive("$folder/missing.zip"), fn () => ZipQueries::addMissing("$folder/other.zip", "$folder/missing.csv"), fn () => ZipQueries::closeUnwritable("$folder/missing/notes.zip")] as $refused) {
    try {
        $refused();
    } catch (RuntimeException $error) {
        echo get_class($error), ': ', str_replace($folder, '<folder>', $error->getMessage()), "\n";
    }
}

foreach (['out/readme.txt', 'out/data/report.csv', 'out/later.txt', 'notes.zip', 'empty.zip', 'other.zip', 'report.csv'] as $file) {
    unlink("$folder/$file");
}
rmdir("$folder/out/data");
rmdir("$folder/out");
rmdir($folder);
?>
--EXPECT--
array(4) {
  [0]=>
  string(10) "readme.txt"
  [1]=>
  string(15) "data/report.csv"
  [2]=>
  string(7) "Read me"
  [3]=>
  NULL
}
string(7) "Read me"
string(14) "id,total
1,30
"
string(5) "Later"
int(0)
RuntimeException: ZipArchive.open: "<folder>/report.csv" cannot be opened: Not a zip archive
RuntimeException: ZipArchive.open: "<folder>/missing.zip" cannot be opened: No such file
RuntimeException: ZipArchive.addFile: "<folder>/missing.csv" is not a file
RuntimeException: ZipArchive.close: "<folder>/missing/notes.zip" cannot be written: Failure to create temporary file: No such file or directory
