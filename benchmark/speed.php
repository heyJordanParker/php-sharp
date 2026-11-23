<?php

declare(strict_types=1);

/*
 * Measures what proving and compiling cost, in counts that don't depend on the machine's load, and compares them with
 * a baseline: each law's Lean heartbeats, which Lean counts the same on every run of the same input, and the
 * instructions mago's own process retires compiling every example of docs/spec.md and README.md once, as
 * sharp/bin/spec compiles them, on one worker thread, so no scheduling changes its work. valgrind's cachegrind counts
 * them on Linux, whose hosted runners expose no hardware counter, and `/usr/bin/time -l` on macOS. Lean's work, the
 * proving, is what the heartbeats count.
 *
 * Its inputs are the examples, which use every construct the language has, and the law corpus, each file of
 * benchmark/laws under app/: one file per law shape a project proved slowly. No input is generated, so a count moves
 * only when mago or an input does.
 *
 * Usage: php benchmark/speed.php <mago> [--baseline <file>] [--write <file>]
 *
 * <mago> is the test-run build, which behaves as the release users run. It writes the examples' projects with
 * `sharp/bin/spec --projects` and the corpus's project into a temporary folder, and compiles each once from cold. With
 * --write, it writes what it measured and the commit it measured as a baseline, which only the SPEED job writes, since
 * instructions differ between machines. With --baseline, it exits 1 when any law of the baseline counts more than 1.2
 * times its heartbeats or ends unproven where the baseline proved it, when the corpus counts more than 1.05 times its
 * heartbeats, or, when the baseline counted its instructions on this platform, when the examples' compiles retire more
 * than 1.05 times them, and names each.
 */

const LAW_GROWTH = 1.2;
const CORPUS_GROWTH = 1.05;
const INSTRUCTION_GROWTH = 1.05;
const CORPUS = 'benchmark/laws';

$arguments = array_slice($argv, 1);
$mago = array_shift($arguments);
$options = [];
while ($arguments !== []) {
    $option = array_shift($arguments);
    if (!in_array($option, ['--baseline', '--write'], true) || $arguments === []) {
        $mago = null;
        break;
    }
    $options[$option] = array_shift($arguments);
}
if ($mago === null || !is_executable($mago)) {
    fwrite(STDERR, "Usage: php {$argv[0]} <mago> [--baseline <file>] [--write <file>]\n");
    exit(2);
}
$mago = realpath($mago);
$repository = dirname(__DIR__);

$root = sys_get_temp_dir() . '/mago-speed-' . getmypid();
register_shutdown_function(static function () use ($root): void {
    if (!is_dir($root)) {
        return;
    }
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
});
$measured = ['commit' => commit(), 'laws' => [], 'corpus_heartbeats' => 0, 'instructions' => []];

$started = microtime(true);
$corpus = "{$root}/corpus";
write_corpus($repository, $corpus);
[$status, $output, $log] = output([$mago, '--no-version-check', '--colors', 'never', 'compile'], $corpus,
    ['MAGO_LOG' => 'debug'] + getenv());
checked($corpus, $status, $output, $log);
$laws = laws(CORPUS, $log);
if ($laws === []) {
    fwrite(STDERR, "The cold compile of the corpus logged no law:\n{$output}{$log}\n");
    exit(1);
}
$measured['laws'] += $laws;
$measured['corpus_heartbeats'] = array_sum(array_column($laws, 'heartbeats'));
printf("corpus: %d laws, %.1f s\n", count($laws), microtime(true) - $started);

$started = microtime(true);
$examples = "{$root}/examples";
run([PHP_BINARY, "{$repository}/sharp/bin/spec", '--projects', $examples], $repository);
$folders = array_map(static fn (string $id): string => "{$examples}/{$id}", array_diff(scandir($examples), ['.', '..']));
$instructions = 0;
foreach (run_magos($mago, $folders) as $folder => $log) {
    $measured['laws'] += laws(basename($folder), $log);
    $instructions += retired($log);
}
$measured['instructions'] = ['platform' => PHP_OS_FAMILY . '-' . php_uname('m'), 'examples' => $instructions];
printf("examples: %d projects, %d laws, %.1f s\n", count($folders), count($measured['laws']) - count($laws),
    microtime(true) - $started);
printf("examples %s instructions, corpus %s thousand heartbeats\n", number_format($instructions),
    number_format($measured['corpus_heartbeats']));

