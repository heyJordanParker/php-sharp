--TEST--
PHP# Console writes standard output and standard error and reads standard input, and Process runs a program from an argument list and exits skipping every finally
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
RuntimeException: Process.run: <php> ran past its timeout
