--TEST--
Each way a PHP# program ends exits with its code, after Rust's Termination: void main 0, an ExitCode main that code, a Result main's Ok its code and Err 1 with "Error: " and the message on stderr, a throw that escapes main 101 after an installed exception handler runs, the engine stopping it 101, and Process.exit its ExitCode at once
--FILE--
<?php
require __DIR__ . '/program.inc';

$program = static fn (string $name, string $returns, string $body, string $fields = '') => <<<SHARP
namespace App.Console;

import Sharp.Result;
import Sharp.System.Environment;
import Sharp.System.ExitCode;
import Sharp.System.Process;

public class $name
{
    public $name(private Environment environment, private Process process) { }
$fields
    public $returns main()
    {
$body
    }
}

SHARP;

// A program's main may return the library's Result, whose Err holds a Sharp.Error.
$library = dirname(__DIR__, 3) . '/sharp/composer/library/Sharp';
$root = program_project('program-exit-codes', 'vendor', [
    'vendor/heyjordanparker/php-sharp-composer/library/Sharp/Result.sharp' => file_get_contents("$library/Result.sharp"),
    'vendor/heyjordanparker/php-sharp-composer/library/Sharp/Error.sharp' => file_get_contents("$library/Error.sharp"),
    'app/Console/Quiet.sharp' => $program('Quiet', 'void', '        this.environment.arguments.count();'),
    'app/Console/Done.sharp' => $program('Done', 'ExitCode', '        return ExitCode.success;'),
    'app/Console/ImportError.sharp' => "namespace App.Console;\n\nimport Sharp.Error;\n\ninternal class ImportError : Error\n{\n    public ImportError(public string message { get; }) { }\n}\n",
    'app/Console/Load.sharp' => $program('Load', 'Result<ExitCode, ImportError>', '        return this.environment.arguments.count() < 2 ? Result.Err(new ImportError("file missing")) : Result.Ok(ExitCode.success);'),
    'app/Console/Crash.sharp' => $program('Crash', 'void', '        throw new RuntimeException("import broke");'),
    'app/Console/Hungry.sharp' => $program('Hungry', 'void', "        let text = \"x\";\n        let doubled = 0;\n        while (doubled < 40) {\n            text = `\${text}\${text}`;\n            doubled += 1;\n        }\n        if (this.environment.variable(text) is string) {\n            throw new LogicException(\"a variable has that name\");\n        }"),
    'app/Console/Stop.sharp' => $program('Stop', 'ExitCode', "        this.process.exit(new ExitCode(2));"),
    'handler.php' => "<?php\nset_exception_handler(static function (Throwable \$error): void { echo 'handled: ', \$error->getMessage(), \"\\n\"; });\n",
]);

// Runs each program as PHP runs a script, prints what it wrote to stdout and stderr apart, and collects its status.
$statuses = [];
foreach ([
    'app/Console/Quiet.sharp',
    'app/Console/Done.sharp',
    'app/Console/Import.sharp',
    'app/Console/Load.sharp rows.csv',
    'app/Console/Load.sharp',
    '-d auto_prepend_file=handler.php app/Console/Crash.sharp',
    '-d memory_limit=32M -d display_errors=0 app/Console/Hungry.sharp',
    'app/Console/Stop.sharp',
] as $command) {
    $php = escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' ' . getenv('TEST_PHP_EXTRA_ARGS');
    exec('cd ' . escapeshellarg($root) . " && $php $command 2>stderr.txt", $output, $status);
    $errors = trim(file_get_contents("$root/stderr.txt"));
    echo "php $command: exit $status\n";
    foreach ($output as $line) {
        echo "  stdout: $line\n";
    }
    if ($errors !== '' && !str_contains($command, 'Crash')) {
        echo "  stderr: $errors\n";
    }
    $output = [];
    $statuses[] = $status;
}
echo implode(' ', $statuses), "\n";

remove_project($root);
?>
--EXPECT--
php app/Console/Quiet.sharp: exit 0
php app/Console/Done.sharp: exit 0
php app/Console/Import.sharp: exit 64
php app/Console/Load.sharp rows.csv: exit 0
php app/Console/Load.sharp: exit 1
  stderr: Error: file missing
php -d auto_prepend_file=handler.php app/Console/Crash.sharp: exit 101
  stdout: handled: import broke
php -d memory_limit=32M -d display_errors=0 app/Console/Hungry.sharp: exit 101
php app/Console/Stop.sharp: exit 2
0 0 64 0 1 101 101 2
