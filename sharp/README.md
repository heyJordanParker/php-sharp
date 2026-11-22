# PHP#

PHP# is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with a syntax of its own and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [docs/spec.md](../docs/spec.md), and the reason behind each language decision lives in [docs/decisions/](../docs/decisions/).

## Branches

- `master` is PHP#'s only line and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.12`, and all work lands on it directly.
- Upstream's other branches, such as `PHP-8.5`, are read-only mirrors. Upstream's own `master` is reached through the `upstream` remote.
- Upstream is merged into `master`, never rebased. Moving to a newer 8.5.x release means merging that release's tag.
- PHP# owns `sharp/`, `ext/sharp/`, `Zend/tests/sharp/`, `.github/workflows/sharp.yml`, `.github/workflows/completes.yml`, `.github/workflows/mago.yml`, `.github/workflows/release.yml`, `.github/actions/setup-lean/`, `.github/SECURITY.md`, `.github/ISSUE_TEMPLATE/`, `README.md`, `docs/spec.md`, `docs/decisions/` and `docs/php-src.md`. Everything else is an engine file.
- A merge from upstream meets conflicts only in engine files a feature had to change, and in the upstream files PHP# replaced or deleted: `README.md`, the forms in `.github/ISSUE_TEMPLATE/`, and php-src's own workflows, actions and scripts under `.github/` and `.circleci/`, which test platforms PHP# doesn't run on. Each of those keeps PHP#'s side, a deleted file staying deleted.

## Commands

Run every command from the repository root.

```sh
sharp/bin/check                 # the checks a pull request's required jobs run, on this machine
sharp/bin/check mago-checks     # only these gates of it, by name
sharp/bin/build                 # incremental build of sharp/build/<os>-<arch>/sapi/cli/php, installed into its install/
sharp/bin/build --clean         # rebuild from scratch
sharp/bin/build-mago            # build sharp/mago/ as cargo test does, and sharp/build/<os>-<arch>/mago/debug/mago
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/, in its four modes
sharp/bin/test --linux          # run it in the sharp/docker/ image, on Linux
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --shard 2/3      # run the second of three slices of the PHP# suite instead, balanced by their seconds
sharp/bin/test --census         # also check every write and comparison of a field that holds a PHP# mark
sharp/bin/test --spec           # also compile and run every code example in docs/spec.md and README.md
sharp/bin/test --differential   # also check that php -l compiles every Zend/tests/sharp/*/*.sharp file
sharp/bin/test --phpunit        # also run the PHP# test class in sharp/phpunit/ under PHPUnit
sharp/bin/test --linux --upstream # run Zend/tests, ext/reflection, ext/tokenizer and ext/opcache, without opcache
sharp/bin/test --mago           # run sharp/mago/'s workspace tests, its doc tests and mago-codex's property tests
sharp/bin/test --mago --cargo-built # run only the doc tests and the property tests, which cargo builds
sharp/bin/hosts --linux         # serve sharp/hosts/ from FrankenPHP, and compare with sharp/hosts/expected/
sharp/bin/php-extensions gd zip # check that php -m lists each PHP extension, and call into each one
```

Every build and test runs as many jobs at once as `sharp/bin/jobs` prints: `CARGO_BUILD_JOBS` when it is set, or else 6, or the machine's cores when it has fewer. `sharp/bin/build`'s `make` and the cargo build of `ext/sharp` it runs, `sharp/bin/build-mago`'s cargo, `sharp/bin/test`'s `run-tests.php` workers and test threads, `sharp/bin/spec`'s compiles and `sharp/bin/hosts`'s Go build read it. A builder's Mac has 12 cores and runs other builders' work beside its own, so 6 keeps one run from taking the machine.

PHP# runs on FrankenPHP alone, so `sharp/bin/build` builds the one engine thread-safe, with `--enable-zts --enable-embed --disable-zend-signals`, as docker-library's `zts` images do, so FrankenPHP links its `libphp`, and installs it into the build's `install/`. Apple silicon has no thread-safe JIT, as `ext/opcache/config.m4` warns, so on macOS `sharp/bin/test` runs the suite without opcache and in shared memory, and the JIT's tests skip. `--linux` runs all four modes.

`sharp/bin/hosts` runs on Linux, so `--linux` runs it in the `sharp/docker/` image. It needs `sharp/bin/build` first, for the installed engine, and builds `mago` as `sharp/bin/test` does. It installs the fixture into an empty `vendor/`, as `--phpunit` does. It builds FrankenPHP v1.13.5, the release the image ships, with Go against that engine into `sharp/build/<os>-<arch>/frankenphp/v1.13.5/`, then requests the fixture's paths from one FrankenPHP worker: the lifetimes twice, `/types` 1,000 times, `/flood` past the type limit, `/words`, a type the program makes that no request made before, then `/types` and `/` again. The output must equal `sharp/hosts/expected/frankenphp.txt`. Four more workers then serve 400 requests, 32 at once, in one process.

`sharp/bin/test` first compares the version the built engine prints in `php --ri sharp` with the release version, the root package's `version` in `sharp/mago/Cargo.toml`, and checks that the engine is thread-safe, and refuses to run until `sharp/bin/build` rebuilds an engine that differs. `--upstream` runs on Linux alone, where `sharp/baseline/linux` holds the results `UPSTREAM` compares with.

The engine runs only `.sharpc` files that `mago compile` wrote. So before any test runs, `sharp/bin/test` builds `mago` from `sharp/mago/` with `sharp/bin/build-mago`, into `sharp/build/<os>-<arch>/mago/debug/`, as `sharp/bin/hosts`, `sharp/bin/bench` and CI do, and runs `mago compile` on `Zend/tests/sharp/`, a project shaped like an application: its `composer.json` maps each fixture namespace to its folder, its `vendor/` holds the packages its `composer.lock` pins, a copy of `sharp/composer/` among them, and the compile writes its `.sharp/` folder, `.sharp/preload.php` included. Composer keeps an earlier run's copy of `sharp/composer/` while the lock is unchanged, so the run reinstalls that one package when its copy differs from `sharp/composer/`, and the install then dumps the autoloader, which runs the package's plugin. The fixtures sit in folders below `Zend/tests/sharp/`, never beside a test, since `run-tests.php` writes each test's script beside it, where Composer's loader would take it for a class. cargo rebuilds only what changed, so a run after a checker change tests that change. It builds what `cargo test --workspace` builds, in the dev profile `sharp/mago/Cargo.toml` explains, so the tests and `sharp/bin/test` share one build. `Zend/tests/sharp/mago.toml` makes the `.sharp` fixtures, the plain PHP classes and functions they call, in `Zend/tests/sharp/harness/`, and the packages in `vendor/`, the standard library among them, one checker project. A fixture the checker refuses fails the run, because a refusal is Mago's to test. Tests that build their own project, such as the refusal tests, find `mago` in `TEST_MAGO_EXECUTABLE`.

