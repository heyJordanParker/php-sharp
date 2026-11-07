# PHP#

PHP# is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [docs/spec.md](../docs/spec.md), and the reason behind each language decision lives in [docs/decisions/](../docs/decisions/).

## Branches

- `master` is PHP#'s only line and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.12`, and all work lands on it directly.
- Upstream's other branches, such as `PHP-8.5`, are read-only mirrors. Upstream's own `master` is reached through the `upstream` remote.
- Upstream is merged into `master`, never rebased. Moving to a newer 8.5.x release means merging that release's tag.
- PHP# owns `sharp/`, `ext/sharp/`, `Zend/tests/sharp/`, `.github/workflows/sharp.yml`, `.github/workflows/completes.yml`, `.github/workflows/mago.yml`, `.github/workflows/mutants.yml`, `.github/workflows/release.yml`, `.github/actions/setup-lean/`, `.github/SECURITY.md`, `.github/ISSUE_TEMPLATE/`, `README.md`, `docs/spec.md`, `docs/decisions/` and `docs/php-src.md`. Everything else is an engine file.
- A merge from upstream meets conflicts only in engine files a feature had to change, and in the upstream files PHP# replaced: `README.md` and the forms in `.github/ISSUE_TEMPLATE/`. Each of those keeps PHP#'s side.

## Commands

Run every command from the repository root.

```sh
sharp/bin/build                 # incremental build of sharp/build/<os>-<arch>/sapi/cli/php and sapi/fpm/php-fpm
sharp/bin/build --clean         # rebuild from scratch
sharp/bin/build --zts           # the thread-safe build, in sharp/build/<os>-<arch>-zts, installed into its install/
sharp/bin/build --no-werror     # build past a compiler warning, with --disable-werror
sharp/bin/build-mago            # build sharp/mago/ as cargo test does, and sharp/build/<os>-<arch>/mago/debug/mago
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test --zts            # run it on the thread-safe build
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --opcache        # also run with the opcache file cache, priming it and then using it
sharp/bin/test --repeat         # also run each test twice in one process, the second time from opcache shared memory
sharp/bin/test --passes         # also run with each optimizer pass alone
sharp/bin/test --jit            # also run under the tracing and the function JIT
sharp/bin/test --census         # also check every write and comparison of a field that holds a PHP# mark
sharp/bin/test --spec           # also compile and run every code example in docs/spec.md and README.md
sharp/bin/test --differential   # also check that php -l compiles every Zend/tests/sharp/*.sharp file
sharp/bin/test --phpunit        # also run the PHP# test class in sharp/phpunit/ under PHPUnit
sharp/bin/test --upstream       # run Zend/tests, ext/reflection, ext/tokenizer and ext/opcache
sharp/bin/test --installed      # run it on the php on the PATH, with its php.ini and conf.d, as in a runtime image
sharp/bin/hosts --linux         # serve sharp/hosts/ from php-fpm and FrankenPHP, and compare with sharp/hosts/expected/
sharp/bin/php-extensions gd zip # check that php -m lists each PHP extension, and call into each one
```

`--zts` builds with `--enable-zts --enable-embed --disable-zend-signals`, as docker-library's `zts` images do, so FrankenPHP and other threaded hosts can link its `libphp`. Its tests use the `mago` in the other build's folder. Apple Silicon has no thread-safe JIT, so there the `*_jit_*` tests fail on it, and Linux runs them.

`sharp/bin/hosts` runs on Linux, so `--linux` runs it in the `sharp/docker/` image. It needs `sharp/bin/build` and `sharp/bin/build --zts` first, for `php-fpm` and the installed thread-safe engine, and builds `mago` as `sharp/bin/test` does. It installs the fixture into an empty `vendor/`, as `--phpunit` does. It builds FrankenPHP v1.13.5, the release the FrankenPHP image ships, with Go against that engine into `sharp/build/<os>-<arch>-zts/frankenphp/v1.13.5/`, then requests the fixture's paths from php-fpm, through `cgi-fcgi`, and from one FrankenPHP worker: the lifetimes twice, `/types` 1,000 times, `/flood` past the type limit, `/words`, a type the program makes that no request made before, then `/types` and `/` again. Each host's output must equal `sharp/hosts/expected/<host>.txt`, where only the static's count differs. Four more workers then serve 400 requests, 32 at once, in one process.

`--installed` runs the suite on the engine a runtime image installs, the `php` on the `PATH`, inside that image. Every other mode runs the build's engine with no `php.ini`, so only `--installed` loads the PHP extensions the image's `conf.d` enables.

`sharp/bin/test` first compares the version the built engine prints in `php --ri sharp` with `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`, and refuses to run until `sharp/bin/build` rebuilds an engine that differs.

The engine runs only `.sharpc` files that `mago compile` wrote. So before any test runs, `sharp/bin/test` builds `mago` from `sharp/mago/` with `sharp/bin/build-mago`, into `sharp/build/<os>-<arch>/mago/debug/`, as `sharp/bin/hosts`, `sharp/bin/bench` and CI do, and runs `mago compile` from the repository root into its `.sharp/` folder. It first fails when a test in `Zend/tests/sharp/` shares the name of a `.sharp` file beside it in another case, as `signatures.phpt` and `Signatures.sharp` did: `run-tests.php` writes the test's script, `signatures.php`, where `mago compile` writes the class's companion, `Signatures.php`, and a disk that ignores case keeps one file for both. cargo rebuilds only what changed, so a run after a checker change tests that change. It builds what `cargo test --workspace` builds, in the dev profile `sharp/mago/Cargo.toml` explains, so the tests and `sharp/bin/test` share one build. `Zend/tests/sharp/mago.toml` makes the `.sharp` fixtures, the plain PHP classes and functions they call, in `Zend/tests/sharp/harness/`, and the standard library in `sharp/composer/library/` one checker project. A fixture the checker refuses fails the run, because a refusal is Mago's to test. Tests that build their own project, such as the refusal tests, find `mago` in `TEST_MAGO_EXECUTABLE`.

