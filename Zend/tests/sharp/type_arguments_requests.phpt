--TEST--
Type texts from unserialize last one request, so a worker that reads new ones in every request keeps its size
--SKIPIF--
<?php
if (getenv('SKIP_SLOW_TESTS')) die('skip slow test');
?>
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

if (($argv[1] ?? null) === 'request') {
    // Twenty thousand type texts no other request reads: Function types whose parameters spell a random number.
    $base = random_int(0, PHP_INT_MAX >> 32) << 32;
    $template = serialize(App\Queue::pair());
    for ($i = 0; $i < 20_000; $i++) {
        $parameters = [];
        for ($bit = 0, $number = $base | $i; $bit < 62; $bit++) {
            $parameters[] = ($number >> $bit) & 1 ? 'string' : 'int';
        }
        $text = 'Function<void(' . implode(', ', $parameters) . ')>, int';
        if (!unserialize(str_replace('s:14:"App.Order, int"', 's:' . strlen($text) . ':"' . $text . '"', $template))) {
            echo "unserialize failed\n";
        }
    }
    // macOS counts the peak in bytes, Linux in kilobytes.
    echo 'peak ', getrusage()['ru_maxrss'] * (PHP_OS_FAMILY === 'Darwin' ? 1 : 1024), "\n";
    return;
}

$output = shell_exec(escapeshellarg(PHP_BINARY) . ' -n --repeat 4 ' . escapeshellarg(__FILE__) . ' request');
preg_match_all('/^peak (\d+)$/m', $output, $peaks);
if (count($peaks[1]) !== 4) {
    echo $output;
}
// Kept for the process, the texts would add some 10 MB to every request after the first.
$growth = $peaks[1][3] - $peaks[1][1];
echo $growth < 4 * 1024 * 1024 ? "the peak stays\n" : "the peak grew by $growth bytes\n";
?>
--EXPECT--
the peak stays
