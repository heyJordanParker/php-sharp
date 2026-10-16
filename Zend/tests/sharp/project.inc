<?php

// The standard library's module, each class it binds, and the classes and types their members take, return and call,
// by their paths under the library's Sharp folder. A project that holds the library's module holds these.
const LIBRARY_MODULE = [
    'Module', 'Dispatcher', 'ImmediateDispatcher', 'Environment', 'Clock', 'Random', 'Console', 'Process',
    'ProcessResult', 'Files', 'Http', 'Mail', 'Time/Duration', 'Net/HttpMethod', 'Net/HttpRequest', 'Net/HttpResponse',
    'Net/MailAddress', 'Net/MailMessage', 'Net/Url', 'Net/Email',
];

function compile_project(string $name, array $files): string
{
    // The process ID keeps two test runs on one machine out of each other's projects.
    $root = sys_get_temp_dir() . "/sharp-test-$name-" . getmypid();
    remove_project($root);
    mkdir($root);
    $root = realpath($root);
    file_put_contents("$root/mago.toml", "[source]\npaths = [\".\"]\nextensions = [\"sharp\"]\n");
    foreach ($files as $path => $contents) {
        is_dir(dirname("$root/$path")) || mkdir(dirname("$root/$path"), recursive: true);
        file_put_contents("$root/$path", $contents);
    }
    run_mago($root);

    return $root;
}

function run_mago(string $root): void
{
    exec(escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --workspace ' . escapeshellarg($root) . ' compile 2>&1', $output, $status);
    if ($status !== 0) {
        throw new RuntimeException(implode("\n", $output));
    }
}

function remove_project(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
}

function overwrite(string $file, int $offset, string $bytes): void
{
    file_put_contents($file, substr_replace(file_get_contents($file), $bytes, $offset, strlen($bytes)));
}

// The paths load in one process of their own, without opcache, so a class an earlier load declared or cached is not
// in the way.
function refusal(array|string $paths, array $settings = []): void
{
    $code = '';
    foreach ((array) $paths as $path) {
        $code .= 'try { require ' . var_export($path, true) . '; echo "ran\n"; }'
            . ' catch (CompileError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }';
    }
    $options = '';
    foreach ($settings as $name => $value) {
        $options .= ' -d ' . escapeshellarg("$name=\"$value\"");
    }
    echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . " -n$options -r " . escapeshellarg($code));
}

const SHOP = <<<'SHARP'
namespace Demo;

class Shop
{
    public static int run(int amount)
    {
        const counter = new Counter();
        return counter.add(amount);
    }
}

SHARP;

const COUNTER = <<<'SHARP'
namespace Demo;

class Counter
{
    private int count = 0;

    public int add(int amount)
    {
        this.count += amount;
        return this.count;
    }
}

SHARP;
