--TEST--
The container finds a class's modules by its namespace and each namespace above it, wherever the class's file is: a program under tests/lib that declares App.Testing.Lib runs with the service App's module binds, never the one its folder's module binds for Tests.Lib, a program of Tests.Lib in that folder takes its own module's, and app/Console's program takes App's as before
--FILE--
<?php
require __DIR__ . '/program.inc';

// Prints the name of the Runtime the container builds the program with.
$program = fn (string $namespace, string $class) => <<<SHARP
namespace $namespace;

import App.Shared.Runtime;
import Sharp.IO.Console;

public class $class
{
    public $class(private Runtime runtime, private Console console) { }

    public void main()
    {
        this.console.writeLine(this.runtime.name());
    }
}

SHARP;
$runtime = fn (string $class, string $name) => "namespace App.Shared;\n\npublic class $class : Runtime\n{\n    public string name() => \"$name\";\n}\n";

$root = program_project('container-namespace-modules', 'vendor', [
    'composer.json' => '{"type": "project", "autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}' . "\n",
    'mago.toml' => "[source]\npaths = [\"app\", \"tests\"]\nincludes = [\"vendor\"]\n",
    'app/Shared/Runtime.sharp' => "namespace App.Shared;\n\npublic interface Runtime\n{\n    string name();\n}\n",
    'app/Shared/LiveRuntime.sharp' => $runtime('LiveRuntime', 'live'),
    'app/Shared/QuietRuntime.sharp' => $runtime('QuietRuntime', 'quiet'),
    'app/Module.sharp' => "namespace App;\n\nimport App.Shared.Runtime;\nimport App.Shared.LiveRuntime;\n\nmodule\n{\n    singleton Runtime = LiveRuntime;\n}\n",
    'tests/lib/Module.sharp' => "namespace Tests.Lib;\n\nimport App.Shared.Runtime;\nimport App.Shared.QuietRuntime;\n\nmodule\n{\n    singleton Runtime = QuietRuntime;\n}\n",
    'tests/lib/Link.sharp' => $program('App.Testing.Lib', 'Link'),
    'tests/lib/Seed.sharp' => $program('Tests.Lib', 'Seed'),
    'app/Console/Report.sharp' => $program('App.Console', 'Report'),
]);

run_program($root, 'tests/lib/Link.sharp');
run_program($root, 'tests/lib/Seed.sharp');
run_program($root, 'app/Console/Report.sharp');

remove_project($root);
?>
--EXPECT--
php tests/lib/Link.sharp
  live
exit 0
php tests/lib/Seed.sharp
  quiet
exit 0
php app/Console/Report.sharp
  live
exit 0
