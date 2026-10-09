--TEST--
Type texts from unserialize, and type arguments a new spells from this's, last one request, so a worker that reads or nests new ones in every request keeps its size
--SKIPIF--
<?php
if (getenv('SKIP_SLOW_TESTS')) die('skip slow test');
?>
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

if (($argv[1] ?? null) === 'unserialize') {
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

if (($argv[1] ?? null) === 'new') {
    // Each request starts from another type argument code spells, so it spells types no request before it did:
    // Node<List<int>>, Node<List<List<int>>> and on, then the same from string, float and bool.
    $counter = sys_get_temp_dir() . '/type_arguments_requests.' . getmypid();
    $request = (int) @file_get_contents($counter);
    file_put_contents($counter, $request + 1);
    $node = [App\Queue::intNode(...), App\Queue::stringNode(...), App\Queue::floatNode(...), App\Queue::boolNode(...)][$request]();
    for ($depth = 0; $depth < 400; $depth++) {
        $node = $node->deeper();
    }
    if ($request === 3) {
        unlink($counter);
    }
    echo 'peak ', getrusage()['ru_maxrss'] * (PHP_OS_FAMILY === 'Darwin' ? 1 : 1024), "\n";
    return;
}

foreach ([
    // Kept for the process, the texts would add some 10 MB to every request after the first.
    'unserialize' => 4 * 1024 * 1024,
    // Kept for the process, the types would add some 1 MB to every request after the first.
    'new' => 512 * 1024,
] as $reads => $limit) {
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -n --repeat 4 ' . escapeshellarg(__FILE__) . ' ' . $reads);
    preg_match_all('/^peak (\d+)$/m', $output, $peaks);
    if (count($peaks[1]) !== 4) {
        echo $output;
    }
    $growth = $peaks[1][3] - $peaks[1][1];
    echo $reads, ': ', $growth < $limit ? "the peak stays\n" : "the peak grew by $growth bytes\n";
}
?>
--EXPECT--
unserialize: the peak stays
new: the peak stays
