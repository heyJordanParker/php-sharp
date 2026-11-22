--TEST--
A .sharp file that auto_prepend_file names and whose class declares void main runs main before the script, which then runs as PHP runs it
--FILE--
<?php
require __DIR__ . '/program.inc';

$root = program_project('program-prepend', 'vendor', [
    'app/Http/Boot.sharp' => <<<'SHARP'
namespace App.Http;

import Sharp.IO.Console;

public class Boot
{
    public Boot(private Console console) { }

    public void main()
    {
        this.console.writeLine("booted");
    }
}

SHARP,
    'public/index.php' => "<?php\n\necho \"front controller\\n\";\n",
]);

run_program($root, '-d auto_prepend_file=app/Http/Boot.sharp public/index.php');

remove_project($root);
?>
--EXPECT--
php -d auto_prepend_file=app/Http/Boot.sharp public/index.php
  booted
  front controller
exit 0