Each run points `TMPDIR` at a folder of its own and removes it when the run ends, after it stops `run-tests.php`, its workers and their tests when the run ends early, as on SIGTERM, so no file a test writes in the temporary folder reaches another run.

`--differential` checks that every `.sharp` fixture `mago compile` accepted makes `php -l` print nothing but "No syntax errors detected", so a compile warning or deprecation also fails the run.

`--phpunit` installs `sharp/phpunit/`, a Composer project that requires `sharp/composer/` and the PHPUnit version its `composer.lock` pins, into an empty `vendor/`, because Composer keeps an earlier run's copy of `sharp/composer/` while the lock is unchanged. It compiles it with the `mago` it built, and runs its tests with the engine under PHPUnit twice: in one process, and with `--process-isolation`. Its PHP# test class extends PHPUnit's `TestCase` and declares a test module, so the run fails when the test module does not apply in PHPUnit's process, in the child process of `#[RunInSeparateProcess]`, or before the test's own `Before` method. It needs `composer` on the path, which the `sharp/docker/` image installs.

`sharp/bin/test` runs the suite in the four ways production runs the engine, as every pull request's CI does: without opcache, in opcache shared memory with each test twice in one process, under `opcache.jit=function`, which compiles each function of a file as the file loads, and under `opcache.jit=tracing`, with every hot counter at 1. So a test written once runs under each JIT, and no test copies another to set a JIT of its own. Each expects the output of the run without opcache. A test whose output depends on the whole optimizer, such as a count of oplines the JIT left to the VM, sets `opcache.optimization_level=0x7FFEBFFF` in its `--INI--` section. The run of the whole suite then runs the whole-project proof of `mago convert`.

`--shard <i>/<n>` runs only the i-th of n slices of the `.phpt` files under `Zend/tests/sharp/`, so n runs with the same flags run each test once, as CI's jobs do. The slices balance by the seconds `sharp/baseline/seconds` lists, which CI's slow-test summaries measured: a test's seconds summed over the modes, and the proof of `mago convert`, which holds its slice alone while it runs, so it weighs its seconds times the workers. A test the file doesn't list weighs 1. Each item, heaviest first, goes to the slice that weighs least so far, so every slice computes the same split, and one of them runs the proof. A test that runs past 10 seconds belongs in the file. `sharp/bin/spec --shard <i>/<n>` takes every n-th example instead.

`--mago` runs `sharp/mago/`'s workspace tests: every test binary `sharp/bin/build-mago` builds, from its package's folder as `cargo test` runs it, then the doc tests and `mago-codex`'s property tests. With `--shard <i>/<n>`, it runs the i-th of n slices of all the binaries' tests, which balance by the seconds `sharp/baseline/seconds` lists for each test of 1.5 s or more on Linux, under its binary's source file and its name, as `sharp/mago/tests/main.rs::compile::<name>`. A binary runs its tests on as many threads as the run's jobs, so it takes the longer of its longest test and its tests' seconds over the threads, and each test, heaviest first, goes to the slice whose time that grows least. A binary runs its slice by skipping the other tests by exact name, which libtest and `libtest-mimic` both take. `--cargo-built` runs only the doc and the property tests, which cargo builds as it runs them, without the Composer packages and the Lean the binaries' tests run. `--shard` and `--no-build` leave them out, so a run of every slice runs them with `--cargo-built`, as CI's `BUILD_LINUX` does.

`--census` runs `sharp/bin/census`. It reads the register of PHP# marks in `Zend/zend_compile.h` and fails on each line in `Zend/` and `ext/opcache/` that writes or compares a marked field of a marked opcode or AST kind without naming the mark, as the header of `sharp/bin/census` describes. A new mark goes in the register, beside the upstream flags of the same field and kind, with a `ZEND_STATIC_ASSERT` that fails the build when they overlap.

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux`. In a git worktree, `sharp/docker/run` also mounts the main checkout's `.git` folder at its own path, because the worktree's `.git` file names a folder inside it, so git, `sharp/bin/spec` and `mago compile` find the repository in the container as they do in the main checkout.

`sharp/bin/check` runs the checks a pull request's required jobs run, on this machine: every gate, as every CI run runs every job. A gate CI runs in the `sharp/docker/` image runs there, and a gate CI runs on the runner itself runs on this machine, so each check meets the platform it meets in CI:

- `workflows`, `WORKFLOWS`'s checks of the workflows
- `spec-guard`, `SPEC_GUARD` with the merge base's `sharp/bin/spec`, and `COMPLETES` when `BODY` holds the pull request's body
- `mago-checks`, `mago.yml`'s `checks`, which needs `typos` on the `PATH`
- `linux`, `LINUX` and `SPEC`, with the self-test of `sharp/bin/spec` that `UPSTREAM` runs
- `upstream`, `UPSTREAM`
- `hosts`, `HOSTS`
- `generated`, the checks of generated files `BUILD_LINUX` runs
- `mago-tests`, `mago.yml`'s `build` and `tests`
- `plain-php`, `mago.yml`'s `plain-php`, on the test-run `mago` `mago-checks` builds

`sharp/bin/check <gate>...` runs only those, and CI's jobs run `generated`, `workflows`, `mago-checks` and `plain-php` through it, since no other script holds them. It ends with each gate's time and result.

`sharp/bin/build` configures with `--enable-werror`, as php-src's own CI does, and so does the runtime image in `sharp/docker/runtime/`, which `IMAGE` builds at `-O2` on amd64 and arm64 for every push to `master`, so a compiler warning fails the build that brings it in. A server runs the image, so no deploy compiles the engine with another compiler.

`sharp/bin/build` builds from nothing when the build folder was configured with other flags, such as an engine without thread safety, since php-src's Makefile tracks no header. It reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global`, `sharp/docker/Dockerfile`, `sharp/bin/build` or `Zend/zend_modules.h` changes. `configure` reads `ZEND_MODULE_API_NO` from `Zend/zend_modules.h` into the extension directory.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --linux --upstream` compares the run without opcache with `sharp/baseline/linux`, and fails on any difference. It lists every test that did not pass on the untouched, thread-safe `php-8.5.12` build, as `STATUS<TAB>path`. An upstream FAIL, BORK or LEAK fails the run before any comparison, so the baseline only ever holds skips and expected failures. A changed result is a regression until the language owner accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## Spec examples

`--spec` runs `sharp/bin/spec --self-test`, which checks one example for each of the checker's own rules in `sharp/spec-self-test.md`, then `sharp/bin/spec`. It compiles every checked code block in `docs/spec.md` and `README.md` with the `mago` built from `sharp/mago/`, runs the lines that claim a value or a throw on the engine, and compares each annotated line with its annotation. An HTML comment above a block, such as `<!-- example map-reads: statements alone -->`, names the example and says how to build it, as [docs/architecture/spec-examples.md](../docs/architecture/spec-examples.md) describes. Every example compiles against the classes and plain PHP stubs in `sharp/harness/`. A law example (section 28) needs Lean. mago runs the Lean version it pins from its own cache folder, `sharp/build/cache` there, or as elan's toolchain in `sharp/build/elan`, where `sharp/bin/test --mago` installs it, and else downloads it into its cache folder the first time a law compiles.

A `compile error` line needs the error its rule gives: a "not supported yet" refusal, a missing class the line doesn't name itself, or an unreadable line never satisfies it, and an error moves to it from another line only when it is one of the whole-construct errors in `MOVES` and its line's brackets enclose it. A value or `throws` annotation that no run reaches fails the check, unless a hidden `<!-- run <id> -->` comment after the example's last block holds a run line that makes the same claim.

`sharp/baseline/spec/` lists every example that fails today, one empty file `<section>/<id>` each, so pull requests that fix different examples never conflict. Its count is how far PHP# is from v1. Git tracks files, not folders, so once no example fails, a checkout has no `sharp/baseline/spec/` at all. A missing folder means no example fails: `sharp/bin/spec` reads it as an empty list, in the working tree and, with `--base`, at the base commit. The run fails when an unlisted example fails or a listed one passes, and prints the `git rm`, `mkdir -p` and `touch` commands that make the folder match. The list records which examples fail, not how, so a listed example may change its errors.

On a pull request, the `SPEC_GUARD` job runs the base commit's `sharp/bin/spec --base <commit>`, from the base branch's `.github/workflows/completes.yml` on `pull_request_target`, so a pull request never runs or changes its own comparison. A feature pull request changes the spec, its examples and `sharp/baseline/spec` together with its code. `GUARDED` in `sharp/bin/spec` names what measures the examples, `sharp/bin/spec` itself, and a pull request that changes any other file leaves it exactly as the base has it. Markdown never runs, so a Markdown file may change beside it. A pull request that adds or changes a file in `sharp/harness/` lists it in its body, and its review checks that no stub makes an example pass falsely. The guard lists changed files with `--no-renames`, so a renamed guarded file shows its old path, and it fails on a symbolic link at a file it reads or guards, naming the path only. The fingerprint covers the whole example but its id: its section, and every block's marker line, setup, fence language, code and run comment. An example the base has with the same fingerprint, under its id or another, never joins the list, except that a renamed example takes over its old id's entry. An `ignore` on code the base checked counts as an entry.

## Runtime images

PHP# ships its engine only as one runtime image: FrankenPHP on the thread-safe engine, `ghcr.io/heyjordanparker/php-sharp:<version>`. A release's other assets are `mago` archives, Mago's source and their SHA-256s, with no `php` build. A server that can't run the image builds the engine from source, with the [Linux dependencies](#linux-dependencies).

`php --ri sharp` shows that a `php` is the PHP# engine. It prints the engine's version, and plain PHP prints `Extension 'sharp' not present.` and exits with status 1:

```text
$ docker run --rm ghcr.io/heyjordanparker/php-sharp:0.6.27 php --ri sharp
sharp

