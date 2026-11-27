<?php

// The standard library's module, each effect it binds and the class it binds the effect to, the fake of each effect a
// test module binds, the classes and types their members take, return and call, the `extern` lines of the PHP built-ins
// they call, and the effects those lines name, by their paths under the library's Sharp folder. A project that holds
// the library's module holds these.
const LIBRARY_MODULE = [
    'Module', 'Dispatcher', 'ImmediateDispatcher', 'System/Environment', 'Time/Clock', 'Random', 'IO/Console',
    'System/SystemEnvironment', 'Time/SystemClock', 'SystemRandom', 'IO/SystemConsole', 'System/SystemProcess', 'IO/SystemFiles',
    'Net/SystemHttp', 'Net/SystemMail', 'Net/SystemDns', 'Interop/SystemPhpGlobals', 'Data/SystemDatabase', 'Data/SystemCache',
    'Testing/FakeEnvironment', 'Testing/FakeClock', 'Testing/FakeRandom', 'Testing/FakeConsole', 'Testing/FakeProcess',
    'Testing/FakeFiles', 'Testing/FakeHttp', 'Testing/FakeMail', 'Testing/FakeDns', 'Testing/FakePhpGlobals',
    'Testing/FakeDatabase', 'Testing/FakeCache',
    'System/Process', 'System/ExitCode', 'System/ProcessResult', 'System/ChildProcess', 'IO/Files', 'Net/Http', 'Net/Mail', 'Net/Dns', 'Net/IpAddress',
    'Net/IpFamily', 'Interop/PhpGlobals', 'Interop/Superglobal', 'Time/Duration', 'Time/Instant',
    'Time/DateTime', 'Time/Date', 'Time/TimeOfDay', 'Time/TimeZone', 'Interop/PhpDates', 'Time/Weekday', 'Time/Month', 'Int', 'ByteOrder', 'Bytes', 'Math', 'Net/HttpMethod', 'Net/HttpRequest',
    'Net/HttpResponse', 'Net/MailAddress', 'Net/MailMessage', 'Net/Url', 'Text/StringMembers', 'Text/NormalizationForm', 'ListMembers',
    'MapMembers', 'SetMembers', 'IterableMembers', 'Internal/Builtins', 'Data/Database', 'Data/Cache',
];

// A library unless $files holds a composer.json of its own, so spec section 5's strict rules, which an application's
// code meets, leave alone a test's classes, which only the test itself calls. Its `autoload-dev` folder is tests/, as a
// Composer project's is, so a test module there belongs to a test file.
function compile_project(string $name, array $files): string
{
    // The process ID keeps two test runs on one machine out of each other's projects.
    $root = sys_get_temp_dir() . "/sharp-test-$name-" . getmypid();
    remove_project($root);
    mkdir($root);
    $root = realpath($root);
    file_put_contents("$root/mago.toml", "[source]\npaths = [\".\"]\nextensions = [\"sharp\"]\n");
    file_put_contents("$root/composer.json", '{"type": "library", "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}' . "\n");
    foreach ($files as $path => $contents) {
        is_dir(dirname("$root/$path")) || mkdir(dirname("$root/$path"), recursive: true);
        file_put_contents("$root/$path", $contents);
    }
    run_mago($root);

    return $root;
}

// Every mago a test starts prints one issue to a line, through run_mago, an exec of its own, or the engine's
// sharp.compile_command, since mago prints GitHub's annotations instead under GitHub Actions.
putenv('MAGO_REPORTING_FORMAT=emacs');

function run_mago(string $root): void
{
    exec(escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --workspace ' . escapeshellarg($root) . ' compile 2>&1', $output, $status);
    if ($status !== 0) {
        throw new RuntimeException(implode("\n", $output));
    }
    // The compile moved .sharp/current to a new generation, which PHP's realpath cache would still resolve to the old one.
    clearstatcache(true);
}

function remove_project(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
}

function overwrite(string $file, int $offset, string $bytes): void
{
    file_put_contents($file, substr_replace(file_get_contents($file), $bytes, $offset, strlen($bytes)));
}

// Writes the hash of a compiled file's sections, every byte after its 160-byte header, into the header's
// sections_hash at 124, as mago writes it, so the engine reads a tree a test changed past that check.
function rehash_sections(string $file): void
{
    overwrite($file, 124, hash('xxh128', substr(file_get_contents($file), 160), true));
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

public class Shop
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
