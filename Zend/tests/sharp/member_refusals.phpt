--TEST--
The checker names a string member as the code calls it and counts its arguments from the first one inside (…), lists the methods a string has when it lacks one, types a flatMap over Maps and a Map's toList by their [key, value] entries, and shows flatMap's function type without the type parameter no code writes
--FILE--
<?php
require __DIR__ . '/project.inc';

const LIBRARY = 'vendor/heyjordanparker/php-sharp-composer';

// A string's members call a List's, a List's call an int's, an int's take a ByteOrder, and the code calls a Map's,
// which the library declares with the `extern` lines of the PHP built-ins they call.
$files = [LIBRARY . '/composer.json' => '{"name": "heyjordanparker/php-sharp-composer"}'];
foreach (LIBRARY_MODULE as $member) {
    $files[LIBRARY . "/library/Sharp/$member.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$member.sharp");
}
$root = compile_project('member-refusals', $files);
file_put_contents("$root/Refusals.sharp", <<<'SHARP'
namespace Demo;

public class Refusals
{
    public bool held(string name) => name.contains(1);

    public string swapped(string name) => name.replace("a", 2);

    public int compared(string name) => name.shuffled("a");

    public List<int> values(List<Map<string, int>> shelves) => shelves.flatMap(shelf => shelf);

    public List<string> keys(Map<string, int> counts) => counts.toList();

    public List<int> counts(List<List<int>> rows) => rows.flatMap(row => row.count());
}

SHARP);
refusal("$root/Refusals.sharp", ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile']);
remove_project($root);
?>
--EXPECT--
CompileError: Refusals.sharp has 6 errors:
line 5: Invalid argument type for argument #1 of `string.contains`: expected `string`, but found `int`.
  The provided type `int` is not compatible with the expected type `string`.
  help: Change the argument value to match `string`, or update the parameter's type declaration.
line 7: Invalid argument type for argument #2 of `string.replace`: expected `string`, but found `int`.
  The provided type `int` is not compatible with the expected type `string`.
  help: Change the argument value to match `string`, or update the parameter's type declaration.
line 9: Method `shuffled` does not exist on `string`.
  help: A `string` has the methods `capitalized`, `characters`, `codePoints`, `compareTo`, `contains`, `decapitalized`, `endsWith`, `equals`, `indexOf`, `isDigit`, `isEmpty`, `isLetter`, `isLetterOrDigit`, `isLowercase`, `isNotEmpty`, `isUppercase`, `isWhitespace`, `lastIndexOf`, `length`, `lines`, `lowercase`, `normalized`, `padEnd`, `padStart`, `parseCsv`, `removePrefix`, `removeSuffix`, `repeat`, `replace`, `slug`, `split`, `startsWith`, `substring`, `titlecase`, `toBytes`, `trim`, `trimEnd`, `trimStart`, `uppercase`.
line 11: List<[string, int]> cannot be used as List<int>.
  A type parameter without `out` or `in` is invariant, so neither substitution is allowed.
line 13: List<[string, int]> cannot be used as List<string>.
  A type parameter without `out` or `in` is invariant, so neither substitution is allowed.
line 15: Invalid argument type for argument #1 of `List<List<int>>.flatMap`: expected `Function<Iterable<Any?>(List<int>)>`, but found `Function<int(List<int>)>`.
  The provided type `Function<int(List<int>)>` is not compatible with the expected type `Function<Iterable<Any?>(List<int>)>`.
  help: Change the argument value to match `Function<Iterable<Any?>(List<int>)>`, or update the parameter's type declaration.
