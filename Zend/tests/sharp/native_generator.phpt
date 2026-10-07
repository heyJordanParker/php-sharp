--TEST--
sharp/bin/native writes the same native interface on every run, and an edited Rust source or a native dependency's version changes SHARP_NATIVE while a Mago pin move does not
--FILE--
<?php
require __DIR__ . '/native_generator.inc';

$copy = sys_get_temp_dir() . '/sharp_native_generator';
native_generator_remove($copy);
native_generator_copy($copy);
$header = "$copy/ext/sharp/sharp_native.h";
$library = "$copy/sharp/composer/native.php";

$status = native_generator_run($copy);
echo 'first run exits with ', $status, "\n";
$first = [file_get_contents($header), file_get_contents($library)];
$status = native_generator_run($copy);
echo 'second run exits with ', $status, "\n";
echo [file_get_contents($header), file_get_contents($library)] === $first ? 'same' : 'different', " files on the second run\n";

preg_match('/^#define SHARP_NATIVE "([0-9a-f]{32})"$/m', $first[0], $define);
preg_match("/^return '([0-9a-f]{32})';$/m", $first[1], $returned);
echo $define[1] === $returned[1] ? 'one' : 'two', " SHARP_NATIVE in sharp_native.h and native.php\n";

file_put_contents("$copy/ext/sharp/src/lib.rs", "\n", FILE_APPEND);
$status = native_generator_run($copy);
echo 'run after the edit exits with ', $status, "\n";
echo native_generator_fingerprint($copy) === $returned[1] ? 'same' : 'new', " SHARP_NATIVE after a Rust source changed\n";

$fingerprints = [];
foreach ([
    'the fixture lock' => ['1.6.2', '0f7dc46623c29f03770d5805e51b76836281bd22', '1.53.0'],
    'the mago-sharp-bridge rev moved' => ['1.6.2', 'cbdadc778de7dc92c7c08be8064d476647ddcd09', '1.53.0'],
    'a package only the bridge uses changed' => ['1.6.2', '0f7dc46623c29f03770d5805e51b76836281bd22', '1.54.0'],
    'deunicode moved to 1.6.1' => ['1.6.1', '0f7dc46623c29f03770d5805e51b76836281bd22', '1.53.0'],
] as $lock => [$deunicode, $rev, $syntax]) {
    native_generator_lock($copy, $deunicode, $rev, $syntax);
    $status = native_generator_run($copy);
    $fingerprints[$lock] = native_generator_fingerprint($copy);
    echo $status === 0 ? '' : "exits with $status: ", $fingerprints[$lock] === reset($fingerprints) ? 'same' : 'new', " SHARP_NATIVE with $lock\n";
}
native_generator_remove($copy);
?>
--EXPECT--
first run exits with 0
second run exits with 0
same files on the second run
one SHARP_NATIVE in sharp_native.h and native.php
run after the edit exits with 0
new SHARP_NATIVE after a Rust source changed
same SHARP_NATIVE with the fixture lock
same SHARP_NATIVE with the mago-sharp-bridge rev moved
same SHARP_NATIVE with a package only the bridge uses changed
new SHARP_NATIVE with deunicode moved to 1.6.1