Version => 0.6.27

Directive => Local Value => Master Value
sharp.compile_command => no value => no value
```

`sharp.compile_command` is the engine's one setting. It stays empty in production, and [Development](#development) says what it does in a dev container.

`ghcr.io/heyjordanparker/php-sharp:<version>-dev` is the same image with the Lean version that release's `mago` proves laws with already installed, so a `mago compile` with laws in a container built from it downloads nothing. Its `XDG_CACHE_HOME` is `/var/cache`, so Mago's cache folder is `/var/cache/mago` for every user, and every user can read and write it, so a container run with `--user` finds the Lean too. The first compile with laws still builds the law checker's library there, in a few seconds. Lean takes about 3 GB, so build a dev container or a CI job on `<version>-dev` and a production image on `<version>`.

`sharp/docker/runtime/frankenphp.Dockerfile` builds the engine with docker-library's `8.5/trixie/zts` recipe, from this repository's source instead of the php.net tarball, with `ext/sharp` built in, then runs FrankenPHP v1.13.5's own Dockerfile stages on it, as FrankenPHP's images build on `php:8.5-zts-trixie`. It keeps docker-library's layout, so `docker-php-ext-*` and `install-php-extensions` work unchanged and an app on FrankenPHP's image switches to PHP# by changing only its `FROM` line. The `docker-php-*` scripts are verbatim copies, and `zts/docker-php-entrypoint` is docker-library's `zts` variant's. `sharp/bin/hosts` builds the same FrankenPHP release for `HOSTS`. Go 1.27, `e-dant/watcher` 0.14.5 and `install-php-extensions` 2.13.2 are pinned where FrankenPHP takes the latest. FrankenPHP's source is fetched by its release's commit, and the `e-dant/watcher` archive and the `install-php-extensions` script are checked against their SHA-256s. Compare the Dockerfile, the shared scripts and `zts/docker-php-entrypoint` with docker-library's `8.5/trixie/zts`, and the Dockerfile with FrankenPHP's `Dockerfile`, on every merge from either. FrankenPHP's stage rewrites `php` in the `zts` entrypoint to `frankenphp run`, so the image starts FrankenPHP on its default command.

The build context is the checkout, which CI builds from at the run's commit. The repository's `.dockerignore` leaves out the checkout's build output:

```sh
docker build -f sharp/docker/runtime/frankenphp.Dockerfile -t php-sharp .
```

A server whose PHP comes from its distribution, such as Ubuntu's packages, can't load PHP#'s extensions there: PHP# reports module API `20261009`, and every distribution extension is built for PHP's own. There PHP runs in the image, and FrankenPHP serves the app. General FrankenPHP and Docker practice is [FrankenPHP's](https://frankenphp.dev/docs/production/), and docker-library's [documentation](https://github.com/docker-library/docs/blob/master/php/README.md) applies to the image's PHP unchanged. What follows is what a production app on PHP# gets wrong without being told.

### Extensions

The image ships the extensions its release's `php -m` lists. For 0.6.51:

```text
$ docker run --rm ghcr.io/heyjordanparker/php-sharp:0.6.51 php -m
[PHP Modules]
bcmath
calendar
Core
ctype
curl
date
dom
exif
fileinfo
filter
ftp
gd
hash
iconv
igbinary
imagick
intl
json
lexbor
libxml
mbstring
mysqlnd
openssl
pcntl
pcre
PDO
pdo_mysql
pdo_pgsql
pdo_sqlite
pgsql
Phar
posix
random
readline
redis
Reflection
session
sharp
shmop
SimpleXML
sockets
sodium
SPL
sqlite3
standard
sysvmsg
sysvsem
sysvshm
tokenizer
uri
xml
xmlreader
xmlwriter
Zend OPcache
zip
zlib

