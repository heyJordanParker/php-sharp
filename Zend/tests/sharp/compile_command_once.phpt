--TEST--
With sharp.compile_command set, requests that find a file stale at once compile the project one at a time: the first runs the compile, and each other one waits for it, checks its file again, and runs the command, which replays the failed compile from mago's record without compiling. The command runs in a process group of its own, which ends with the request that started it, so no process it started outlives it, a request killed mid-compile included
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('compile-command-once', ['Counter.sharp' => COUNTER]);
file_put_contents("$root/Torn.sharp", "namespace Demo;\n\nclass Torn\n{\n    public int run( => 1;\n}\n");
// Each run logs when it starts, and then whether mago compiled, which rewrites its record, or replayed the record. It
// also leaves a process running, which the request ends.
file_put_contents("$root/compile.sh", str_replace('MAGO', escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')), <<<'SH'
before=$(cksum < .sharp/inputs.json)
echo start >> runs.log
sleep 60 &
echo $! >> left.pids
MAGO --no-version-check compile
status=$?
if [ "$before" = "$(cksum < .sharp/inputs.json)" ]; then echo replayed >> runs.log; else echo compiled >> runs.log; fi
exit $status
SH));

function request(string $root, string $command): array
{
    $code = 'try { require ' . var_export("$root/Torn.sharp", true) . '; }'
        . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';
    $process = proc_open(
        [getenv('TEST_PHP_EXECUTABLE'), '-n', '-d', "sharp.compile_command=\"$command\"", '-r', $code],
        [1 => ['pipe', 'w']],
        $pipes,
        $root,
    );

    return [$process, $pipes[1]];
}

// Whether each process whose ID the file at path lists has ended, which a killed process does a moment later.
function ended(string $path): bool
{
    for ($tenth = 0; $tenth < 50; $tenth++) {
        $running = array_filter(file($path, FILE_IGNORE_NEW_LINES), fn (string $pid): bool => shell_exec("kill -0 $pid 2>/dev/null && echo running") !== null);
        if ($running === []) {
            return true;
        }
        usleep(100_000);
    }

    return false;
}

$requests = [];
for ($i = 0; $i < 10; $i++) {
    $requests[] = request($root, 'sh compile.sh');
}
$refusals = [];
foreach ($requests as [$process, $printed]) {
    $refusals[] = stream_get_contents($printed);
    proc_close($process);
}
echo implode('', array_unique($refusals));
echo implode(' ', file("$root/runs.log", FILE_IGNORE_NEW_LINES)), "\n";
var_dump(count(file("$root/left.pids")), ended("$root/left.pids"));

// A request killed while its compile runs leaves no process of it running.
[$process, $printed] = request($root, 'echo $$ > leader.pid; sleep 60 & echo $! >> left.pid; wait');
for ($tenth = 0; $tenth < 100 && !file_exists("$root/left.pid"); $tenth++) {
    usleep(100_000);
}
proc_terminate($process, 9);
proc_close($process);
var_dump(ended("$root/leader.pid"), ended("$root/left.pid"));
remove_project($root);
?>
--EXPECT--
CompileError: Torn.sharp has 1 error:
line 5: Unexpected `=>`
start compiled start replayed start replayed start replayed start replayed start replayed start replayed start replayed start replayed start replayed
int(10)
bool(true)
bool(true)
bool(true)
