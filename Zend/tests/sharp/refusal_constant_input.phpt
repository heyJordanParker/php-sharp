--TEST--
The engine refuses a .sharp file once the plain PHP file that declares a constant it reads through its namespace changes, as a clean compile refuses it: the library's constant deleted or given another type, and a constant of PHP's root namespace given another type
--FILE--
<?php
require __DIR__ . '/project.inc';

const PDF = "<?php\n\nnamespace Vendor\\Pdf;\n\nconst DEFAULT_DPI = 300;\n";
const VERSION = "<?php\n\nconst APP_VERSION = 3;\n";

// A clean compile of the project as it stands, which refuses Report.sharp after each edit below.
function clean_compile(string $root): void
{
    try {
        run_mago($root);
        echo "the clean compile accepts Report.sharp\n";
    } catch (RuntimeException $e) {
        echo str_contains($e->getMessage(), 'Report.sharp') ? "the clean compile refuses Report.sharp\n" : $e->getMessage();
    }
}

$root = compile_project('constant-input', [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n",
    'Pdf.php' => PDF,
    'version.php' => VERSION,
    'Report.sharp' => <<<'SHARP'
namespace Demo;

import Vendor.Pdf;

class Report
{
    public static int dpi() => Pdf.DEFAULT_DPI;

    public static int version() => global.APP_VERSION;
}

SHARP,
]);
refusal("$root/Report.sharp");

foreach ([
    ['Pdf.php', PDF, ''],
    ['Pdf.php', PDF, str_replace('300', '"300"', PDF)],
    ['version.php', VERSION, str_replace('3', '"3"', VERSION)],
] as [$file, $before, $after]) {
    file_put_contents("$root/$file", $after === '' ? "<?php\n\nnamespace Vendor\\Pdf;\n" : $after);
    refusal("$root/Report.sharp");
    clean_compile($root);
    file_put_contents("$root/$file", $before);
    run_mago($root);
    refusal("$root/Report.sharp");
}
remove_project($root);
?>
--EXPECT--
ran
CompileError: Report.sharp is out of date (Pdf.php changed). Run vendor/bin/mago compile.
the clean compile refuses Report.sharp
ran
CompileError: Report.sharp is out of date (Pdf.php changed). Run vendor/bin/mago compile.
the clean compile refuses Report.sharp
ran
CompileError: Report.sharp is out of date (version.php changed). Run vendor/bin/mago compile.
the clean compile refuses Report.sharp
ran
