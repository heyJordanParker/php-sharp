# PHP#

PHP# is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [docs/spec.md](../docs/spec.md), and the reason behind each language decision lives in [docs/decisions/](../docs/decisions/).

## Branches

- `master` is PHP#'s only line and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.11`, and all work lands on it directly.
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
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test --zts            # run it on the thread-safe build
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --opcache        # also run with the opcache file cache, priming it and then using it
sharp/bin/test --repeat         # also run each test twice in one process, the second time from opcache shared memory
sharp/bin/test --passes         # also run with each optimizer pass alone, then under the tracing and the function JIT
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

`sharp/bin/hosts` runs on Linux, so `--linux` runs it in the `sharp/docker/` image. It needs `sharp/bin/build`, `sharp/bin/test` and `sharp/bin/build --zts` first, for `php-fpm`, `mago` and the installed thread-safe engine. It builds FrankenPHP v1.13.0 with Go against that engine into `sharp/build/<os>-<arch>-zts/frankenphp/`, then requests the fixture's paths from php-fpm, through `cgi-fcgi`, and from one FrankenPHP worker: the lifetimes twice, `/types` 1,000 times, `/flood` past the type limit, `/words`, a type the program makes that no request made before, then `/types` and `/` again. Each host's output must equal `sharp/hosts/expected/<host>.txt`, where only the static's count differs. Four more workers then serve 400 requests, 32 at once, in one process.

`--installed` runs the suite on the engine a runtime image installs, the `php` on the `PATH`, inside that image. Every other mode runs the build's engine with no `php.ini`, so only `--installed` loads the PHP extensions the image's `conf.d` enables.

`sharp/bin/test` first compares the version the built engine prints in `php --ri sharp` with `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`, and refuses to run until `sharp/bin/build` rebuilds an engine that differs.

The engine runs only `.sharpc` files that `mago compile` wrote. So before any test runs, `sharp/bin/test` builds `mago` from `sharp/mago/` with cargo, into `sharp/build/<os>-<arch>/mago`, and runs `mago compile` from the repository root into its `.sharp/` folder. cargo rebuilds only what changed, so a run after a checker change tests that change. `Zend/tests/sharp/mago.toml` makes the `.sharp` fixtures, the plain PHP classes and functions they call, in `Zend/tests/sharp/harness/`, and the standard library in `sharp/composer/library/` one checker project. A fixture the checker refuses fails the run, because a refusal is Mago's to test. Tests that build their own project, such as the refusal tests, find `mago` in `TEST_MAGO_EXECUTABLE`.

`--differential` checks that every `.sharp` fixture `mago compile` accepted makes `php -l` print nothing but "No syntax errors detected", so a compile warning or deprecation also fails the run.

`--phpunit` installs `sharp/phpunit/`, a Composer project that requires `sharp/composer/` and the PHPUnit version its `composer.lock` pins, compiles it with the `mago` it built, and runs its tests with the engine under PHPUnit twice: in one process, and with `--process-isolation`. Its PHP# test class extends PHPUnit's `TestCase` and declares a test module, so the run fails when the test module does not apply in PHPUnit's process, in the child process of `#[RunInSeparateProcess]`, or before the test's own `Before` method. It needs `composer` on the path, which the `sharp/docker/` image installs.

`--passes` runs the suite once per bit of `opcache.optimization_level` from pass 1 to pass 16, each pass alone, and then under `opcache.jit=tracing` and `opcache.jit=function`. Every run expects the output of the run without opcache, so a pass that drops a PHP# mark fails on its own run instead of hiding behind the other passes. A test whose output depends on the whole optimizer, such as a count of oplines the JIT left to the VM, sets `opcache.optimization_level=0x7FFEBFFF` in its `--INI--` section.

