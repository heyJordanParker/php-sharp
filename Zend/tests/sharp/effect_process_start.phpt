--TEST--
PHP# Process.start runs a program while the code goes on, as .NET's Process.Start and Rust's Command::spawn: the ChildProcess writes its input, closes it, reads its output and its error output to their end, waits for its exit code, and kills it, and Environment gives the machine's processor count and name
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';

$process = new Sharp\System\SystemProcess();

$cat = $process->start('cat');
$cat->writeInput("one line\n");
$cat->closeInput();
var_dump($cat->readOutput(), $cat->wait(), $cat->wait());

$shell = $process->start('sh', ['-c', 'read name; echo "out $name"; echo "err $name" >&2; exit 3']);
$shell->writeInput(str_repeat('x', 3) . "\n");
var_dump($shell->readError(), $shell->readOutput(), $shell->wait());

$waiting = $process->start('cat');
var_dump($waiting->wait());
try {
    $waiting->writeInput('late');
} catch (RuntimeException $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}

$sleeper = $process->start('sleep', ['30']);
$started = hrtime(true);
$sleeper->kill();
var_dump($sleeper->wait(), (hrtime(true) - $started) < 5_000_000_000);
$sleeper->kill();

$environment = new Sharp\System\SystemEnvironment();
var_dump(is_int($environment->processorCount()) && $environment->processorCount() >= 1, $environment->machineName() === gethostname());
?>
--EXPECT--
string(9) "one line
"
int(0)
int(0)
string(8) "err xxx
"
string(8) "out xxx
"
int(3)
int(0)
RuntimeException: ChildProcess.writeInput: the input is closed
int(-1)
bool(true)
bool(true)
bool(true)