Each run points `TMPDIR` at a folder of its own and removes it when the run ends, after it stops `run-tests.php`, its workers and their tests when the run ends early, as on SIGTERM, so the opcache file cache that `--opcache` primes, and every file a test writes in the temporary folder, never reaches another run. `run-tests.php` keeps the file cache under `sys_get_temp_dir()`, as upstream's master does, where PHP 8.5's keeps it in `/tmp`.

`--differential` checks that every `.sharp` fixture `mago compile` accepted makes `php -l` print nothing but "No syntax errors detected", so a compile warning or deprecation also fails the run.

`--phpunit` installs `sharp/phpunit/`, a Composer project that requires `sharp/composer/` and the PHPUnit version its `composer.lock` pins, into an empty `vendor/`, because Composer keeps an earlier run's copy of `sharp/composer/` while the lock is unchanged. It compiles it with the `mago` it built, and runs its tests with the engine under PHPUnit twice: in one process, and with `--process-isolation`. Its PHP# test class extends PHPUnit's `TestCase` and declares a test module, so the run fails when the test module does not apply in PHPUnit's process, in the child process of `#[RunInSeparateProcess]`, or before the test's own `Before` method. It needs `composer` on the path, which the `sharp/docker/` image installs.

`--passes` runs the suite once per bit of `opcache.optimization_level` from pass 1 to pass 16, each pass alone. `--jit` runs it under `opcache.jit=tracing` and under `opcache.jit=function`, with every hot counter at 1. Every run expects the output of the run without opcache, so a pass that drops a PHP# mark fails on its own run instead of hiding behind the other passes. A test whose output depends on the whole optimizer, such as a count of oplines the JIT left to the VM, sets `opcache.optimization_level=0x7FFEBFFF` in its `--INI--` section.

`--census` runs `sharp/bin/census`. It reads the register of PHP# marks in `Zend/zend_compile.h` and fails on each line in `Zend/` and `ext/opcache/` that writes or compares a marked field of a marked opcode or AST kind without naming the mark, as the header of `sharp/bin/census` describes. A new mark goes in the register, beside the upstream flags of the same field and kind, with a `ZEND_STATIC_ASSERT` that fails the build when they overlap.

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux --opcache`. In a git worktree, `sharp/docker/run` also mounts the main checkout's `.git` folder at its own path, because the worktree's `.git` file names a folder inside it, so git, `sharp/bin/spec` and `mago compile` find the repository in the container as they do in the main checkout.

Every PHP# feature must pass `sharp/bin/test --opcache --repeat --passes --jit --census --spec --differential --phpunit`. `sharp/bin/test` disables the JIT outside `--jit`.

`sharp/bin/build` configures with `--enable-werror`, as php-src's own CI does, and so do the runtime images in `sharp/docker/runtime/`, which `PHP_EXTENSIONS` builds at `-O2` on amd64 and arm64 for every pull request, so a compiler warning fails the build that brings it in. Those builds use Debian trixie's gcc 14, so after the merge `UBUNTU_WERROR` runs `sharp/bin/build --release` with Ubuntu 24.04's gcc 13 too, which warned where gcc 14 didn't in #255. A compiler newer than CI's can warn in upstream code: `--no-werror` configures with `--disable-werror` to build past it, and a later run without it configures with `--enable-werror` again. CI never passes it.

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global`, `sharp/docker/Dockerfile`, `sharp/bin/build` or `Zend/zend_modules.h` changes. `configure` reads `ZEND_MODULE_API_NO` from `Zend/zend_modules.h` into the extension directory.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --upstream` compares the run without opcache with `sharp/baseline/darwin` or `sharp/baseline/linux`, and fails on any difference. Each file lists every test that did not pass on the untouched `php-8.5.12` build, as `STATUS<TAB>path`. An upstream FAIL, BORK or LEAK fails the run before any comparison, so the baseline only ever holds skips and expected failures. A changed result is a regression until the Architect accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## Spec examples

`--spec` runs `sharp/bin/spec --self-test`, which checks one example for each of the checker's own rules in `sharp/spec-self-test.md`, then `sharp/bin/spec`. It compiles every checked code block in `docs/spec.md` and `README.md` with the `mago` built from `sharp/mago/`, runs the lines that claim a value or a throw on the engine, and compares each annotated line with its annotation. An HTML comment above a block, such as `<!-- example map-reads: statements alone -->`, names the example and says how to build it, as [docs/architecture/spec-examples.md](../docs/architecture/spec-examples.md) describes. Every example compiles against the classes and plain PHP stubs in `sharp/harness/`. A law example (section 28) needs Lean, so the `sharp/docker/` image installs Debian's `elan`, which downloads the Lean version mago pins into `sharp/build/elan` the first time a law compiles. mago's own cache folder sits in `sharp/build/cache` there.

A `compile error` line needs the error its rule gives: a "not supported yet" refusal, a missing class the line doesn't name itself, or an unreadable line never satisfies it, and an error moves to it from another line only when it is one of the whole-construct errors in `MOVES` and its line's brackets enclose it. A value or `throws` annotation that no run reaches fails the check, unless a hidden `<!-- run <id> -->` comment after the example's last block holds a run line that makes the same claim.

`sharp/baseline/spec/` lists every example that fails today, one empty file `<section>/<id>` each, so pull requests that fix different examples never conflict. Its count is how far PHP# is from v1. Git tracks files, not folders, so once no example fails, a checkout has no `sharp/baseline/spec/` at all. A missing folder means no example fails: `sharp/bin/spec` reads it as an empty list, in the working tree and, with `--base`, at the base commit. The run fails when an unlisted example fails or a listed one passes, and prints the `git rm`, `mkdir -p` and `touch` commands that make the folder match. The list records which examples fail, not how, so a listed example may change its errors.

On a pull request, the `SPEC_GUARD` job runs the base commit's `sharp/bin/spec --base <commit>`, from the base branch's `.github/workflows/completes.yml` on `pull_request_target`, so a pull request never runs or changes its own comparison. A feature pull request changes the spec, its examples and `sharp/baseline/spec` together with its code. `GUARDED` in `sharp/bin/spec` names what measures the examples, `sharp/bin/spec` itself, and a pull request that changes any other file leaves it exactly as the base has it. Markdown never runs, so a Markdown file may change beside it. A pull request that adds or changes a file in `sharp/harness/` lists it in its body, and its review checks that no stub makes an example pass falsely. The guard lists changed files with `--no-renames`, so a renamed guarded file shows its old path, and it fails on a symbolic link at a file it reads or guards, naming the path only. The fingerprint covers the whole example but its id: its section, and every block's marker line, setup, fence language, code and run comment. An example the base has with the same fingerprint, under its id or another, never joins the list, except that a renamed example takes over its old id's entry. An `ignore` on code the base checked counts as an entry.

## Runtime images

PHP# ships its engine only as two runtime images: php-fpm's `ghcr.io/heyjordanparker/php-sharp:<version>` and FrankenPHP's `ghcr.io/heyjordanparker/php-sharp:<version>-frankenphp`. A release's other assets are `mago` archives, Mago's source and their SHA-256s, with no `php` or `php-fpm` build. A server that can't run the images builds the engine from source, with the [Linux dependencies](#linux-dependencies).

`php --ri sharp` shows that a `php` is the PHP# engine. It prints the engine's version, and plain PHP prints `Extension 'sharp' not present.` and exits with status 1:

```text
$ docker run --rm ghcr.io/heyjordanparker/php-sharp:0.6.3 php --ri sharp
sharp