[Zend Modules]
Zend OPcache
```

Beside the extensions PHP builds by default, the image holds the ones an app's image would otherwise build again on every release:

- every extension of PHP's own source that links no outside library: `bcmath`, `calendar`, `exif`, `ftp`, `pcntl`, `shmop`, `sockets`, `sysvmsg`, `sysvsem` and `sysvshm`, with `intl` and `zip`, which the standard library runs on
- the database drivers `pdo_mysql`, `pdo_pgsql` and `pgsql`
- `gd`, `imagick`, and `redis` with `igbinary` as its serializer, which Laravel Sail's and serversideup's images ship

The app's image installs every other extension it needs with [`install-php-extensions`](https://github.com/mlocati/docker-php-extension-installer), which the image ships, such as `xsl`, `gettext`, `ffi`, or a development extension such as `xdebug`, `pcov` or `excimer`. A line that installs an extension the image holds builds nothing: `install-php-extensions` says "Module already installed" for it.

### The app's image

The app builds its own image on the release, with its extensions, the production `php.ini` and its own ini settings:

```dockerfile
FROM ghcr.io/heyjordanparker/php-sharp:0.6.51
RUN install-php-extensions xsl
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY app.ini "$PHP_INI_DIR/conf.d/"
COPY Caddyfile /etc/frankenphp/Caddyfile
RUN chown -R 1000:1000 /data/caddy /config/caddy
```

The last line gives Caddy's folders to the deploy user the containers run as, here uid and gid 1000, as [FrankenPHP's docs](https://frankenphp.dev/docs/docker/#running-as-a-non-root-user) do for a user that is not root. The image loads no `php.ini`, so until the app copies `php.ini-production`, PHP runs with `display_errors` on. `php.ini-production` still leaves `expose_php` on and `memory_limit` at `128M`, so `app.ini` sets them, with the opcache setting the deploy below depends on:

```ini
expose_php = Off
memory_limit = 256M
opcache.validate_timestamps = 0
```

The app's `Caddyfile` replaces the image's, which resolves a root that is a symbolic link once, when FrankenPHP starts. Its `resolve_root_symlink false` keeps the root the link the deploy below moves:

```caddyfile
{
	frankenphp
}

{$SERVER_NAME} {
	root {$SERVER_ROOT}
	encode zstd br gzip
	php_server {
		resolve_root_symlink false
	}
}
```

### FrankenPHP, the workers and the scheduler

A Capistrano-style deploy root, such as `/srv/www/app`, holds `releases/<timestamp>`, `shared/` and the `current` link. The `current` link and each release's links into `shared/` name absolute paths, so the container mounts the whole deploy root at its own path, where every link resolves. The app's Caddyfile serves `SERVER_ROOT` for the addresses `SERVER_NAME` names, and `SERVER_ROOT` names the `current` link's `public` folder, so a request reads the release the link names, through every file opcache didn't cache.

FrankenPHP, the queue worker and the scheduler each run as their own container of the app's image, here `app-php`:

```sh
docker run -d --name app --restart always --user "$(id -u web):$(id -g web)" --network host \
  -e SERVER_NAME=app.example.com -e SERVER_ROOT=/srv/www/app/current/public \
  -v /srv/www/app:/srv/www/app -v app-caddy-data:/data -v app-caddy-config:/config app-php
docker run -d --name worker --restart always --user "$(id -u web):$(id -g web)" --network host --memory 512m \
  -v /srv/www/app:/srv/www/app -w /srv/www/app/current app-php php artisan queue:work
docker run -d --name scheduler --restart always --user "$(id -u web):$(id -g web)" --network host --memory 256m \
  -v /srv/www/app:/srv/www/app -w /srv/www/app/current app-php php artisan schedule:work
