--TEST--
Spec section 29: under sharp.effect_report, each call of a plain PHP method an `extern` line declares records the built-ins it runs, past PHP# code it calls back, the autoload it starts and the files of its package, installed under the project's config.vendor-dir, and `mago effects` refuses a `uses none` line whose call ran PDO::query and gives a `uses any` line that ran no effect `uses none`
--FILE--
<?php
require __DIR__ . '/project.inc';

$library = 'libs/heyjordanparker/php-sharp-composer';
$files = [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n",
    // The project installs its packages in libs/, as its config.vendor-dir says.
    'composer.json' => '{"type": "library", "require": {"acme/libs": "*", "heyjordanparker/php-sharp-composer": "*"}, "config": {"vendor-dir": "libs"}}',
    "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
    'libs/acme/libs/src/Validator.php' => <<<'PHP'
        <?php

        namespace Acme\Libs;

        class Validator
        {
            public function check(\PDO $database): bool
            {
                return $database->query('SELECT 1') !== false;
            }
        }
        PHP,
    'libs/acme/libs/src/Formatter.php' => <<<'PHP'
        <?php

        namespace Acme\Libs;

        class Formatter
        {
            public function format(object $caller): string
            {
                $template = file_get_contents(__DIR__ . '/template.txt');

                return (new Helper())->wrap($template, $caller->shout('hi'));
            }
        }
        PHP,
    'libs/acme/libs/src/Helper.php' => <<<'PHP'
        <?php

        namespace Acme\Libs;

        class Helper
        {
            public function wrap(string $template, string $word): string
            {
                return sprintf($template, $word);
            }
        }
        PHP,
    'libs/acme/libs/src/template.txt' => '<%s>',
    'app/Stubs/Libraries.sharp' => <<<'SHARP'
        namespace App.Stubs;

        import Acme.Libs.Formatter;
        import Acme.Libs.Validator;

        extern Formatter.format uses any;
        extern Validator.check uses none;
        SHARP,
    'app/Demo/Caller.sharp' => <<<'SHARP'
        namespace App.Demo;

        public class Caller
        {
            public string shout(string word) => word.uppercase();
        }
        SHARP,
    'run.php' => <<<'PHP'
        <?php

        // Helper loads on demand, inside Formatter.format, and the built-ins the loader runs record nothing.
        spl_autoload_register(function (string $class): void {
            $file = __DIR__ . '/libs/acme/libs/src/' . substr($class, strlen('Acme\\Libs\\')) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
        require __DIR__ . '/libs/acme/libs/src/Validator.php';
        require __DIR__ . '/libs/acme/libs/src/Formatter.php';
        require __DIR__ . '/libs/heyjordanparker/php-sharp-composer/library/Sharp/Text/StringMembers.sharp';
        require __DIR__ . '/app/Demo/Caller.sharp';

        (new Acme\Libs\Validator())->check(new PDO('sqlite::memory:'));
        $formatter = new Acme\Libs\Formatter();
        echo $formatter->format(new App\Demo\Caller()), "\n";
        echo $formatter->format(new App\Demo\Caller()), "\n";
        PHP,
];
foreach (LIBRARY_MODULE as $class) {
    $files["$library/library/Sharp/$class.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp");
}
$root = compile_project('effect-report', $files);

$php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE'));
$report = "$root/effects.json";
// A run with the setting off writes no report.
echo shell_exec("$php -n " . escapeshellarg("$root/run.php"));
var_dump(file_exists($report));
echo shell_exec("$php -n -d " . escapeshellarg("sharp.effect_report=$report") . ' ' . escapeshellarg("$root/run.php"));
echo file_get_contents($report);

exec(escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --workspace ' . escapeshellarg($root) . ' effects ' . escapeshellarg($report) . ' 2>/dev/null', $output, $status);
echo implode("\n", $output), "\n";
echo "mago effects exited $status\n";
remove_project($root);
?>
--EXPECT--
<HI>
<HI>
bool(false)
<HI>
<HI>
{"Acme\\Libs\\Validator::check":{"calls":1,"ran":{"PDO::query":1}},"Acme\\Libs\\Formatter::format":{"calls":2,"ran":{"sprintf":2}}}
help: `Formatter.format` is declared `uses any`, and used nothing in 2 calls: declare it `uses none`.
error: `Validator.check` is declared `uses none` (app/Stubs/Libraries.sharp:7), but it ran PDO::query, which has the effect Database.
mago effects exited 1