Version => 0.6.3

Directive => Local Value => Master Value
sharp.compile_command => no value => no value
```

`sharp/docker/runtime/Dockerfile` holds docker-library's `php:8.5-fpm-trixie` recipe, built from this repository's source instead of the php.net tarball, with `ext/sharp` built in. It keeps docker-library's layout, so `docker-php-ext-*` and `install-php-extensions` work unchanged and an app switches to PHP# by changing only its `FROM` line. The `docker-php-*` scripts are verbatim copies. `docker-php-entrypoint` is the one script that differs between docker-library's variants, so each image copies its own from `fpm/` or `zts/`, and the shared ones sit beside the Dockerfiles. Compare the Dockerfile, the shared scripts and `fpm/docker-php-entrypoint` with docker-library's `8.5/trixie/fpm` on every merge from upstream.

`sharp/docker/runtime/frankenphp.Dockerfile` builds the engine with docker-library's `8.5/trixie/zts` recipe, then runs FrankenPHP v1.13.5's own Dockerfile stages on it, as FrankenPHP's images build on `php:8.5-zts-trixie`. `sharp/bin/hosts` builds the same release for `HOSTS`. Go 1.27, `e-dant/watcher` 0.14.5 and `install-php-extensions` 2.12.0 are pinned where FrankenPHP takes the latest. FrankenPHP's source is fetched by its release's commit, and the `e-dant/watcher` archive and the `install-php-extensions` script are checked against their SHA-256s. Compare it and `zts/docker-php-entrypoint` with docker-library's `8.5/trixie/zts`, and it with FrankenPHP's `Dockerfile`, on every merge from either. FrankenPHP's stage rewrites `php` in the `zts` entrypoint to `frankenphp run`, so the image starts FrankenPHP on its default command.

The build context is the git tree, the same one CI builds from:

```sh
git archive HEAD | docker build -f sharp/docker/runtime/Dockerfile -t php-sharp -
git archive HEAD | docker build -f sharp/docker/runtime/frankenphp.Dockerfile -t php-sharp:frankenphp -
```

A server whose PHP comes from its distribution, such as Ubuntu's packages, can't load PHP#'s extensions there: PHP# reports module API `20261009`, and every distribution extension is built for PHP's own. There nginx stays on the host, and PHP runs in the php-fpm image. General php-fpm and Docker practice is docker-library's, and [its documentation](https://github.com/docker-library/docs/blob/master/php/README.md) applies to this image unchanged. What follows is what a production app on PHP# gets wrong without being told.

### Extensions

The image ships the extensions its release's `php -m` lists. For 0.6.3:

```text
$ docker run --rm ghcr.io/heyjordanparker/php-sharp:0.6.3 php -m
[PHP Modules]
Core
ctype
curl
date
dom
fileinfo
filter
hash
iconv
json
lexbor
libxml
mbstring
mysqlnd
openssl
pcre
PDO
pdo_sqlite
Phar
posix
random
readline
Reflection
session
sharp
SimpleXML
sodium
SPL
sqlite3
standard
tokenizer
uri
xml
xmlreader
xmlwriter
Zend OPcache
zlib

