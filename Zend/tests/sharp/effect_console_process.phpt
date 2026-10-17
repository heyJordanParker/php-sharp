--TEST--
PHP# Console writes standard output and standard error and reads standard input, and Process runs a program from an argument list and exits with a code from 0 to 254, skipping every finally
--STDIN--
first line
second line
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

use Sharp\Time\Duration;

$console = new Sharp\Console();
$console->write('no newline, ');
$console->writeLine('then a newline');
var_dump($console->readLine(), $console->readLine(), $console->readLine());

$process = new Sharp\Process();
var_dump($process->id() === getmypid());
$php = getenv('TEST_PHP_EXECUTABLE');
$classes = 'require ' . var_export(__DIR__ . '/effect_classes.inc', true) . ';';

$result = $process->run($php, ['-n', '-r', $classes . ' $c = new Sharp\Console(); $c->write("out"); $c->writeError("err"); exit(3);']);
var_dump($result->exitCode, $result->output, $result->errorOutput, $result->successful);

// The argument reaches the program as it is written, with no shell to read its quotes and semicolons.
$echoed = $process->run($php, ['-n', '-r', 'echo $argv[1];', '--', '"quoted"; $(id)']);
var_dump($echoed->output, $echoed->successful);

$exited = $process->run($php, ['-n', '-r', $classes . ' try { (new Sharp\Process())->exit(4); } finally { echo "finally ran"; }']);
var_dump($exited->exitCode, $exited->output);

// A code past 254 is refused, as a negative one is, so the process keeps running.
foreach ([-1, 255] as $code) {
    try {
        $process->exit($code);
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
$highest = $process->run($php, ['-n', '-r', $classes . ' (new Sharp\Process())->exit(254);']);
var_dump($highest->exitCode);

try {
    $process->run($php, ['-n', '-r', 'sleep(5);'], Duration::ofMilliseconds(200));
} catch (RuntimeException $e) {
    echo $e::class, ': ', str_replace($php, '<php>', $e->getMessage()), "\n";
}
?>
--EXPECT--
no newline, then a newline
string(10) "first line"
string(11) "second line"
NULL
bool(true)
int(3)
string(3) "out"
string(3) "err"
bool(false)
string(15) ""quoted"; $(id)"
bool(true)
int(4)
string(0) ""
ValueError: Process.exit: the code -1 is not from 0 to 254
ValueError: Process.exit: the code 255 is not from 0 to 254
int(254)
RuntimeException: Process.run: <php> ran past its timeout