`--census` runs `sharp/bin/census`. It reads the register of PHP# marks in `Zend/zend_compile.h` and fails on each line in `Zend/` and `ext/opcache/` that writes or compares a marked field of a marked opcode or AST kind without naming the mark, as the header of `sharp/bin/census` describes. A new mark goes in the register, beside the upstream flags of the same field and kind, with a `ZEND_STATIC_ASSERT` that fails the build when they overlap.

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux --opcache`.

Every PHP# feature must pass `sharp/bin/test --opcache --repeat --passes --census --spec --differential --phpunit`. `sharp/bin/test` disables the JIT outside `--passes`.

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global`, `sharp/docker/Dockerfile`, `sharp/bin/build` or `Zend/zend_modules.h` changes. `configure` reads `ZEND_MODULE_API_NO` from `Zend/zend_modules.h` into the extension directory.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --upstream` compares the run without opcache with `sharp/baseline/darwin` or `sharp/baseline/linux`, and fails on any difference. Each file lists every test that did not pass on the untouched `php-8.5.11` build, as `STATUS<TAB>path`. An upstream FAIL, BORK or LEAK fails the run before any comparison, so the baseline only ever holds skips and expected failures. A changed result is a regression until the Architect accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## Spec examples

`--spec` runs `sharp/bin/spec --self-test`, which checks one example for each of the checker's own rules in `sharp/spec-self-test.md`, then `sharp/bin/spec`. It compiles every checked code block in `docs/spec.md` and `README.md` with the `mago` built from `sharp/mago/`, runs the lines that claim a value or a throw on the engine, and compares each annotated line with its annotation. An HTML comment above a block, such as `<!-- example map-reads: statements alone -->`, names the example and says how to build it, as [docs/architecture/spec-examples.md](../docs/architecture/spec-examples.md) describes. Every example compiles against the classes and plain PHP stubs in `sharp/harness/`. A law example (section 28) needs Lean, so the `sharp/docker/` image installs Debian's `elan`, which downloads the Lean version mago pins into `sharp/build/elan` the first time a law compiles. mago's own cache folder sits in `sharp/build/cache` there.

A `compile error` line needs the error its rule gives: a "not supported yet" refusal, a missing class the line doesn't name itself, or an unreadable line never satisfies it, and an error moves to it from another line only when it is one of the whole-construct errors in `MOVES` and its line's brackets enclose it. A value or `throws` annotation that no run reaches fails the check, unless a hidden `<!-- run <id> -->` comment after the example's last block holds a run line that makes the same claim.

`sharp/baseline/spec/` lists every example that fails today, one empty file `<section>/<id>` each, so pull requests that fix different examples never conflict. Its count is how far PHP# is from v1. The run fails when an unlisted example fails or a listed one passes, and prints the `git rm`, `mkdir -p` and `touch` commands that make the folder match. The list records which examples fail, not how, so a listed example may change its errors.

On a pull request, the `SPEC_GUARD` job runs the base commit's `sharp/bin/spec --base <commit>`, from the base branch's `.github/workflows/completes.yml` on `pull_request_target`, so a pull request never runs or changes its own comparison. A feature pull request changes the spec, its examples and `sharp/baseline/spec` together with its code. `GUARDED` in `sharp/bin/spec` names what measures the examples, `sharp/bin/spec` itself, and a pull request that changes any other file leaves it exactly as the base has it. Markdown never runs, so a Markdown file may change beside it. A pull request that adds or changes a file in `sharp/harness/` lists it in its body, and its review checks that no stub makes an example pass falsely. The guard lists changed files with `--no-renames`, so a renamed guarded file shows its old path, and it fails on a symbolic link at a file it reads or guards, naming the path only. The fingerprint covers the whole example but its id: its section, and every block's marker line, setup, fence language, code and run comment. An example the base has with the same fingerprint, under its id or another, never joins the list, except that a renamed example takes over its old id's entry. An `ignore` on code the base checked counts as an entry.

## Runtime images

`sharp/docker/runtime/Dockerfile` holds docker-library's `php:8.5-fpm-trixie` recipe, built from this repository's source instead of the php.net tarball, with `ext/sharp` built in. It keeps docker-library's layout, so `docker-php-ext-*` and `install-php-extensions` work unchanged and an app switches to PHP# by changing only its `FROM` line. The `docker-php-*` scripts are verbatim copies. Compare them and the Dockerfile with docker-library's `8.5/trixie/fpm` on every merge from upstream.

`sharp/docker/runtime/frankenphp.Dockerfile` builds the engine with docker-library's `8.5/trixie/zts` recipe, then runs FrankenPHP v1.13.0's own Dockerfile stages on it, as FrankenPHP's images build on `php:8.5-zts-trixie`. Go 1.27, `e-dant/watcher` 0.14.5 and `install-php-extensions` 2.12.0 are pinned where FrankenPHP takes the latest. Compare it with docker-library's `8.5/trixie/zts` and FrankenPHP's `Dockerfile` on every merge from either.

The build context is the git tree, the same one CI builds from:

```sh
git archive HEAD | docker build -f sharp/docker/runtime/Dockerfile -t php-sharp -
git archive HEAD | docker build -f sharp/docker/runtime/frankenphp.Dockerfile -t php-sharp:frankenphp -
```

## Composer

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. Composer's autoloader then tries `.sharp` after `.php` by the PSR-4 rules, the way it tries `.hh` on HHVM. Composer's class map scanner reads only `<?php` files, so on `composer dump-autoload --optimize` the plugin writes every `.sharp` file under a PSR-4 folder to `vendor/composer/autoload_sharp.php`, and the autoloader reads it. The package's autoloader includes every `.sharp` class itself, never Composer's, so on plain PHP the first one stops with "This project needs the PHP# engine" before its source can reach the output.

`sharp/composer/library/Sharp/Builtins.sharp` gives each PHP built-in function its effect, as `extern global.fopen uses Files;`. `sharp/bin/builtins` writes it from PHPStan's php-8-stubs, at the version and SHA-256 it pins, for every PHP 8.5 function in PHP's root namespace that Mago's prelude in `sharp/mago/crates/prelude/` declares. `RULES` in the script maps each function to its effects, or to none, by its extension and name, and the run fails on a function no rule covers. The file also declares pure each PHP 8.5 exception class the prelude declares, as `extern ValueError;`: `Exception`, `Error` and every class whose parents reach one of them. `sharp/bin/builtins --self-test` checks that each rule gives the effect of its row in `ROWS`, and that removing the rule changes that row. The Linux job runs both and fails when the file differs from the committed one.

`sharp/composer/` is the plugin's source of truth. Composer installs it from `heyJordanParker/php-sharp-composer`, a read-only split that CI rewrites from this folder's history, the way Symfony splits its components. Change the plugin here, never in the split.

## macOS dependencies

```sh
brew install autoconf bison curl re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## CI

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on `macos-latest`, for every push and pull request to `master` that changes more than documentation. The Linux job runs `sharp/bin/test --phpunit` too, so a PHP# test class runs under real PHPUnit on every change. `LINUX_ZTS` builds with `--zts` and runs `--opcache --repeat --passes`, the JIT included. `HOSTS` runs `sharp/bin/hosts` with the `php-fpm` and `mago` `LINUX` built and the engine `LINUX_ZTS` installed, which each keeps as a tar artifact, so it builds no engine. `PHP_EXTENSIONS` builds each runtime image, installs the extensions a typical Laravel app installs on top with `install-php-extensions`, as such an app's Dockerfile does, and runs `sharp/bin/php-extensions` on them, from `php` and from `frankenphp php-cli`. It then runs `sharp/bin/test --installed --opcache --repeat --passes` inside the image, so an extension that hooks the engine fails the PHP# suite. The list sits in the job's `EXTENSIONS`, and `sharp/bin/php-extensions` holds a call for each. `LINUX` also fails when a generated file differs from the committed one, when `ext/sharp/sharp_unit.h` differs from the header `sharp/mago/` generates from `Zend/zend_ast.h`, or when `ext/sharp/Cargo.lock` or `sharp/mago/Cargo.lock` names a local `path+file://` or `git+file://` source.

