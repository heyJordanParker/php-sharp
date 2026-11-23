<?php

declare(strict_types=1);

/*
 * Measures what proving and compiling cost, in counts that don't depend on the machine's load, and compares them with
 * a baseline: each law's Lean heartbeats, which Lean counts the same on every run of the same input, and the
 * instructions mago's own process retires for a one-file edit, on one worker thread, so no scheduling changes its work.
 * valgrind's cachegrind counts them on Linux, whose hosted runners expose no hardware counter, and `/usr/bin/time -l`
 * on macOS. Lean's work, the cold compiles' proving, is what the heartbeats count.
 *
 * Usage: php benchmark/speed.php <mago> [--baseline <file>] [--write <file>]
 *
 * <mago> is the test-run build, which behaves as the release users run. It writes c2ks and the law corpus with
 * benchmark/c2k.php into a temporary folder and compiles each from cold, then edits app/M66/Score.sharp of c2ks in
 * place, which no law reaches, for a whole `mago analyze`. With --write, it writes what it measured and the commit it
 * measured as a baseline, which only the SPEED job writes, since instructions differ between machines. With
 * --baseline, it exits 1 when any law of the baseline counts more than 1.2 times its heartbeats or ends unproven where
 * the baseline proved it, when the corpus counts more than 1.05 times its heartbeats, or, when the baseline counted
 * its instructions on this platform, when the edit retires more than 1.05 times them, and names each.
 */

const LAW_GROWTH = 1.2;
const CORPUS_GROWTH = 1.05;
const INSTRUCTION_GROWTH = 1.05;

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
foreach (['c2ks', 'laws'] as $kind) {
    $started = microtime(true);
    $project = "{$root}/{$kind}";
    run([PHP_BINARY, __DIR__ . '/c2k.php', $kind, $project], getcwd());
    $log = run_mago($mago, ['compile'], $project);
    $laws = laws($log);
    if ($laws === []) {
        fwrite(STDERR, "The cold compile of {$kind} logged no law:\n{$log}\n");
        exit(1);
    }
    $measured['laws'] += $laws;
    if ($kind === 'laws') {
        $measured['corpus_heartbeats'] = array_sum(array_column($laws, 'heartbeats'));
    } else {
        run_mago($mago, ['analyze'], $project);
        $score = "{$project}/app/M66/Score.sharp";
        file_put_contents($score, str_replace('line.doubled() * tier.weight()', 'tier.weight() * line.doubled()', file_get_contents($score)));
        $measured['instructions'] = [
            'platform' => PHP_OS_FAMILY . '-' . php_uname('m'),
            'edit' => counted($mago, ['--threads', '1', 'analyze'], $project),
        ];
    }
    printf("%s: %d laws, %.1f s\n", $kind, count($laws), microtime(true) - $started);
}
printf("edit %s instructions, corpus %s thousand heartbeats\n", number_format($measured['instructions']['edit']),
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
} elseif ($now['edit'] > INSTRUCTION_GROWTH * $before['edit']) {
    $failures[] = sprintf("The edit retires %s instructions, %.3f times its %s.", number_format($now['edit']),
        $now['edit'] / $before['edit'], number_format($before['edit']));
}
foreach ($failures as $failure) {
    fwrite(STDERR, "{$failure}\n");
}
exit($failures === [] ? 0 : 1);

/** The commit of the tree this script measures, with `-dirty` when the tree holds changes no commit holds. */
function commit(): string
{
    [, $head] = output(['git', 'rev-parse', '--short=11', 'HEAD'], __DIR__, getenv());
    [, $changes] = output(['git', 'status', '--porcelain', '--untracked-files=no'], __DIR__, getenv());

    return trim($head) . ($changes === '' ? '' : '-dirty');
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

/** Runs `mago $arguments` in `$folder` at MAGO_LOG=debug, and gives its log. */
function run_mago(string $mago, array $arguments, string $folder): string
{
    return mago($mago, $arguments, $folder, [])[1];
}

/**
 * Runs `mago $arguments` in `$folder` under the platform's instruction counter, and gives the instructions mago's own
 * process retired: cachegrind's "I refs" on Linux, and `/usr/bin/time -l`'s "instructions retired" on macOS.
 */
function counted(string $mago, array $arguments, string $folder): int
{
    $counter = PHP_OS_FAMILY === 'Linux'
        ? ['valgrind', '--tool=cachegrind', '--cache-sim=no', '--cachegrind-out-file=/dev/null']
        : ['/usr/bin/time', '-l'];
    $log = mago($mago, $arguments, $folder, $counter)[1];
    $pattern = PHP_OS_FAMILY === 'Linux' ? '/I\s+refs:\s+([\d,]+)/' : '/^\s*(\d+)\s+instructions retired/m';
    if (preg_match($pattern, $log, $match) !== 1) {
        fwrite(STDERR, "No instruction count in:\n{$log}\n");
        exit(1);
    }

    return (int) str_replace(',', '', $match[1]);
}

/**
 * Runs `mago $arguments` in `$folder` at MAGO_LOG=debug, after `$counter`, and gives its standard output and its
 * standard error, which holds the log and the counter's report. mago exits 1 when it reports issues, as a refused law,
 * which the log's outcomes count, so only another status, a crash or a tool error, stops the script with both.
 *
 * @return array{string, string}
 */
function mago(string $mago, array $arguments, string $folder, array $counter): array
{
    $command = [...$counter, $mago, '--no-version-check', '--colors', 'never', ...$arguments];
    [$status, $output, $log] = output($command, $folder, ['MAGO_LOG' => 'debug'] + getenv());
    if ($status !== 0 && $status !== 1) {
        fwrite(STDERR, implode(' ', $arguments) . " in {$folder} exited {$status}:\n{$output}{$log}");
        exit(1);
    }

    return [$output, $log];
}

/**
 * Each law the compile's log judged, by name, with how its proof ended, whether that is proved, and the thousands of
 * heartbeats Lean counted for it.
 *
 * @return array<string, array{ended: string, proved: bool, heartbeats: int}>
 */
function laws(string $log): array
{
    preg_match_all('/The law (\S+) ended (.+?) after [\d.]+ s and (\d+) thousand heartbeats of the provers\' time\./', $log,
        $matches, PREG_SET_ORDER);
    $laws = [];
    foreach ($matches as [, $law, $ended, $heartbeats]) {
        $laws[$law] = ['ended' => $ended, 'proved' => $ended === 'proved', 'heartbeats' => (int) $heartbeats];
    }

    return $laws;
}
