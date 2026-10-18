--TEST--
A .sharp file opcache holds, in its file cache or in shared memory, runs while its compiled file names the engine's own native fingerprint, and is refused once a compile against a library built for other native bodies rewrites it: opcache's revalidation reads the compiled file's fingerprint
--EXTENSIONS--
opcache
--CONFLICTS--
server
--FILE--
<?php

require __DIR__ . '/project.inc';
include __DIR__ . '/../../../sapi/cli/tests/php_cli_server.inc';

$package = dirname(__DIR__, 3) . '/sharp/composer';
$files = [
    'mago.toml' => "[source]\npaths = [\"app\"]\nincludes = [\"vendor\"]\n",
    'vendor/heyjordanparker/php-sharp-composer/composer.json' => '{"name": "heyjordanparker/php-sharp-composer"}',
    'vendor/heyjordanparker/php-sharp-composer/native.php' => file_get_contents("$package/native.php"),
    'vendor/composer/installed.json' => '{"packages": [{"name": "heyjordanparker/php-sharp-composer", "version": "9.9.9"}]}',
    'app/Clock.sharp' => "namespace App;\n\npublic class Clock\n{\n    public string now() => \"noon\";\n}\n",
];
foreach (LIBRARY_MODULE as $library) {
    $files["vendor/heyjordanparker/php-sharp-composer/library/Sharp/$library.sharp"] = file_get_contents("$package/library/Sharp/$library.sharp");
}
$root = compile_project('native-fingerprint-opcache', $files);
$clock = var_export("$root/app/Clock.sharp", true);
$other = '<?php return ' . var_export(['fingerprint' => '0123456789abcdef0123456789abcdef', 'bodies' => []], true) . ';';
$run = <<<PHP
    try {
        require $clock;
        echo (new App\\Clock())->now();
    } catch (CompileError \$e) {
        echo str_replace(phpversion('sharp'), '<engine version>', \$e->getMessage());
    }

    PHP;

// The file cache: each process is new, and reads the op array another one stored.
$cache = "$root/opcache";
mkdir($cache);
file_put_contents("$root/file_cache.php", "<?php\n$run    echo ', ', opcache_is_script_cached_in_file_cache($clock) ? 'in the file cache' : 'not in the file cache', \"\\n\";\n");
$fileCache = static fn (string $step) => print("$step: " . shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
    . ' -n -d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d ' . escapeshellarg("opcache.file_cache=$cache")
    . ' -d opcache.validate_timestamps=1 -d opcache.file_update_protection=0 ' . escapeshellarg("$root/file_cache.php") . ' 2>&1'));
$fileCache('the file cache, first');
$fileCache('the file cache, again');

// Shared memory: one server keeps the op array across requests.
php_cli_server_start($run . "echo ', ', opcache_is_script_cached($clock) ? 'cached' : 'not cached', \"\\n\";", null, [
    '-d', 'opcache.enable=1',
    '-d', 'opcache.enable_cli=1',
    '-d', 'opcache.validate_timestamps=1',
    '-d', 'opcache.revalidate_freq=0',
    // The files are seconds old, and a compiled file's revision is no time, so the protection must not apply.
    '-d', 'opcache.file_update_protection=2',
]);
$sharedMemory = static fn (string $step) => print("$step: " . file_get_contents('http://' . PHP_CLI_SERVER_ADDRESS . '/index.php'));
$sharedMemory('shared memory, first');
$sharedMemory('shared memory, again');

file_put_contents("$root/vendor/heyjordanparker/php-sharp-composer/native.php", $other);
run_mago($root);
$fileCache('the file cache, compiled against other native bodies');
$sharedMemory('shared memory, compiled against other native bodies');

remove_project($root);
?>
--EXPECT--
the file cache, first: noon, in the file cache
the file cache, again: noon, in the file cache
shared memory, first: noon, cached
shared memory, again: noon, cached
the file cache, compiled against other native bodies: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>., not in the file cache
shared memory, compiled against other native bodies: This project's PHP# library is 9.9.9, and the PHP# engine is <engine version>. Install PHP# 9.9.9, or require heyjordanparker/php-sharp-composer <engine version>., not cached