```

`--user` runs FrankenPHP and the workers as the deploy user that owns the releases, here `web`, so the app writes `shared/storage` as that user. FrankenPHP serves HTTP and HTTPS on the host's ports 80 and 443 itself, so no nginx runs in front of it: the image gives `frankenphp` the capability to bind them as any user. Caddy keeps its certificates in `/data` and its configuration in `/config`, so the named volumes keep them across restarts. `--network host` gives each container the host's `127.0.0.1`, where the app reaches a Redis or a database that listens only there.

### Deploying a release

The engine runs only the `.sharp/…c` units that `mago compile` writes, never a `.sharp` source. So each release compiles in its own folder before `current` moves to it, and then FrankenPHP restarts its PHP threads and the workers restart:

```sh
cd /srv/www/app/releases/<timestamp>
composer install --no-dev --optimize-autoloader
docker run --rm --user "$(id -u web):$(id -g web)" -v /srv/www/app:/srv/www/app -w "$PWD" app-php vendor/bin/mago compile
ln -sfn "$PWD" /srv/www/app/current.next && mv -T /srv/www/app/current.next /srv/www/app/current
docker exec app curl --silent --show-error --fail -X POST http://localhost:2019/frankenphp/workers/restart
docker restart worker scheduler
```

`composer install --no-dev` installs no `require-dev` package, so `heyjordanparker/mago-sharp` goes in the app's `require`, or the release has no `vendor/bin/mago`.

With `opcache.validate_timestamps=0`, opcache never checks a file it cached, and the engine checks a unit against its source only when opcache compiles the file. A `.sharp` file that FrankenPHP ran before its source changed, or before `mago compile` rewrote its unit at the same path, keeps running its cached bytecode with no refusal until FrankenPHP restarts its PHP threads. After the restart, the first request compiles it again, and the engine refuses a unit that is missing or out of date, as in [Running tests](#running-tests), or runs the new one. With the default `opcache.validate_timestamps=1`, opcache asks the engine for the unit's revision every `opcache.revalidate_freq` seconds, 2 by default, so FrankenPHP refuses an uncompiled edit and runs a compiled one without a restart.

opcache keeps every release's bytecode in FrankenPHP's memory, so a request after the link moved still runs the old release's cached bytecode, which loads the old release's files. The restart goes through FrankenPHP's admin API, which the image serves on `localhost:2019`. FrankenPHP restarts each PHP thread once its request is done, which empties opcache, and holds each request that arrives meanwhile until a thread is back, so the server answers every request through a deploy, and each request after the restart runs the new release. A request still running 6 seconds into the restart is stopped. `mv -T` replaces the link in one step on a Linux file system, so no request finds it missing. On a folder Docker Desktop shares from macOS, a path read through the link while `mv` replaces it fails for an instant, and that request gets a 404, so the deploy root lives on the server's own disk. `sharp/bin/hosts` runs this deploy while requests arrive 8 at a time, and checks that each one succeeds. The admin API restarts FrankenPHP's worker scripts too, for an app that serves them, but a worker script FrankenPHP resolved through the `current` link when it started stays in its release until `docker restart app`.

A worker or scheduler container resolves `-w /srv/www/app/current` once, when it starts, so it keeps running the release it started in. `docker restart` starts each in the new release, and Laravel's worker with `pcntl` stops between jobs on its signal.

### Development

An edit to a `.sharp` file, or to a plain PHP file whose signature a unit read, such as a base model's, makes the unit out of date, and the engine refuses it until the next `mago compile`. A dev container compiles it when it loads instead, with this line in its `php.ini`, or in a file in `conf.d`:

```ini
sharp.compile_command = "vendor/bin/mago --no-version-check --no-extensions compile"
```

- **The engine runs the command at most once per request,** when a `.sharp` file it loads isn't compiled or is out of date, then loads what it wrote. FrankenPHP, its worker mode and `php` on the command line all run it.
- **It runs in the project root,** the nearest folder above the file that holds `.sharp/`. A project with no `.sharp/` folder has no root, so its first compile is by hand.
- **`vendor/bin/mago` is the Composer wrapper,** and it runs on the engine's own `php`. The binary it wraps sits under a folder named for its version and platform, so no line names it.
- **`--no-extensions` keeps the extension hosts out of the compile a request waits for.** Their rules run in `mago analyze` and in CI, and the deploy's `mago compile` checks every one.
- **`mago analyze` of the whole project writes the `.sharp` folder too,** as `mago --no-extensions compile` writes it, before its hosts run, from the same analysis and lowering as the hosts' folder. A save needs `mago analyze` alone.
- **A compile never starts another.** The command runs with `SHARP_COMPILING=1`, and so does every process `mago` starts, such as an extension host's `php`. A process that has it set never runs the command, and gets the refusal. Without it, an extension host that loaded a file the checker refused would compile again, and that compile's hosts again, until `fork` failed.
- **The user that runs PHP must be able to write the project's `.sharp/` folder.** A command that fails adds how it ended and its last three lines of stderr to the refusal, as "… failed with exit 2: … ERROR can't write the file `…/.sharp/app/Orders/Order.sharpc`: Permission denied (os error 13)" for a project the user can't write.
- **A project compiles once at a time.** A request holds the lock of `.sharp/compile.lock` while it runs the command, in one container or in several on one host's mount, as FrankenPHP, the queue worker and the scheduler are. A request that finds a file stale while another compiles waits for that compile, checks its file again, and runs the command only when the file is still stale. `mago compile` writes each file beside the old one and renames it over it, so a request reads the old file or the new one, never part of one.
- **A failed compile fails again at once until an input changes.** `mago compile` keeps the record of a compile that refused a file, with what it printed, so the next compile with every input as it was prints the same errors and exits 1 without compiling. Each request that waited for a failed compile gets its refusal that way. A compile whose law checker refused a file keeps no record, so the next one runs the checker again.
- **The compile ends with the request that started it.** The command runs in a process group of its own, and once it exits, the engine kills every process it left running. A request killed mid-compile, and on Linux one that times out or whose thread FrankenPHP stops, leave the group to a watcher process in it, which sends it SIGTERM, which `mago` passes on to the processes it started, and SIGKILL a second later.
- **With opcache's default `opcache.validate_timestamps=1`,** FrankenPHP checks a file it cached every `opcache.revalidate_freq` seconds, 2 by default. A request within 2 seconds of an edit runs the bytecode it cached, with no compile.
- **FrankenPHP's worker mode sees an edit only after the worker restarts,** as plain PHP's worker mode does. A worker keeps the classes it loaded, reads each input of a unit once, and runs the command at most once. The first stale file it loads after a restart compiles.

A request after an edit waits for one whole `mago compile`. On a project of 79 PHP# classes and about 1,900 PHP files, the request right after an edit waits about 7 s in a container. Measured on the previous release's image, for projects with no extension host, on a Mac whose load averaged 120 on 12 cores:

| Project | Where | Request after an edit | The request after it |
| --- | --- | --- | --- |
| One PHP# class and the standard library | `php`, a folder in the container | 670 to 850 ms | 13 to 70 ms |
| 79 PHP# classes and 1,979 PHP files | `php`, an OrbStack bind mount | 7.1 s, the median of 3 | 67 ms |

The compile alone took a median of 3.8 s in that container through the mount, and 1.9 s on the Mac itself, with the same `mago` built for macOS.

Production leaves the setting empty. A stale deploy is then refused, loudly, instead of compiled by the first request, which would wait seconds and need a release the PHP user can write. The deploy runs `mago compile` before the code runs, as in [Deploying a release](#deploying-a-release).

## Composer

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. Composer's autoloader then tries `.sharp` after `.php` by the PSR-4 rules. Composer's class map scanner reads only `<?php` files, so on `composer dump-autoload --optimize` the plugin writes every `.sharp` file under a PSR-4 folder to `vendor/composer/autoload_sharp.php`, and the autoloader reads it. The package's autoloader includes every `.sharp` class itself, never Composer's, so on plain PHP the first one stops with "This project needs the PHP# engine" before its source can reach the output. A test file a runner includes itself stops at the plugin's test bootstrap, `require-engine.php`, as [Running tests](#running-tests) describes. The engine comes from the [runtime images](#runtime-images). PHP code that lists a folder's `*.php` files finds no PHP# class, so an app names each PHP# console command, controller and listener through its framework's explicit registration (decision 81).

`sharp/composer/library/Sharp/Internal/Builtins.sharp` gives each PHP built-in function its effect, as `extern global.fopen uses Files;`. Only a standard library file names a built-in through `global`: in a project's code, `global` is an ordinary name (spec section 23), and a built-in is reached only through the standard library (spec section 8). `sharp/bin/builtins` writes it from PHPStan's php-8-stubs, at the version and SHA-256 it pins, for every PHP 8.5 function in PHP's root namespace that Mago's prelude in `sharp/mago/crates/prelude/` declares. `RULES` in the script maps each function to its effects, or to none, by its extension and name, and the run fails on a function no rule covers. Each line ends in `uses`, and a function no effect reaches says `uses none`. The file also declares pure each PHP 8.5 exception class the prelude declares, as `extern ValueError uses none;`: `Exception`, `Error` and every class whose parents reach one of them. `sharp/bin/builtins --self-test` checks that each rule gives the effect of its row in `ROWS`, and that removing the rule changes that row. The Linux job runs both and fails when the file differs from the committed one.

`sharp/composer/` is the plugin's source of truth. Composer installs it from `heyJordanParker/php-sharp-composer`, a read-only split that CI rewrites from this folder's history, the way Symfony splits its components. Change the plugin here, never in the split.

## Running tests

An app's tests run on the PHP# engine under PHPUnit or Pest, through the same loading as the app:

```sh
vendor/bin/mago compile         # first, after every change to a .sharp file
vendor/bin/phpunit
vendor/bin/pest --parallel      # or vendor/bin/pest
```

A test run loads a PHP# class the way the app does. The test runner includes a test file it lists itself, and the plugin's loader includes any other `.sharp` file by the PSR-4 rules. Either way, the engine runs the compiled unit, `.sharp/<path of the source>c` under the project root, and never the source. So `mago compile` runs before the tests. A test that loads a class whose unit is missing or out of date fails with the refusal, such as `app/Mail/SmtpMailer.sharp is out of date (app/Mail/SmtpMailer.sharp changed). Run vendor/bin/mago compile.`

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

A source build on Debian or Ubuntu needs the packages `sharp/docker/runtime/frankenphp.Dockerfile` installs to build the image's engine, without `xz-utils` and `curl`, which only its source archive and the image's users need:

```sh
apt-get install --no-install-recommends autoconf bison ca-certificates dpkg-dev file g++ gcc git libargon2-dev libc-dev \
  libcurl4-openssl-dev libicu-dev libonig-dev libreadline-dev libsodium-dev libsqlite3-dev libssl-dev libxml2-dev \
  libzip-dev make pkg-config re2c rustup zlib1g-dev
