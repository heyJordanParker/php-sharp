--TEST--
Opcache keeps a .sharp file's op array while its compiled file stays current, new stamps and an input's body edit included, while the edited input itself is refused when it loads, and drops it when an input goes missing or the file's own source changes, making the compiled file out of date
--EXTENSIONS--
opcache
--CONFLICTS--
server
--FILE--
<?php

require __DIR__ . '/project.inc';
include __DIR__ . '/../../../sapi/cli/tests/php_cli_server.inc';

$root = compile_project('opcache-revision', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
$shop = var_export("$root/Shop.sharp", true);
$counter = var_export("$root/Counter.sharp", true);
php_cli_server_start(<<<PHP
    spl_autoload_register(function (string \$class): void {
        if (\$class === 'Demo\\\\Counter') {
            require $counter;
        }
    });
    try {
        require $shop;
        \$total = \Demo\Shop::run(1);
        echo "ran, Shop.run(1) gives \$total";
    } catch (CompileError \$e) {
        echo \$e->getMessage();
    }
    echo ', ', opcache_is_script_cached($shop)
        ? 'cached with ' . opcache_get_status(true)['scripts'][$shop]['hits'] . ' hits'
        : 'not cached', "\n";
    PHP, null, [
    '-d', 'opcache.enable=1',
    '-d', 'opcache.enable_cli=1',
    '-d', 'opcache.validate_timestamps=1',
    '-d', 'opcache.revalidate_freq=0',
    // The files are seconds old, and a compiled file's revision is no time, so the protection must not apply.
    '-d', 'opcache.file_update_protection=2',
]);

function request(string $step): void {
    echo "$step: ", file_get_contents('http://' . PHP_CLI_SERVER_ADDRESS . '/index.php');
}

request('first');
request('again');

touch("$root/Counter.sharp", time() + 100);
request('an input with a new stamp');

file_put_contents("$root/Counter.sharp", str_replace('class Counter', "// Counts.\nclass Counter", COUNTER));
run_mago($root);
request('an input with a new comment, compiled again');

file_put_contents("$root/Counter.sharp", str_replace('return this.count;', 'return this.count + 1;', COUNTER));
request('an input with a new body');

run_mago($root);
request('compiled again');

unlink("$root/Counter.sharp");
request('an input went missing');

file_put_contents("$root/Counter.sharp", COUNTER);
run_mago($root);
request('the input back, compiled again');

file_put_contents("$root/Shop.sharp", str_replace('return counter.add(amount);', 'return counter.add(amount + 1);', SHOP));
request('its own source changed');

run_mago($root);
request('compiled once more');
remove_project($root);
?>
--EXPECT--
first: ran, Shop.run(1) gives 1, cached with 0 hits
again: ran, Shop.run(1) gives 1, cached with 1 hits
an input with a new stamp: ran, Shop.run(1) gives 1, cached with 2 hits
an input with a new comment, compiled again: ran, Shop.run(1) gives 1, cached with 3 hits
an input with a new body: Counter.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile., cached with 4 hits
compiled again: ran, Shop.run(1) gives 2, cached with 5 hits
an input went missing: Shop.sharp is out of date (Counter.sharp changed). Run vendor/bin/mago compile., not cached
the input back, compiled again: ran, Shop.run(1) gives 1, cached with 0 hits
its own source changed: Shop.sharp is out of date (Shop.sharp changed). Run vendor/bin/mago compile., not cached
compiled once more: ran, Shop.run(1) gives 2, cached with 0 hits