[Zend Modules]
Zend OPcache
```

The list has no `pcntl`, which Laravel's queue worker needs to stop between jobs when `docker restart` signals it, and no driver for a database but SQLite. The app's image builds every other extension it needs with [`install-php-extensions`](https://github.com/mlocati/docker-php-extension-installer), as `pgsql`, `redis`, `imagick`, `intl`, `gd`, `zip`, `bcmath`, `exif` and `pcntl` build on 0.6.3.

### The app's image

The app builds its own image on the release, with its extensions, the production `php.ini`, its own ini settings and its pool file:

```dockerfile
FROM ghcr.io/heyjordanparker/php-sharp:0.6.3
COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pcntl redis
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY app.ini "$PHP_INI_DIR/conf.d/"
COPY app.conf /usr/local/etc/php-fpm.d/zz-app.conf
```

The image loads no `php.ini`, so until the app copies `php.ini-production`, php-fpm runs with `display_errors` on. `php.ini-production` still leaves `expose_php` on and `memory_limit` at `128M`, so `app.ini` sets them, with the opcache setting the deploy below depends on:

```ini
expose_php = Off
memory_limit = 256M
opcache.validate_timestamps = 0
```

php-fpm reads `/usr/local/etc/php-fpm.d/*.conf` in name order. The image's `docker.conf` gives the default `[www]` pool `listen = 9000`, on every address, and `www.conf` after it sets `pm.max_children = 5`. So the app's `[www]` settings go in a file whose name sorts after `www.conf`, as `zz-app.conf` does. A file named `app.conf` sorts first, and its `pm.max_children` loses to `www.conf`'s. The pool listens on the host's loopback address, because php-fpm runs on the host's network:

```ini
[www]
listen = 127.0.0.1:9000
pm.max_children = 20
```

### php-fpm, the workers and the scheduler

A Capistrano-style deploy root, such as `/srv/www/app`, holds `releases/<timestamp>`, `shared/` and the `current` link. The `current` link and each release's links into `shared/` name absolute paths, so the container mounts the whole deploy root at its own path, where every link resolves. nginx serves `current/public` and passes `$realpath_root`, so `SCRIPT_FILENAME` names the release itself, such as `/srv/www/app/releases/<timestamp>/public/index.php`:

```nginx
root /srv/www/app/current/public;

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    fastcgi_pass 127.0.0.1:9000;
}
```

php-fpm, the queue worker and the scheduler each run as their own container of the app's image, here `app-php`:

```sh
docker run -d --name app --restart always --user "$(id -u web):$(id -g web)" --network host \
  -v /srv/www/app:/srv/www/app app-php
docker run -d --name worker --restart always --user "$(id -u web):$(id -g web)" --network host --memory 512m \
  -v /srv/www/app:/srv/www/app -w /srv/www/app/current app-php php artisan queue:work
docker run -d --name scheduler --restart always --user "$(id -u web):$(id -g web)" --network host --memory 256m \
  -v /srv/www/app:/srv/www/app -w /srv/www/app/current app-php php artisan schedule:work
```

The image runs the pool as `www-data`, uid 33. `--user` runs php-fpm, its pool and the workers as the deploy user that owns the releases, here `web`, so the app writes `shared/storage` as that user. php-fpm then logs that it ignores the pool's `user` and `group`, as [docker-library describes](https://github.com/docker-library/docs/blob/master/php/README.md#running-as-an-arbitrary-user). `--network host` gives each container the host's `127.0.0.1`, where nginx reaches php-fpm and the app reaches a Redis or a database that listens only there.

### Deploying a release

The engine runs only the `.sharp/…c` units that `mago compile` writes, never a `.sharp` source. So each release compiles in its own folder before `current` moves to it, and then php-fpm reloads and the workers restart:

```sh
cd /srv/www/app/releases/<timestamp>
composer install --no-dev --optimize-autoloader
docker run --rm --user "$(id -u web):$(id -g web)" -v /srv/www/app:/srv/www/app -w "$PWD" app-php vendor/bin/mago compile
ln -sfn "$PWD" /srv/www/app/current.next && mv -T /srv/www/app/current.next /srv/www/app/current
docker kill --signal USR2 app
docker restart worker scheduler
```

`composer install --no-dev` installs no `require-dev` package, so `heyjordanparker/mago-sharp` goes in the app's `require`, or the release has no `vendor/bin/mago`.

With `opcache.validate_timestamps=0`, opcache never checks a file it cached, and the engine checks a unit against its source only when opcache compiles the file. A `.sharp` file that php-fpm ran before its source changed, or before `mago compile` rewrote its unit at the same path, keeps running its cached bytecode with no refusal until php-fpm reloads. After the reload, the first request compiles it again, and the engine refuses a unit that is missing or out of date, as in [Running tests](#running-tests), or runs the new one. With the default `opcache.validate_timestamps=1`, opcache asks the engine for the unit's revision every `opcache.revalidate_freq` seconds, 2 by default, so php-fpm refuses an uncompiled edit and runs a compiled one without a reload.

`$realpath_root` gives every release its own paths, so a request after the switch reaches the new release even without a reload. A path through `current` does not: with nginx's `$document_root`, which passes `current/public/index.php`, php-fpm keeps running the old release's bytecode. Every earlier release's bytecode also stays in opcache's shared memory. `docker kill --signal USR2 app` reloads php-fpm gracefully: it starts again on the same socket, with an empty opcache.

A worker or scheduler container resolves `-w /srv/www/app/current` once, when it starts, so it keeps running the release it started in. `docker restart` starts each in the new release, and Laravel's worker with `pcntl` stops between jobs on its signal.

## Composer

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. Composer's autoloader then tries `.sharp` after `.php` by the PSR-4 rules, the way it tries `.hh` on HHVM. Composer's class map scanner reads only `<?php` files, so on `composer dump-autoload --optimize` the plugin writes every `.sharp` file under a PSR-4 folder to `vendor/composer/autoload_sharp.php`, and the autoloader reads it. The package's autoloader includes every `.sharp` class itself, never Composer's, so on plain PHP the first one stops with "This project needs the PHP# engine" before its source can reach the output. A test file a runner includes itself stops at the plugin's test bootstrap, `require-engine.php`, as [Running tests](#running-tests) describes. The engine comes from the [runtime images](#runtime-images). `mago compile` also writes a `.php` file beside each of a project's `.sharp` files that declares a type, outside `autoload-dev` folders and `vendor/`, which loads the `.sharp` file the same way, so PHP code that lists `*.php` files finds PHP# classes (spec section 27, decision 81).

`sharp/composer/library/Sharp/Internal/Builtins.sharp` gives each PHP built-in function its effect, as `extern global.fopen uses Files;`. `sharp/bin/builtins` writes it from PHPStan's php-8-stubs, at the version and SHA-256 it pins, for every PHP 8.5 function in PHP's root namespace that Mago's prelude in `sharp/mago/crates/prelude/` declares. `RULES` in the script maps each function to its effects, or to none, by its extension and name, and the run fails on a function no rule covers. The file also declares pure each PHP 8.5 exception class the prelude declares, as `extern ValueError;`: `Exception`, `Error` and every class whose parents reach one of them. `sharp/bin/builtins --self-test` checks that each rule gives the effect of its row in `ROWS`, and that removing the rule changes that row. The Linux job runs both and fails when the file differs from the committed one.

`sharp/composer/` is the plugin's source of truth. Composer installs it from `heyJordanParker/php-sharp-composer`, a read-only split that CI rewrites from this folder's history, the way Symfony splits its components. Change the plugin here, never in the split.

## Running tests

An app's tests run on the PHP# engine under PHPUnit or Pest, through the same loading as the app:

```sh
vendor/bin/mago compile         # first, after every change to a .sharp file
vendor/bin/phpunit
vendor/bin/pest --parallel      # or vendor/bin/pest
```

A test run loads a PHP# class the way the app does. A class in an `autoload` folder loads through the `<Name>.php` file `mago compile` writes beside its `.sharp` file, which Composer's own loader finds first. A class in an `autoload-dev` folder, such as a test class, has no such file: the test runner includes a test file it lists itself, and the plugin's loader includes any other `.sharp` file by the PSR-4 rules. Either way, the engine runs the compiled unit, `.sharp/<path of the source>c` under the project root, and never the source. So `mago compile` runs before the tests. A test that loads a class whose unit is missing or out of date fails with the refusal, such as `app/Mail/SmtpMailer.sharp is out of date (app/Mail/SmtpMailer.sharp changed). Run vendor/bin/mago compile.`

A test runner includes each `.sharp` test file it lists itself, past the plugin's loader, and plain PHP prints a `.sharp` file's source as output: Pest then reports "No tests found" and exits 0. So `phpunit.xml` names the plugin's bootstrap, `<phpunit bootstrap="vendor/heyjordanparker/php-sharp-composer/require-engine.php">`, in place of `vendor/autoload.php`. PHPUnit, Pest and ParaTest load it before any test file, in each child process too. It loads `vendor/autoload.php` when the runner hasn't, and on plain PHP it stops the run with "This project needs the PHP# engine" before any test file is read.

**PHPUnit** finds a PHP# test class when `phpunit.xml` lists its files, as `<directory suffix="Test.sharp">tests</directory>` beside `<directory suffix="Test.php">tests</directory>`. A test file's test module applies while each of its tests runs (spec section 32), in PHPUnit's process, in the child process of `#[RunInSeparateProcess]` and under `--process-isolation`. `sharp/phpunit/` is such a project, and `sharp/bin/test --phpunit` runs it.

**Pest** runs on PHPUnit, with the same `phpunit.xml`. A Pest test file is plain PHP, so its `test()` and `it()` calls use PHP# classes as any PHP code does, and it declares no test module. Pest also runs a PHP# test class that `phpunit.xml` lists, and that class's test module applies.

**Parallel Pest** runs each worker as its own PHP process, started with the `php` that runs Pest, so start Pest with the engine. Every worker reads the same `.sharp/` folder, which the one `mago compile` before the run wrote. In the CLI, opcache's shared memory belongs to one process, so the workers share bytecode only through `opcache.file_cache`, when `php.ini` sets it with `opcache.enable_cli=1`. The engine caches a `.sharp` file under the revision of its compiled unit, not under the source's modification time. With `opcache.validate_timestamps=1`, the default, workers that share a file cache need nothing beyond the one compile before the run:

- after a source edit with no compile, every worker refuses the out-of-date unit, as without opcache
- after the compile, each worker reads the new unit, and the file cache replaces the entry of each file whose unit changed
- a worker writes an entry only when no other worker has written it, under an exclusive lock, and reads one under a shared lock, as upstream opcache does

The file cache outlives each run, and `opcache.validate_timestamps=0` skips the check. Then every worker runs the bytecode an earlier run cached, of `.sharp` and `.php` files alike, and a suite can pass against code that no longer exists. So keep `opcache.validate_timestamps=1` wherever tests run with `opcache.file_cache`, or empty the file cache folder after each `mago compile`.

## macOS dependencies

```sh
brew install autoconf bison curl re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## Linux dependencies

A source build on Debian or Ubuntu needs the packages `sharp/docker/runtime/Dockerfile` installs to build the php-fpm image's engine, without `xz-utils` and `curl`, which only its source archive and the image's users need:

```sh
apt-get install --no-install-recommends autoconf bison ca-certificates dpkg-dev file g++ gcc git libargon2-dev libc-dev \
  libcurl4-openssl-dev libonig-dev libreadline-dev libsodium-dev libsqlite3-dev libssl-dev libxml2-dev make pkg-config \
  re2c rustup zlib1g-dev
```

On a fresh Debian trixie or Ubuntu 24.04 with only these, `./buildconf --force`, then that Dockerfile's `./configure` line and `make`, build `sapi/cli/php` and `sapi/fpm/php-fpm`, and `php --ri sharp` prints the tree's version. `rustup` downloads the Rust toolchain `ext/sharp/rust-toolchain.toml` names at the first build. `sharp/bin/build` also configures `--enable-intl`, `--with-zip` and `--with-pgsql`, so it needs `libicu-dev`, `libzip-dev` and `libpq-dev` as well, which `sharp/docker/Dockerfile` installs.

## CI

Every macOS job in `.github/workflows/sharp.yml`, `.github/workflows/mago.yml` and `.github/workflows/release.yml` runs on the three self-hosted runners labelled `macos-arm64-local`, on one M2 Max, and `sharp.yml`'s `MACOS` only on `jordan-m2max-1`, the one also labelled `macos-arm64-suite`, so its suites run one at a time. A pull request runs none of them but a release's bump pull request, whose `sharp.yml` and `mago.yml` macOS jobs run on `macos-latest` when it comes from a fork, because the repository is public, and the push to `master` runs them after the merge, as [docs/how-we-work.md](../docs/how-we-work.md) says. A job on them installs nothing outside its workspace and the runner's own `RUSTUP_HOME`, `CARGO_HOME` and `HOME`, so it uses Homebrew's PHP, Composer and build formulae as installed and builds without ccache. The jobs of every workflow share one work folder per runner, so each checks out with `clean: false`, then runs `git clean -ffd` and `git clean -ffdx --exclude=/sharp/build/ -- sharp`. That keeps `sharp/build` and the parsers, scanners and `configure` that php-src's build writes into the source tree, which the kept build's dependency files name, so the engine and `mago` rebuild only what changed since that runner's last job.

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on macOS, for every pull request to `master` and every push run on `master` that changes more than documentation. `master` runs one push at a time, and a newer push replaces the one still waiting, so a push run checks every change since `master`'s last push run that wasn't cancelled. A pull request runs each mode once, on the build it is about, and the full run runs every mode on every build: nightly on `master`'s tip, on a dispatch, and on a release's bump pull request. A pull request's run skips `MACOS`, `LINUX_PASSES` and `UBUNTU_WERROR` unless it is the full run, and the push to `master` runs them. `ASAN` runs in the full run alone. A failed nightly run or push to `master` opens an issue, or comments on the one still open. A tree runs the suites once: a push to `master` whose tree is the head of the pull request it merged skips them when that pull request's run passed them all, which only the bump's full run does, and links that run, the nightly run skips a tree a full run already passed, the bump's included, and a tag publishes only a commit on `master` whose tree a full run passed. `release.yml` gates Mago CI the same way. `LINUX` runs `--opcache --repeat --jit --census --spec --differential --phpunit`, so a PHP# test class runs under real PHPUnit on every change, and the upstream tests without opcache. `LINUX_PASSES` runs `--passes` on the same build for every change except one only to `sharp/mago/`, `sharp/composer/`, `sharp/hosts/`, `sharp/phpunit/`, `sharp/bench/`, tests or documentation, so an engine input no one listed runs it. `UBUNTU_WERROR` runs `sharp/bin/build --release` for the same changes, on the runner itself, so the engine builds at `-O2` with `--enable-werror` under Ubuntu 24.04's gcc 13 as well as the image's gcc 14. `ASAN` builds the engine on the runner with AddressSanitizer, through the `CFLAGS` and `LDFLAGS` php-src's own CI sets, and runs `sharp/bin/test --opcache --repeat --jit` with the environment `run-tests.php --asan` sets, so a memory error of the engine fails a test. `LINUX_ZTS` builds with `--zts` and runs `--repeat --jit`, the opcache and the JIT that FrankenPHP and Octane on Swoole run. `HOSTS` builds `php-fpm` and the thread-safe engine itself and runs `sharp/bin/hosts`, beside the other jobs. `MACOS` runs what only macOS can fail: the build, `mago compile` and the run without opcache on a disk that ignores case, `--census` and `--differential`, and `--jit`, the one arm64 JIT run outside the full run. `PHP_EXTENSIONS` builds each runtime image, on an amd64 runner and on an arm64 one, and starts it on its own default command, as an app's container does, with a page where the app's code goes: php-fpm must answer it to `cgi-fcgi` on port 9000, and FrankenPHP to `curl` on its default Caddyfile's `https://localhost`. It then installs the extensions a typical Laravel app installs on top with `install-php-extensions`, as such an app's Dockerfile does, building `imagick` and `redis` from their release tags' git sources on GitHub so a pecl.php.net outage can't fail it, and runs `sharp/bin/php-extensions` on them, from `php` and from `frankenphp php-cli`. In the full run, it last runs `sharp/bin/test --installed --opcache --repeat --passes --jit` inside the image, so an extension that hooks the engine fails the PHP# suite. The list sits in the job's `EXTENSIONS`, and `sharp/bin/php-extensions` holds a call for each. The job's `INSTALL` names the same extensions as `install-php-extensions` takes them, so an extension added to one list goes in the other too. `LINUX` also fails when a generated file differs from the committed one, when `ext/sharp/sharp_unit.h` differs from the header `sharp/mago/` generates from `Zend/zend_ast.h`, or when `ext/sharp/Cargo.lock` or `sharp/mago/Cargo.lock` names a local `path+file://` or `git+file://` source.

`.github/workflows/mago.yml` runs Mago's own gates in `sharp/mago/` on every pull request, and every push run on `master` as `sharp.yml` runs them, that changes `sharp/mago/`, `sharp/composer/`, the `sharp/harness/Psr/` interfaces the standard library's `mago.toml` includes, `sharp/phpunit/`, the workflow, `sharp/bin/coverage` or the actions it runs, with the toolchain `sharp/mago/Cargo.toml`'s `rust-version` names, which `release.yml` builds the binaries with. Its `checks` job runs formatting, clippy, and that the fuzz crate's `Cargo.lock` is current, and builds the Linux `mago`, in the `test-run` profile `sharp/mago/Cargo.toml` explains, then analyzes the standard library in `sharp/composer/` with it and fails on any note. Its `tests` job runs the workspace tests on Linux and on macOS, beside it. A pull request's run skips the macOS legs of `tests` and `plain-php`, and `release-build`, unless it is the full run, and the push to `master` runs every job. A failed weekly run or push to `master` opens an issue, or comments on the one still open. `typos` and patch coverage run as well. Each job that builds Mago restores the Rust crates and its target folder with `.github/actions/mago-cache`, which only `master`'s full run saves, the weekly or dispatched one here and `sharp.yml`'s nightly or dispatched one. Every other run restores what `master` saved and saves nothing, because only its own ref's runs would restore it. That run's last job, `ci-passed` or `CI_PASSED`, then deletes with `.github/actions/mago-cache-prune` each target a newer one of its kind replaced, so the caches hold one target per job. The self-hosted Macs keep their Mago target instead, and `.github/actions/clean-kept-build` removes it above 12 GB. When a pull request from this repository closes, `.github/workflows/delete-pr-caches.yml` deletes every cache its runs saved, which only its own runs could restore. A pull request from another repository gets a token that can't delete them. The full run runs weekly on `master`'s tip, on a dispatch such as `gh workflow run mago.yml --ref <branch>`, and on a release's bump pull request, by the same rule as `sharp.yml`'s. The weekly and dispatched runs also run the workspace tests and clippy on nightly Rust, and the bump's doesn't, so a nightly that breaks never holds a release. Its `plain-php` job runs on Linux and macOS with the runner's own PHP. It installs `heyjordanparker/php-sharp-composer` from `sharp/composer/` and `heyjordanparker/mago-sharp` from `sharp/mago/`, with the `test-run` `mago` that the `checks` job built on Linux or `release-build` on macOS, then runs `mago analyze` and `mago compile` on a one-class `.sharp` project. Last it installs `sharp/phpunit/` and runs its tests, which must stop with "This project needs the PHP# engine" and print none of `tests/Shop/CheckoutTest.sharp`. Its `ci-passed` job passes when all of them pass or skipped a change that leaves Mago alone.

`.github/workflows/mutants.yml` runs `cargo mutants` on the Mago code that changed on `master` since its last nightly run, nightly only, never on a merge or a pull request. The generated-file step copies `.github/actions/verify-generated-files/action.yml`, so compare the two on every merge from upstream.

A pull request body line such as `Completes: §6.1, §9` claims those sections are done. The `COMPLETES` job in `.github/workflows/completes.yml` runs the base commit's `sharp/bin/spec --completes` on the merge commit whenever the body is opened, edited or pushed to. It fails on a `Completes:` line in any other form, such as `**Completes:** §6`, on a section with no checked example, and on a section `sharp/baseline/spec` still lists. `§9` covers `§9.1`.

`sharp.yml`'s `WORKFLOWS` job fails when a `uses:` in `.github/workflows` or `.github/actions` names an action that is not ours at anything but a full 40-character commit SHA, when a Dockerfile under `sharp/docker` has a `# syntax=` line, whose frontend BuildKit pulls from Docker Hub with no login, and when `actionlint` finds a problem in any workflow. `.github/actionlint.yaml` names the labels of php-src's self-hosted runner, leaves out php-src's composite actions, which have no `description`, and runs shellcheck on every workflow but php-src's `test-suite.yml` and `real-time-benchmark.yml`, kept as php-src writes them so they merge.

`master` takes changes only through a pull request whose `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES` checks pass. A repository ruleset enforces it for every account, admins included, and refuses force pushes and deletion. `CI_PASSED` passes when `WORKFLOWS` passes, and `LINUX`, `LINUX_PASSES`, `LINUX_ZTS`, `HOSTS`, `PHP_EXTENSIONS`, `UBUNTU_WERROR`, `ASAN` and `MACOS` pass, or skip on a pull request that changes documentation alone. `LINUX_PASSES`, `UBUNTU_WERROR`, `ASAN` and `MACOS` also skip on every pull request but a release's bump, and `ASAN` on every push. A red `UBUNTU_WERROR` on a push, or a red `ASAN` in the nightly run, fails `CI_PASSED` and opens the red-master issue. On a release's bump pull request it also needs `RELEASE_BUMP`, `RELEASE_ASSETS` and `IMAGE`, so the bump merges only once every release asset is built. Every other pull request skips them.

PHP# and Mago release together at one version. `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h` and the `version` in `sharp/mago/Cargo.toml` must match. [sharp/CHANGELOG.md](CHANGELOG.md) holds each release's notes, in the Keep a Changelog format, with what to change when upgrading first. A release merges the bump's pull request and pushes the tag on its merge commit, as [docs/how-we-work.md](../docs/how-we-work.md) lists. The bump's pull request has a title that starts with `chore: release`, so `sharp.yml`'s `RELEASE_BUMP` job checks that it changes only what `sharp/mago/scripts/set-version.php` writes, `PHP_SHARP_VERSION` and the version `Zend/tests/sharp/ri_sharp.phpt` expects, and the release's section of `sharp/CHANGELOG.md`. It isn't a required check. The same title makes the pull request's `sharp.yml` and `mago.yml` runs the full runs, every job in every mode. Once it passes on a pull request from this repository, the release's assets build beside the suites, so a release builds them once: `RELEASE_ASSETS` calls `.github/workflows/release.yml`, which builds `mago` for each platform with its attestation, Mago's source archives and `mago-<version>-sha256sums.txt`, the SHA-256 of every other asset, and keeps them for the repository's artifact retention, 90 days, and `IMAGE` pushes both runtime images by digest. It checks every binary but `aarch64-unknown-linux-gnu` and `aarch64-unknown-linux-musl`, which build on an x86_64 runner that can't execute them, with `mago --version`, `mago analyze` and `mago compile` on a one-class `.sharp` project. Pushing a full version tag, such as `v0.4.0`, runs `release.yml` again. It fails unless both versions equal the tag, `sharp/CHANGELOG.md` has a `## [0.4.0]` section, and a `mago.yml` run already passed every job on the tagged commit's tree, on `master` or in a full run, the bump's pull request's included, so it never runs Mago's gates again. That section is the GitHub release's notes. Its `bump_assets` job finds the bump's pull request run on the tagged commit's tree, downloads its assets, and checks each one against its SHA-256 and its attestation, and that the commit the attestation names, the pull request's merge commit, has the tagged commit's tree. The tag then publishes exactly those files as its GitHub release. When no run built them, or they expired, or they were built from another tree, the tag builds them itself, and its run says so. A dispatch of `release.yml` runs the same checks and publishes nothing. `install.sh`, `mago self-update` and the Composer package `heyjordanparker/mago-sharp` download `mago` from these releases. `install.sh` and `mago self-update` verify the archive's attestation with `gh`, or else its SHA-256 against `mago-<version>-sha256sums.txt`, before they extract it. Once the release is public, its `composer` job downloads every published asset and checks it against the SHA-256s the run wrote, then pushes the tag of `heyJordanParker/mago-sharp`: the split of `sharp/mago/`, plus a commit that adds the release's SHA-256s as `composer/sha256sums.txt`, and the Composer wrapper refuses an archive whose SHA-256 differs.

The same tag runs `.github/workflows/sharp.yml`, which fails unless a full run passed all 9 test jobs on the tagged commit's tree: `LINUX`, `LINUX_PASSES`, `LINUX_ZTS`, `HOSTS`, all four `PHP_EXTENSIONS` jobs and `MACOS`. That run is the bump's own, whose `RELEASE_BUMP` passed, or a nightly or dispatched one. Another pull request's run isn't enough, because it runs each mode once. So the tag never runs them again, and its `MACOS` never waits for the Mac runners behind the release's Apple builds. When that check passes, `IMAGE_TAGS` publishes both runtime images, php-fpm's as `ghcr.io/heyjordanparker/php-sharp:<version>` and FrankenPHP's as `ghcr.io/heyjordanparker/php-sharp:<version>-frankenphp`, never as `latest`, for `linux/amd64` and `linux/arm64`. Its `IMAGE` jobs built them on the bump's pull request: each platform builds on its own native runner with Buildx and pushes by digest with BuildKit's provenance and SBOM attestations, and keeps the digest and the commit it built from. On the tag, `CHANGES` finds that run on the tagged commit's tree and checks that the commit has the same tree, and `IMAGE` builds them again only when it finds none. `IMAGE_TAGS` tags the two platforms' digests as one image and attests its provenance, which `gh attestation verify oci://ghcr.io/heyjordanparker/php-sharp:<tag> --repo heyJordanParker/php-sharp` checks. BuildKit, its SBOM scanner and the bases are `.github/workflows/base-images.yml`'s copies in ghcr.io, pulled after the login at the digests `sharp.yml` and the Dockerfiles pin, so the job makes no anonymous Docker Hub pull. CI sets the runtime Dockerfiles' `REGISTRY` to `ghcr.io/heyjordanparker`, and a local build keeps the public default at the same digest. `PHP_EXTENSIONS` scans each image it builds with Trivy, and so does `IMAGE`, each platform's pushed image on its own runner before `IMAGE_TAGS` may tag it, and both fail on a critical or high CVE that a fixed package resolves. Both also start the image on its own default command and request a page from its server with `.github/actions/boot-image`, so the digests `IMAGE_TAGS` tags are the ones that served a page. `base-images.yml` copies the bases every day, and when a copy has a new digest its `PIN` job pushes a branch named from the new digests, which moves the Dockerfiles' pins to them, and opens or updates one issue that names the old and new digests and links the branch's compare view. An Agent opens the pull request from that issue, so its CI runs: one that `GITHUB_TOKEN` opened would start no workflow, and letting GitHub Actions open pull requests would let every workflow's token approve them. The BuildKit pins in `sharp.yml` change by hand with their version, because `GITHUB_TOKEN` can't push a change to a workflow.

On a push to `master`, once `LINUX` and `MACOS` pass or skip a change to documentation alone, and on a tag once it finds those suites passed on `master`, the `COMPOSER` job splits `sharp/composer/` with `splitsh-lite` and pushes the result to the same branch or tag of `heyJordanParker/php-sharp-composer`. It pushes with the split repository's deploy key, kept in the `PHP_SHARP_COMPOSER_DEPLOY_KEY` Actions secret. The split repository's webhook tells Packagist to update `heyjordanparker/php-sharp-composer`, so a tag here becomes a plugin release of the same version. On a branch, the same job splits `sharp/mago/` and pushes it to the same branch of `heyJordanParker/mago-sharp`, a read-only mirror, with the `MAGO_SHARP_DEPLOY_KEY` secret, so Packagist updates `heyjordanparker/mago-sharp`. A tag of the mirror comes from `release.yml`'s `composer` job instead, because it holds the release's SHA-256s. Neither push forces, and the branch push skips a branch the split doesn't contain, as [docs/architecture/mago.md](../docs/architecture/mago.md) says.

Upstream's `Test` and `Windows builds` workflows are disabled on the fork with `gh workflow disable`.