```

On a fresh Debian trixie or Ubuntu 24.04 with only these, `./buildconf --force`, then `./configure $(sharp/docker/runtime/configure-options)`, the options that Dockerfile configures with, and `make`, build `sapi/cli/php` and the embed SAPI's `libphp` FrankenPHP links, and `php --ri sharp` prints the tree's version. `rustup` downloads the Rust toolchain `ext/sharp/rust-toolchain.toml` names at the first build. Every engine builds `intl`, since the standard library's `string.normalized()` runs on it, `bcmath`, since its `Decimal` and `BigInteger` run on it, and `zip`, since its `ZipArchive` runs on it. `sharp/bin/build` also configures `--with-pgsql`, so it needs `libpq-dev` as well, which `sharp/docker/Dockerfile` installs.

## CI

Every macOS job in `.github/workflows/sharp.yml`, `.github/workflows/mago.yml` and `.github/workflows/release.yml` runs on the one self-hosted runner labelled `macos-arm64-local`, on an M2 Max, one job at a time on every core: the runner's `.env` sets `CARGO_BUILD_JOBS` to the Mac's 12 cores. A run has one Mac job, `sharp.yml`'s `MACOS`: it builds the engine `mago` links once, and runs every check only macOS can fail, so the Mac's checks never queue behind each other. Every pull request's push runs them, and a push to `master` whose tree no pull request's run passed, as [docs/how-we-work.md](../docs/how-we-work.md) says. The push to `master` that releases a version also runs `release.yml`'s macOS build, which builds the release `mago` alone. A job on the runner installs nothing outside its workspace and the runner's own `RUSTUP_HOME`, `CARGO_HOME` and `HOME`, so it uses Homebrew's PHP, Composer and build formulae as installed, ccache among them, which the Mac job builds the suite's engine through. The jobs of every workflow share the runner's one work folder, so each that builds checks out with `clean: false`, then runs `.github/actions/clean-kept-build`, and runs it again as its last step. It runs `git clean -ffd` and `git clean -ffdx --exclude=/sharp/build/ -- sharp`, and removes every build the next Mac job doesn't reuse, so the runner keeps only the engine builds and the dev Mago target's `debug` profile, at most about 9 GB, where the release target, the corpus's packages and an old `test-run` profile took its `sharp/build` to 20 GB on 2026-11-22. Its `git clean` keeps the parsers, scanners and `configure` that php-src's build writes into the source tree, which the kept build's dependency files name, so the engine and `mago` rebuild only what changed since the runner's last job, and `sharp/bin/build` configures again only when its flags, `CC`, `CXX` or the content of a file configure reads changed. Every job but `RELEASE` and `release.yml`'s `sums`, which check nothing out, runs `.github/actions/usage` after its checkout and as its last step, which writes a notice titled `usage` with the CPU-seconds and the peak memory the job used, so every run measures itself.

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on macOS, for every push to a pull request into `master` and every push run on `master`, whatever it changed. A pull request and `master` each run one push at a time: a run in progress finishes, and a newer push replaces the one still waiting, so a push run checks every change since `master`'s last push run that wasn't cancelled. In a pull request's run, a job that fails runs `.github/actions/cancel-failed-run` as its last check, which cancels the run, so the newest push's run starts at once: run 45007969043 failed `SPEC (2/4)` at 09:59 UTC on 2026-11-22, then its Mac job ran 48 more minutes, and the next run waited 36 minutes until it was cancelled by hand. A pull request runs the Linux suites in slices on GitHub's 4-core runners: test pull request #381's runs ended in 12.0 and 13.2 minutes, and its longest suite job took 7.6. Its `sharp.yml` and `mago.yml` runs hold up to 18 hosted jobs at once, as a release's pull request's runs did, of the 20 hosted jobs GitHub runs at once across the account's repositories. `LINUX (1/2)` and `(2/2)` each run a slice of the PHP# suite with `sharp/bin/test --shard` on the thread-safe engine FrankenPHP runs, in its four modes, balanced by the seconds of `sharp/baseline/seconds`, so the slice that weighs least also runs the whole-project proof of `mago convert`. The second slice also runs `--census --differential --phpunit`, so a PHP# test class runs under real PHPUnit on every change. `UPSTREAM` runs the upstream tests without opcache, and `sharp/bin/spec --self-test`. `SPEC (1/4)` to `(4/4)` each check a fourth of the code examples in `docs/spec.md` and `README.md` with `sharp/bin/spec --shard`. `HOSTS` runs `sharp/bin/hosts --no-build`, with the Go modules and Go's build cache of FrankenPHP's packages that every push to `master` saves, so `HOSTS` runs on each such push. Their key holds the hash of the PHP headers the engine installs, because Go's build cache never reads the headers a C file includes, so a change to them restores nothing. They all run on one build: `BUILD_LINUX` builds and installs the engine through ccache, which `sharp/docker/run` passes `CC` and `CXX` into, and `mago` with every test binary on the Mago target, from the caches it saves on every push to `master`, and keeps the engine's CLI and install and `mago` as an artifact, which each suite unpacks and runs with `sharp/bin/test --no-build`. It also runs `sharp/bin/check generated`, the checks of generated files.

Every pull request's run also runs what needs the Mac and the runtime image: `MACOS` and `IMAGE`. No pull request's run builds the release. A tree runs the checks once: a run runs none of them when a pull request's run passed `CI_PASSED` on the same tree, the pull request merged with `master`, and links that run. A pull request's run then runs nothing else, so the pull request that lands a commit of `next` passes in about a minute. A push to `master` then runs only `BUILD_LINUX` and `HOSTS`, to save the caches every pull request's run restores. A push to `master` whose `sharp/mago/Cargo.toml` version has no tag on `origin` yet, as `sharp/bin/check --release` prints it, also builds the release in `RELEASE_ASSETS`, and `RELEASE` and `COMPOSER` publish what it built. A push whose tree no pull request's run passed runs every check, the Mac's among them, as the check of the merge. A failed push opens an issue, or comments on the one still open. The Mac job runs what only macOS can fail: the builds, `mago compile` and the suite on the debug engine without opcache and in shared memory on a disk that ignores case, `--census` and `--differential`, `sharp/mago/`'s workspace tests on the dev build, which keeps the debug assertions and overflow checks the release profile turns off, and `plain-php` on the dev build, which `release.yml`'s macOS build runs again on the release binary. No job runs the suite on arm64 Linux: `IMAGE`'s arm64 image shows that its engine starts and lists the `sharp` extension. `ASAN` runs alone, nightly on `master`'s tip, or on the branch `gh workflow run sharp.yml --ref <branch>` names: it builds the engine in the `sharp/docker/` image, as every Linux suite does, with AddressSanitizer, through the `CFLAGS` and `LDFLAGS` php-src's own CI sets, and runs `sharp/bin/test` with the environment `run-tests.php --asan` sets, so a memory error of the engine fails a test, and a failed run opens its own issue.

`IMAGE` builds the runtime image at `-O2` with `--enable-werror`, on an amd64 runner and on an arm64 one, pushes it by digest, scans it, and starts it on its own default command, as an app's container does, with a page where the app's code goes, which FrankenPHP must answer to `curl` on its default Caddyfile's `https://localhost`. It then runs `sharp/bin/php-extensions` from the pushed image's `frankenphp php-cli` on every extension the image ships beside PHP's defaults, which the job's `EXTENSIONS` lists, as [Extensions](#extensions) does, and `sharp/bin/php-extensions` holds a call for each, so an extension added to the image goes in both. `LINUX (2/2)` also fails when a generated file differs from the committed one, when `ext/sharp/sharp_unit.h` differs from the header `sharp/mago/` generates from `Zend/zend_ast.h`, or when `ext/sharp/Cargo.lock` or `sharp/mago/Cargo.lock` names a local `path+file://` or `git+file://` source.