`.github/workflows/mago.yml` runs Mago's own gates in `sharp/mago/` on every push and pull request: formatting, clippy and the workspace tests on Linux with stable and nightly Rust and on macOS with stable Rust, a release build, `typos`, and patch coverage. Its `plain-php` job runs on `ubuntu-latest` and `macos-latest` with the runner's own PHP. It installs `heyjordanparker/php-sharp-composer` from `sharp/composer/` and `heyjordanparker/mago-sharp` from `sharp/mago/`, with the `mago` that job built, then runs `mago analyze` and `mago compile` on a one-class `.sharp` project. Its `ci-passed` job passes when all of them pass.

`.github/workflows/mutants.yml` runs `cargo mutants` on the Mago code that changed, on each push to `master` and nightly for the last day's changes, never on a pull request. The generated-file step copies `.github/actions/verify-generated-files/action.yml`, so compare the two on every merge from upstream.

A pull request body line such as `Completes: §6.1, §9` claims those sections are done. The `COMPLETES` job in `.github/workflows/completes.yml` runs the base commit's `sharp/bin/spec --completes` on the merge commit whenever the body is opened, edited or pushed to. It fails on a `Completes:` line in any other form, such as `**Completes:** §6`, on a section with no checked example, and on a section `sharp/baseline/spec` still lists. `§9` covers `§9.1`.