if (isset($options['--write'])) {
    ksort($measured['laws']);
    file_put_contents($options['--write'], json_encode($measured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
if (!isset($options['--baseline'])) {
    exit(0);
}

$baseline = json_decode(file_get_contents($options['--baseline']), true, flags: JSON_THROW_ON_ERROR);
$failures = [];
foreach ($baseline['laws'] as $law => $before) {
    $now = $measured['laws'][$law] ?? null;
    if ($before['proved'] && !($now['proved'] ?? false)) {
        $failures[] = $now === null
            ? sprintf("%s was proved with %s thousand heartbeats and is now unjudged.", $law, number_format($before['heartbeats']))
            : sprintf("%s was proved with %s thousand heartbeats and now ends %s after %s.", $law,
                number_format($before['heartbeats']), $now['ended'], number_format($now['heartbeats']));
    } elseif ($now !== null && $now['heartbeats'] > LAW_GROWTH * max($before['heartbeats'], 1)) {
        $failures[] = sprintf("%s counts %s thousand heartbeats, %.2f times its %s.", $law, number_format($now['heartbeats']),
            $now['heartbeats'] / max($before['heartbeats'], 1), number_format($before['heartbeats']));
    }
}
if ($measured['corpus_heartbeats'] > CORPUS_GROWTH * max($baseline['corpus_heartbeats'], 1)) {
    $failures[] = sprintf("The law corpus counts %s thousand heartbeats, %.3f times its %s.",
        number_format($measured['corpus_heartbeats']), $measured['corpus_heartbeats'] / max($baseline['corpus_heartbeats'], 1),
        number_format($baseline['corpus_heartbeats']));
}
$before = $baseline['instructions'];
$now = $measured['instructions'];
if ($before === []) {
    echo "The baseline holds no instruction count, so this run compares none.\n";
} elseif ($before['platform'] !== $now['platform']) {
    printf("The baseline's instructions were counted on %s, so this run on %s compares none.\n", $before['platform'],
        $now['platform']);
} elseif ($now['examples'] > INSTRUCTION_GROWTH * $before['examples']) {
    $failures[] = sprintf("The examples' compiles retire %s instructions, %.3f times their %s.", number_format($now['examples']),
        $now['examples'] / $before['examples'], number_format($before['examples']));
}
foreach ($failures as $failure) {
    fwrite(STDERR, "{$failure}\n");
}
exit($failures === [] ? 0 : 1);

/**
 * The commit this script measures: SPEED_COMMIT, which the SPEED job sets to its pull request's head, since a pull
 * request's run checks out GitHub's merge of it, which no branch holds, or else the checkout's own, with `-dirty` when
 * the tree holds changes no commit holds.
 */
function commit(): string
{
    $named = getenv('SPEED_COMMIT');
    if ($named !== false && $named !== '') {
        return substr($named, 0, 11);
    }
    [, $head] = output(['git', 'rev-parse', '--short=11', 'HEAD'], __DIR__, getenv());
    [, $changes] = output(['git', 'status', '--porcelain', '--untracked-files=no'], __DIR__, getenv());

    return trim($head) . ($changes === '' ? '' : '-dirty');
}

/**
 * Writes the corpus's project into `$folder`: each file of CORPUS under app/, with the Composer vendor folder of the
 * checkout's `sharp/composer` package, its two dependencies from Packagist at the versions composer.json pins.
 */
function write_corpus(string $repository, string $folder): void
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$repository}/" . CORPUS, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        put("{$folder}/app/" . substr($file->getPathname(), strlen("{$repository}/" . CORPUS) + 1), file_get_contents($file->getPathname()));
    }
    put("{$folder}/mago.toml", "php-version = \"8.4\"\n\n[source]\npaths = [\"app\"]\nincludes = [\"vendor\"]\n");
    put("{$folder}/composer.json", json_encode([
        'type' => 'library',
        'repositories' => [['type' => 'path', 'url' => "{$repository}/sharp/composer", 'options' => ['symlink' => false]]],
        'require' => [
            'heyjordanparker/php-sharp-composer' => '*@dev',
            'psr/container' => '2.0.2',
            'psr/event-dispatcher' => '1.0.0',
        ],
        'autoload' => ['psr-4' => ['App\\' => 'app/']],
        'config' => ['allow-plugins' => ['heyjordanparker/php-sharp-composer' => true]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    run(['composer', 'install', '--no-interaction', '--no-progress', '--ignore-platform-reqs'], $folder);
}

function put(string $path, string $contents): void
{
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0o777, true)) {
        throw new RuntimeException("Could not create the folder of {$path}.");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Could not write {$path}.");
    }
}

/** Runs `$command` in `$folder`, and stops the script with its output when it fails. */
function run(array $command, string $folder): void
{
    [$status, $output, $errors] = output($command, $folder, getenv());
    if ($status !== 0) {
        fwrite(STDERR, implode(' ', $command) . " failed:\n{$output}{$errors}");
        exit(1);
    }
}

/**
 * Runs `$command` in `$folder` with `$environment`, and gives its exit status, its standard output and its standard
 * error. Both go to files while it runs, so a process that writes much to one, as mago's debug log, never waits on a
 * pipe the script isn't reading yet.
 *
 * @return array{int, string, string}
 */
function output(array $command, string $folder, array $environment): array
{
    $files = [tempnam(sys_get_temp_dir(), 'mago-speed-out'), tempnam(sys_get_temp_dir(), 'mago-speed-err')];
    $process = proc_open($command, [1 => ['file', $files[0], 'w'], 2 => ['file', $files[1], 'w']], $pipes, $folder,
        $environment);
    $status = proc_close($process);
    $written = array_map(static fn (string $file): string => file_get_contents($file), $files);
    array_map(unlink(...), $files);

    return [$status, ...$written];
}

/**
 * Compiles each example's project of `$folders` once, as sharp/bin/spec compiles it, on one worker thread at
 * MAGO_LOG=debug, under the platform's instruction counter, cachegrind on Linux and `/usr/bin/time -l` on macOS, as
 * many at a time as sharp/bin/jobs prints, as sharp/bin/spec does. Gives each one's standard error, which holds its log
 * and its counter's report, by folder.
 *
 * @param list<string> $folders
 * @return array<string, string>
 */
function run_magos(string $mago, array $folders): array
{
    $counter = PHP_OS_FAMILY === 'Linux'
        ? ['valgrind', '--tool=cachegrind', '--cache-sim=no', '--cachegrind-out-file=/dev/null']
        : ['/usr/bin/time', '-l'];
    $parallel = (int) shell_exec(escapeshellarg(dirname(__DIR__) . '/sharp/bin/jobs'));
    $logs = [];
    $running = [];
    $pending = $folders;
    while ($pending !== [] || $running !== []) {
        while ($pending !== [] && count($running) < max($parallel, 1)) {
            $folder = array_shift($pending);
            $process = proc_open(
                [...$counter, $mago, '--no-version-check', '--colors', 'never', '--workspace', $folder, 'compile'],
                [1 => ['file', "{$folder}.out", 'w'], 2 => ['file', "{$folder}.err", 'w']],
                $pipes,
                $folder,
                ['MAGO_LOG' => 'debug', 'MAGO_THREADS' => '1'] + getenv(),
            );
            $running[$folder] = $process;
        }
        foreach ($running as $folder => $process) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                proc_close($process);
                checked($folder, $status['exitcode'], file_get_contents("{$folder}.out"), file_get_contents("{$folder}.err"));
                $logs[$folder] = file_get_contents("{$folder}.err");
                unset($running[$folder]);
            }
        }
        usleep(5000);
    }
    ksort($logs);

    return $logs;
}