`.github/workflows/mago.yml` runs Mago's own gates in `sharp/mago/` on every pull request, and every push run on `master` as `sharp.yml` runs them, whatever it changed, with the toolchain `sharp/mago/Cargo.toml`'s `rust-version` names, which `release.yml` builds the binaries with. Its `checks` job runs `sharp/bin/check mago-checks`: `typos`, the PHP SDK's tests, formatting, clippy, and that the fuzz crate's `Cargo.lock` is current, and builds the Linux `mago`, in the `test-run` profile `sharp/mago/Cargo.toml` explains, then analyzes the standard library in `sharp/composer/` with it and fails on any note. The workspace's tests run on Linux in `sharp.yml`, on the one build of Mago's dev target that `BUILD_LINUX` makes in the `sharp/docker/` image, which keeps the test binaries, `mago` and `build-mago`'s `build.json` as an artifact: `MAGO (1/5)` to `(5/5)` run them with `sharp/bin/test --mago --no-build --shard`, a fifth of the tests each, balanced by their seconds, and `BUILD_LINUX` runs the doc tests and the property tests, which cargo builds, with `sharp/bin/test --mago --cargo-built` after its uploads, so `CI_PASSED` covers them and the suites start about a minute after the uploads. The Mac job runs them on macOS. A run runs no test when a pull request's run passed `ci-passed` on the same tree, and a push to `master` runs `checks` again to save its target. A failed push to `master` opens an issue, or comments on the one still open. Each job that builds Mago restores the Rust crates and its target folder with `.github/actions/mago-cache`, which only a push to `master` saves: `sharp.yml`'s `BUILD_LINUX` for the dev target in the image, and `checks` here for its own. Every other run restores what `master` saved and saves nothing, because only its own ref's runs would restore it. That run's last job, `ci-passed` or `CI_PASSED`, then deletes with `.github/actions/mago-cache-prune` each target, ccache and Go cache a newer one of its kind replaced, so the caches hold one of each. The self-hosted Mac keeps its dev Mago target instead, and `.github/actions/clean-kept-build` removes it above 8 GB. When a pull request from this repository closes, `.github/workflows/closed-pull-request.yml` cancels each of its runs that hasn't ended, which would hold the Mac for a tree nothing lands, and deletes every cache its runs saved, which only its own runs could restore. A pull request from another repository gets a token that can do neither. Its `plain-php` job runs `sharp/bin/check plain-php` on Linux with the runner's own PHP, and the Mac job runs it on macOS. It installs `heyjordanparker/php-sharp-composer` from `sharp/composer/` and `heyjordanparker/mago-sharp` from `sharp/mago/`, with the `test-run` `mago` that the `checks` job built on Linux, or the dev `mago` on macOS, and the release `mago` in a release's macOS build, then runs `mago analyze` and `mago compile` on a one-class `.sharp` project. Last it installs `sharp/phpunit/` and runs its tests, which must stop with "This project needs the PHP# engine" and print none of `tests/Shop/CheckoutTest.sharp`. Its `ci-passed` job passes when all of them pass, or skipped a tree a pull request's run passed.

A commit message line such as `Completes: §6.1, §9` claims those sections are done, so the pull request that lands the commit checks it, a pull request from `land/<sha>` among them. The `COMPLETES` job in `.github/workflows/completes.yml` runs the base commit's `sharp/bin/spec --completes` on the merge commit with the messages of the pull request's commits whenever it is opened, edited or pushed to, and fails on a pull request of more than the 250 commits GitHub lists. Neither `COMPLETES` nor `SPEC_GUARD` runs on `next`'s own pull request into `master`, which never merges, and where a push failed both against a base older than `master`'s tip. A job its `if` skips passes a required check, so they skip only this repository's `next`, and every other pull request runs them. It fails on a `Completes:` line in any other form, such as `**Completes:** §6`, on a section with no checked example, and on a section `sharp/baseline/spec` still lists. `§9` covers `§9.1`.

`sharp.yml`'s `WORKFLOWS` job runs `sharp/bin/check workflows`, which fails when a `uses:` in `.github/workflows` or `.github/actions` names an action that is not ours at anything but a full 40-character commit SHA, when a Dockerfile under `sharp/docker` has a `# syntax=` line, whose frontend BuildKit pulls from Docker Hub with no login, and when `actionlint` finds a problem in any workflow. `.github/actionlint.yaml` names the labels of the self-hosted Mac runners, and actionlint runs shellcheck on every workflow.

`master` takes changes only through a pull request whose `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES` checks pass. A repository ruleset enforces it for every account, admins included, and refuses force pushes and deletion. `CI_PASSED` passes when `WORKFLOWS` passes, and every other job of the run passes, or skips: the checks skip only on a tree a pull request's run passed, and `RELEASE` and `COMPOSER` skip on a pull request. A red job on a push, publishing among them, fails `CI_PASSED` and opens the red-master issue.

