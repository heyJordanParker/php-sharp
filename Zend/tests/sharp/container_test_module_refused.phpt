--TEST--
mago compile refuses a test module in a file that holds no test, naming the file: a program under tests/lib whose module says module : App would never run, since only a test of a class that extends PHPUnit's TestCase turns a test module on
--FILE--
<?php
require __DIR__ . '/program.inc';

// One issue to a line wherever the test runs: mago prints GitHub's annotations under GitHub Actions.
putenv('MAGO_REPORTING_FORMAT=emacs');
try {
    program_project('container-test-module-refused', 'vendor', [
        'composer.json' => '{"type": "project", "autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}' . "\n",
        'mago.toml' => "[source]\npaths = [\"app\", \"tests\"]\nincludes = [\"vendor\"]\n",
        'app/Shared/Runtime.sharp' => "namespace App.Shared;\n\npublic class Runtime\n{\n}\n",
        'app/Module.sharp' => "namespace App;\n\nimport App.Shared.Runtime;\n\nmodule\n{\n    singleton Runtime = Runtime;\n}\n",
        'tests/lib/Link.sharp' => <<<'SHARP'
namespace App.Testing.Lib;

import App.Shared.Runtime;

module : App
{
    singleton Runtime = Runtime;
}

public class Link
{
    public Link(private Runtime runtime) { }

    public void main() { }
}

SHARP,
    ]);
    echo "compiled\n";
} catch (RuntimeException $e) {
    echo implode("\n", preg_grep('/holds no test/', explode("\n", $e->getMessage()))), "\n";
}
?>
--EXPECTF--
%s`tests/lib/Link.sharp` holds no test, so its test module would never run.