/**
 * Stops the script with a compile's output when it exited with neither 0 nor 1. mago exits 1 when it reports issues,
 * as a refused law or an example's compile error, which the log's outcomes count, so only another status, a crash or a
 * tool error, stops it.
 */
function checked(string $folder, int $status, string $output, string $log): void
{
    if ($status !== 0 && $status !== 1) {
        fwrite(STDERR, "The compile of {$folder} exited {$status}:\n{$output}{$log}");
        exit(1);
    }
}

/** The instructions the counter's report in `$log` says mago's own process retired. */
function retired(string $log): int
{
    $pattern = PHP_OS_FAMILY === 'Linux' ? '/I\s+refs:\s+([\d,]+)/' : '/^\s*(\d+)\s+instructions retired/m';
    if (preg_match($pattern, $log, $match) !== 1) {
        fwrite(STDERR, "No instruction count in:\n{$log}\n");
        exit(1);
    }

    return (int) str_replace(',', '', $match[1]);
}

/**
 * Each law the compile's log judged, by `$project` and its name, as `benchmark/laws App.Plans.PlanState.aTrialPlanIsChargeable`,
 * since two examples can each state a law of one name, with how its proof ended, whether that is proved, and the
 * thousands of heartbeats Lean counted for it.
 *
 * @return array<string, array{ended: string, proved: bool, heartbeats: int}>
 */
function laws(string $project, string $log): array
{
    preg_match_all('/The law (\S+) ended (.+?) after [\d.]+ s and (\d+) thousand heartbeats of the provers\' time\./', $log,
        $matches, PREG_SET_ORDER);
    $laws = [];
    foreach ($matches as [, $law, $ended, $heartbeats]) {
        $laws["{$project} {$law}"] = ['ended' => $ended, 'proved' => $ended === 'proved', 'heartbeats' => (int) $heartbeats];
    }

    return $laws;
}