`next` lands on `master` through `sharp/bin/land`, which the maintainer's tick runs every half hour, while newer pushes keep `next`'s own pull request waiting for a run of its newest push. Each tick first opens `next`'s own pull request when `next` is ahead of master and none is open. It takes the newest commit of `next` whose `PHP#` and `Mago` runs both passed and that `master` lacks, which holds every older one, and opens a pull request from branch `land/<sha>` at that commit. It closes every other open `land/` pull request with its branch, since that commit holds each older one. The first tick that finds the pull request's Spec guard run ended turns its auto-merge on with `--match-head-commit`, since the ruleset turns off an auto-merge that went on before the pull request's Spec guard run reported. Its runs skip every check, the Mac's among them, so it merges once auto-merge is on, and the repository deletes its branch. The tick runs it with the language owner's `gh` login, so the pull request starts the required runs, where one `GITHUB_TOKEN` opened would start none, and its merge is a push, so `master`'s push runs save the caches as after any merge. A change to `sharp/bin/spec` reaches `master` first in a pull request of its own, which `SPEC_GUARD` requires, and `next` takes it from `master`, so no landing carries it.

PHP# and Mago release together at one version, the `version` of `sharp/mago/Cargo.toml`'s root package, `mago`. The engine's build writes it into `sharp_version.h` as `PHP_SHARP_VERSION`, and every other crate of the workspace keeps version `0.0.0`, so a bump rebuilds only `mago`. [sharp/CHANGELOG.md](CHANGELOG.md) holds each release's notes, in the Keep a Changelog format, with what to change when upgrading first. A release is one commit on `next`, the bump: what `sharp/mago/scripts/set-version.php` writes, and the release's heading and compare links in `sharp/CHANGELOG.md`, as [docs/how-we-work.md](../docs/how-we-work.md) lists. The pull request runs that test it run the checks every run runs, and the push that lands it on `master` builds the release's assets, once: `RELEASE_ASSETS` calls `.github/workflows/release.yml`, which checks that `sharp/CHANGELOG.md` has the version's section, the GitHub release's notes, and builds `mago` for macOS on Apple silicon and for glibc Linux on arm64 and x86_64, the platforms PHP# runs on, each with its attestation, Mago's source archives and `mago-<version>-sha256sums.txt`, the SHA-256 of every other asset, and keeps them and the notes for the repository's artifact retention, 90 days. The macOS binary builds with profile-guided optimization, trained on `corpus/`. No pull request's run builds them, since it only has to prove the tree compiles and passes its checks on its test builds: while a bump waited on `next`, every run there built the profile-guided release, and run 44997846679 took 45 minutes, 16.5 of them the release build alone after its checks had ended. Each binary builds on a runner of its own platform, which checks it with `mago --version`, `mago analyze` and `mago compile` on a one-class `.sharp` project. `sharp.yml`'s `RELEASE` publishes them once every job of the push's run passed. It takes the runtime image the `IMAGE` jobs of the pull request's run that passed `CI_PASSED` on the pushed tree built by digest, or of its own run when no pull request's run passed that tree and it ran every check itself. It waits for `mago.yml`'s run of the same push to pass, checks each asset against its SHA-256 and its attestation, and that the commit the attestation names has the pushed commit's tree, and fails on any mismatch. It then publishes exactly those files as the GitHub release, which creates tag `v<version>` on the pushed commit. Pushes to `master` run one at a time, so the push after a release finds its tag and builds nothing. A tag `GITHUB_TOKEN` makes starts no workflow, and nobody tags by hand. `install.sh`, `mago self-update` and the Composer package `heyjordanparker/mago-sharp` download `mago` from these releases. `install.sh` and `mago self-update` verify the archive's attestation with `gh`, or else its SHA-256 against `mago-<version>-sha256sums.txt`, before they extract it. Once the release is public, `sharp.yml`'s `COMPOSER` job downloads every published asset and checks it against the SHA-256s the run wrote, then pushes the tag of `heyJordanParker/mago-sharp`: the split of `sharp/mago/`, plus a commit that adds the release's SHA-256s as `composer/sha256sums.txt`, and the Composer wrapper refuses an archive whose SHA-256 differs.

`RELEASE` also publishes the runtime image as `ghcr.io/heyjordanparker/php-sharp:<version>`, never as `latest`, for `linux/amd64` and `linux/arm64`. The `IMAGE` jobs of the run that passed the pushed tree built it: each platform builds on its own native runner with Buildx, both at once, and pushes by digest with BuildKit's provenance and SBOM attestations, and keeps the digest and the commit it built from. `RELEASE` checks that commit has the merge's tree, tags the two platforms' digests as one image and attests its provenance, which `gh attestation verify oci://ghcr.io/heyjordanparker/php-sharp:<tag> --repo heyJordanParker/php-sharp` checks. BuildKit, its SBOM scanner and the bases are `.github/workflows/base-images.yml`'s copies in ghcr.io, pulled after the login at the digests `sharp.yml` and the Dockerfiles pin, so the job makes no anonymous Docker Hub pull. CI sets the runtime Dockerfiles' `REGISTRY` to `ghcr.io/heyjordanparker`, and a local build keeps the public default at the same digest. `IMAGE` scans each platform's pushed image with Trivy on its own runner before `RELEASE` may tag it, and fails on a critical or high CVE that a fixed package resolves. It also starts the image on its own default command and requests a page from its server with `.github/actions/boot-image`, so the digests `RELEASE` tags are the ones that served a page and loaded the extension set. `base-images.yml` copies the bases every day, and when a copy has a new digest its `PIN` job pushes a branch named from the new digests, which moves the Dockerfiles' pins to them, and opens or updates one issue that names the old and new digests and links the branch's compare view. An Agent opens the pull request from that issue, so its CI runs: one that `GITHUB_TOKEN` opened would start no workflow, and letting GitHub Actions open pull requests would let every workflow's token approve them. The BuildKit pins in `sharp.yml` change by hand with their version, because `GITHUB_TOKEN` can't push a change to a workflow.

On a push to `master`, once every check and `RELEASE` passed or skipped, the `COMPOSER` job splits `sharp/composer/` with `splitsh-lite` and pushes the result to `master` of `heyJordanParker/php-sharp-composer`, and for a release to its tag `v<version>` too. It pushes with the split repository's deploy key, kept in the `PHP_SHARP_COMPOSER_DEPLOY_KEY` Actions secret. The split repository's webhook tells Packagist to update `heyjordanparker/php-sharp-composer`, so a tag here becomes a plugin release of the same version. The same job splits `sharp/mago/` and pushes it to `master` of `heyJordanParker/mago-sharp`, a read-only mirror, with the `MAGO_SHARP_DEPLOY_KEY` secret, so Packagist updates `heyjordanparker/mago-sharp`, and for a release pushes the mirror's tag with the release's SHA-256s. Neither push forces, and the branch push skips a branch the split doesn't contain, as [docs/architecture/mago.md](../docs/architecture/mago.md) says.

php-src's own workflows, their actions and scripts, and `.circleci/`, which test platforms PHP# doesn't run on, are deleted, and a merge from upstream keeps them deleted.
