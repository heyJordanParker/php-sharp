--TEST--
Two processes that each read a stock count in a Database.transaction and write it back one lower both take effect: each reads before either writes, the database refuses the second writer, and the transaction runs it again on the count the first one committed
--EXTENSIONS--
pdo_sqlite
pcntl
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/vendor/autoload.php';

$path = sys_get_temp_dir() . '/sharp-transaction-concurrent-' . getmypid() . '.sqlite';
$setup = new Sharp\Data\SystemDatabase("sqlite:$path");
$setup->execute('CREATE TABLE stock (sku TEXT PRIMARY KEY, count INTEGER)');
$setup->execute("INSERT INTO stock (sku, count) VALUES ('tea', 10)");
unset($setup);

// On its first run each process tells the other it read the count, then reads until the other one has read it too,
// so both read 10 before either writes, as two requests that sell the last units at once do. A run after the
// database refused the first one reads until the process that committed on its first run says so, so it runs once
// more, whatever the timing.
function sell(string $path, $peer): int
{
    $database = new Sharp\Data\SystemDatabase("sqlite:$path");
    $runs = 0;
    $database->transaction(function () use ($database, $peer, &$runs): void {
        $runs++;
        if ($runs === 2) {
            fread($peer, 1);
        }
        $count = $database->scalar("SELECT count FROM stock WHERE sku = 'tea'");
        if ($runs === 1) {
            fwrite($peer, 'r');
            fread($peer, 1);
        }
        $database->execute("UPDATE stock SET count = ? WHERE sku = 'tea'", [$count - 1]);
    });
    if ($runs === 1) {
        fwrite($peer, 'c');
    }

    return $runs;
}

[$parentEnd, $childEnd] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
$child = pcntl_fork();
if ($child === 0) {
    fclose($parentEnd);
    exit(sell($path, $childEnd));
}
fclose($childEnd);
$runs = sell($path, $parentEnd);
pcntl_waitpid($child, $status);
$childRuns = pcntl_wexitstatus($status);

$database = new Sharp\Data\SystemDatabase("sqlite:$path");
echo 'count: ', $database->scalar("SELECT count FROM stock WHERE sku = 'tea'"), "\n";
echo 'runs: ', min($runs, $childRuns), ' and ', max($runs, $childRuns), "\n";
unset($database);
unlink($path);
?>
--EXPECT--
count: 8
runs: 1 and 2
