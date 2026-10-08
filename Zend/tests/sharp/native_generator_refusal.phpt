--TEST--
sharp/bin/native refuses an extern declaration it cannot read, naming its file and line, and writes nothing
--FILE--
<?php
require __DIR__ . '/native_generator.inc';

$copy = sys_get_temp_dir() . '/sharp_native_generator_refusal';
native_generator_remove($copy);
native_generator_copy($copy);
file_put_contents("$copy/sharp/composer/library/Sharp/Text/Join.sharp", <<<'SHARP'
namespace Sharp.Text;

public static class Join
{
    public static extern string lines(List<string> lines);
}

SHARP);

$status = native_generator_run($copy);
echo 'exits with ', $status, "\n";
var_dump(file_exists("$copy/ext/sharp/sharp_native.h"), file_exists("$copy/sharp/composer/native.php"));
native_generator_remove($copy);
?>
--EXPECT--
sharp/composer/library/Sharp/Text/Join.sharp:5: sharp/bin/native cannot read `public static extern string lines(List<string> lines);`. It reads `public static extern <type> <name>(<parameters>);` in a `public static class` under `namespace Sharp`, where every type is string, int, float or bool, the return type may be void, and every parameter after one with a default has a default too.
exits with 1
bool(false)
bool(false)