`master` takes changes only through a pull request whose `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES` checks pass. A repository ruleset enforces it for every account, admins included, and refuses force pushes and deletion. `CI_PASSED` passes when `LINUX`, `LINUX_ZTS`, `HOSTS`, `PHP_EXTENSIONS` and `MACOS` pass, or skip on a pull request that changes documentation alone.

PHP# and Mago release together at one version. `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h` and the `version` in `sharp/mago/Cargo.toml` must match. Pushing a full version tag, such as `v0.4.0`, runs `.github/workflows/release.yml`. It fails unless both versions equal the tag, runs Mago's gates, builds `mago` for each platform, and publishes the archives, their attestations and Mago's source archives as the tag's GitHub release. `install.sh`, `mago self-update` and the Composer package `heyjordanparker/mago-sharp` download `mago` from these releases.

The same tag runs `LINUX`, `LINUX_ZTS`, `HOSTS`, `PHP_EXTENSIONS` and `MACOS`, and when they pass, the `IMAGE` job publishes both runtime images, php-fpm's as `ghcr.io/heyjordanparker/php-sharp:<version>` and FrankenPHP's as `ghcr.io/heyjordanparker/php-sharp:<version>-frankenphp`, never as `latest`, for `linux/amd64` and `linux/arm64`. Each platform builds on its own native runner through `docker/github-builder`.

On a push to `master` or a tag, once `LINUX` and `MACOS` pass or skip a change to documentation alone, the `COMPOSER` job splits `sharp/composer/` with `splitsh-lite` and pushes the result to the same branch or tag of `heyJordanParker/php-sharp-composer`. It pushes with the split repository's deploy key, kept in the `PHP_SHARP_COMPOSER_DEPLOY_KEY` Actions secret. The split repository's webhook tells Packagist to update `heyjordanparker/php-sharp-composer`, so a tag here becomes a plugin release of the same version. The same job splits `sharp/mago/` and pushes it to the same branch or tag of `heyJordanParker/mago-sharp`, a read-only mirror, with the `MAGO_SHARP_DEPLOY_KEY` secret, so Packagist updates `heyjordanparker/mago-sharp`. It never forces, and it skips a branch the split doesn't contain, as [docs/architecture/mago.md](../docs/architecture/mago.md) says.

Upstream's `Test` and `Windows builds` workflows are disabled on the fork with `gh workflow disable`.
