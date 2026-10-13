# PHP#

PHP# is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [docs/spec.md](../docs/spec.md), and the reason behind each language decision lives in [docs/decisions/](../docs/decisions/).

## Branches

- `master` is PHP#'s only line and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.11`, and all work lands on it directly.
- Upstream's other branches, such as `PHP-8.5`, are read-only mirrors. Upstream's own `master` is reached through the `upstream` remote.
- Upstream is merged into `master`, never rebased. Moving to a newer 8.5.x release means merging that release's tag.
- PHP# owns `sharp/`, `ext/sharp/`, `Zend/tests/sharp/`, `.github/workflows/sharp.yml`, `.github/workflows/completes.yml`, `.github/SECURITY.md`, `.github/ISSUE_TEMPLATE/`, `README.md`, `docs/spec.md`, `docs/decisions/` and `docs/php-src.md`. Everything else is an engine file.
- A merge from upstream meets conflicts only in engine files a feature had to change, and in the upstream files PHP# replaced: `README.md` and the forms in `.github/ISSUE_TEMPLATE/`. Each of those keeps PHP#'s side.

## Commands

Run every command from the repository root.

```sh
sharp/bin/build                 # incremental build of sharp/build/<os>-<arch>/sapi/cli/php
sharp/bin/build --clean         # rebuild from scratch
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --opcache        # also run with the opcache file cache, priming it and then using it
sharp/bin/test --repeat         # also run each test twice in one process, the second time from opcache shared memory
sharp/bin/test --passes         # also run with each optimizer pass alone, then under the tracing and the function JIT
sharp/bin/test --census         # also check every write and comparison of a field that holds a PHP# mark
sharp/bin/test --spec           # also compile and run every code example in docs/spec.md and README.md
sharp/bin/test --differential   # also check that php -l compiles every Zend/tests/sharp/*.sharp file
sharp/bin/test --upstream       # run Zend/tests, ext/reflection, ext/tokenizer and ext/opcache
```

`sharp/bin/test` first compares the version and Mago commit the built engine prints in `php --ri sharp` with `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h` and `SHARP_MAGO_COMMIT` in `ext/sharp/sharp_unit.h`, and refuses to run until `sharp/bin/build` rebuilds an engine that differs.

The engine runs only `.sharpc` files that `mago compile` wrote. So before any test runs, `sharp/bin/test` installs `mago` from the Mago commit that `SHARP_MAGO_COMMIT` in `ext/sharp/sharp_unit.h` names, under `sharp/build/<os>-<arch>/mago`, and runs `mago compile` from the repository root into its `.sharp/` folder. `Zend/tests/sharp/mago.toml` makes the `.sharp` fixtures, the plain PHP classes and functions they call, in `Zend/tests/sharp/harness/`, and the standard library in `sharp/composer/library/` one checker project. A fixture the checker refuses fails the run, because a refusal is Mago's to test. Tests that build their own project, such as the refusal tests, find `mago` in `TEST_MAGO_EXECUTABLE`.

`--differential` checks that every `.sharp` fixture `mago compile` accepted makes `php -l` print nothing but "No syntax errors detected", so a compile warning or deprecation also fails the run.

`--passes` runs the suite once per bit of `opcache.optimization_level` from pass 1 to pass 16, each pass alone, and then under `opcache.jit=tracing` and `opcache.jit=function`. Every run expects the output of the run without opcache, so a pass that drops a PHP# mark fails on its own run instead of hiding behind the other passes. A test whose output depends on the whole optimizer, such as a count of oplines the JIT left to the VM, sets `opcache.optimization_level=0x7FFEBFFF` in its `--INI--` section.

`--census` runs `sharp/bin/census`. It reads the register of PHP# marks in `Zend/zend_compile.h` and fails on each line in `Zend/` and `ext/opcache/` that writes or compares a marked field of a marked opcode or AST kind without naming the mark, as the header of `sharp/bin/census` describes. A new mark goes in the register, beside the upstream flags of the same field and kind, with a `ZEND_STATIC_ASSERT` that fails the build when they overlap.

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux --opcache`.

Every PHP# feature must pass `sharp/bin/test --opcache --repeat --passes --census --spec --differential`. `sharp/bin/test` disables the JIT outside `--passes`.

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global`, `sharp/docker/Dockerfile`, `sharp/bin/build` or `Zend/zend_modules.h` changes. `configure` reads `ZEND_MODULE_API_NO` from `Zend/zend_modules.h` into the extension directory.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --upstream` compares the run without opcache with `sharp/baseline/darwin` or `sharp/baseline/linux`, and fails on any difference. Each file lists every test that did not pass on the untouched `php-8.5.11` build, as `STATUS<TAB>path`. An upstream FAIL, BORK or LEAK fails the run before any comparison, so the baseline only ever holds skips and expected failures. A changed result is a regression until the Architect accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## Spec examples

`--spec` runs `sharp/bin/spec --self-test`, which checks one example for each of the checker's own rules in `sharp/spec-self-test.md`, then `sharp/bin/spec`. It compiles every checked code block in `docs/spec.md` and `README.md` with the pinned `mago`, runs the lines that claim a value or a throw on the engine, and compares each annotated line with its annotation. An HTML comment above a block, such as `<!-- example map-reads: statements alone -->`, names the example and says how to build it, as [docs/architecture/spec-examples.md](../docs/architecture/spec-examples.md) describes. Every example compiles against the classes and plain PHP stubs in `sharp/harness/`. A law example (section 28) needs Lean, so the `sharp/docker/` image installs Debian's `elan`, which downloads the Lean version mago pins into `sharp/build/elan` the first time a law compiles. mago's own cache folder sits in `sharp/build/cache` there.

A `compile error` line needs the error its rule gives: a "not supported yet" refusal, a missing class the line doesn't name itself, or an unreadable line never satisfies it, and an error moves to it from another line only when it is one of the whole-construct errors in `MOVES` and its line's brackets enclose it. A value or `throws` annotation that no run reaches fails the check, unless a hidden `<!-- run <id> -->` comment after the example's last block holds a run line that makes the same claim.

`sharp/baseline/spec` lists every example that fails today, one `section<TAB>id` line each. Its length is how far PHP# is from v1. The run fails when an unlisted example fails or a listed one passes, and prints the `cp` command that writes the new list. The list records which examples fail, not how, so a listed example may change its errors.

On a pull request, the `SPEC_GUARD` job runs the base commit's `sharp/bin/spec --base <commit>`, from the base branch's `.github/workflows/completes.yml` on `pull_request_target`, so a pull request never runs or changes its own comparison. The spec files are `docs/spec.md`, `README.md`, `sharp/baseline/spec`, and every file in the `GUARDED` folders, which hold what measures the examples and everything that runs that measurement: `sharp/harness/`, `sharp/bin/`, `sharp/docker/` and `.github/`. A pull request that changes any other file leaves every example's fingerprint and every guarded file exactly as the base has them, so it can only remove entries. Markdown never runs, so a Markdown file outside the guarded folders may change beside a guarded file. The guard lists changed files with `--no-renames`, so a renamed guarded file shows its old path, and it fails on a symbolic link at a file it reads or in a guarded folder, naming the path only. The fingerprint covers the whole example but its id: its section, and every block's marker line, setup, fence language, code and run comment. An entry joins the list only in a pull request that changes nothing but `docs/spec.md`, `README.md` and `sharp/baseline/spec`. In a documents-only pull request, an example the base has with the same fingerprint, under its id or another, never joins the list, except that a renamed example takes over its old id's entry. An `ignore` on code the base checked counts as an entry.

## Runtime image

`sharp/docker/runtime/` holds docker-library's `php:8.5-fpm-trixie` recipe, built from this repository's source instead of the php.net tarball, with `ext/sharp` built in. It keeps docker-library's layout, so `docker-php-ext-*` and `install-php-extensions` work unchanged and an app switches to PHP# by changing only its `FROM` line. The `docker-php-*` scripts are verbatim copies. Compare them and the Dockerfile with docker-library's `8.5/trixie/fpm` on every merge from upstream.

The build context is the git tree, the same one CI builds from:

```sh
git archive HEAD | docker build -f sharp/docker/runtime/Dockerfile -t php-sharp -
```

## Composer

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. Composer's autoloader then tries `.sharp` after `.php` by the PSR-4 rules, the way it tries `.hh` on HHVM. Composer's class map scanner reads only `<?php` files, so on `composer dump-autoload --optimize` the plugin writes every `.sharp` file under a PSR-4 folder to `vendor/composer/autoload_sharp.php`, and the autoloader reads it. The package's autoloader includes every `.sharp` class itself, never Composer's, so on plain PHP the first one stops with "This project needs the PHP# engine" before its source can reach the output.

`sharp/composer/` is the plugin's source of truth. Composer installs it from `heyJordanParker/php-sharp-composer`, a read-only split that CI rewrites from this folder's history, the way Symfony splits its components. Change the plugin here, never in the split.

## macOS dependencies

```sh
brew install autoconf bison re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## CI

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on `macos-latest`, for every push and pull request to `master` that changes more than documentation. The `PLAIN_PHP` job runs on `ubuntu-24.04` and `macos-latest` with the runner's own PHP. It installs `heyjordanparker/php-sharp-composer` from `sharp/composer/` and `heyjordanparker/mago-sharp` from Packagist, then runs `mago analyze` and `mago compile` on a one-class `.sharp` project. The Linux job also fails when a generated file differs from the committed one, when `ext/sharp/sharp_unit.h` differs from the header the Mago commit it names generates from `Zend/zend_ast.h`, or when `ext/sharp/Cargo.lock` names a local `path+file://` or `git+file://` source. The generated-file step copies `.github/actions/verify-generated-files/action.yml`, so compare the two on every merge from upstream.

A pull request body line such as `Completes: §6.1, §9` claims those sections are done. The `COMPLETES` job in `.github/workflows/completes.yml` runs the base commit's `sharp/bin/spec --completes` on the merge commit whenever the body is opened, edited or pushed to. It fails on a `Completes:` line in any other form, such as `**Completes:** §6`, on a section with no checked example, and on a section `sharp/baseline/spec` still lists. `§9` covers `§9.1`.

`master` takes changes only through a pull request whose `CI_PASSED` check passes. A repository ruleset enforces it for every account, admins included, and refuses force pushes and deletion. `CI_PASSED` passes when `LINUX`, `MACOS` and `PLAIN_PHP` pass, or skip on a pull request that changes documentation alone.

Pushing a full version tag, such as `v0.1.0`, runs both jobs, and when they pass, the `IMAGE` job publishes the runtime image as `ghcr.io/heyjordanparker/php-sharp:<version>`, never as `latest`, for `linux/amd64` and `linux/arm64`. Each platform builds on its own native runner through `docker/github-builder`.

On a push to `master` or a tag, once `LINUX`, `MACOS` and `PLAIN_PHP` pass or skip a change to documentation alone, the `COMPOSER` job splits `sharp/composer/` with `splitsh-lite` and pushes the result to the same branch or tag of `heyJordanParker/php-sharp-composer`. It pushes with the split repository's deploy key, kept in the `PHP_SHARP_COMPOSER_DEPLOY_KEY` Actions secret. The split repository's webhook tells Packagist to update `heyjordanparker/php-sharp-composer`, so a tag here becomes a plugin release of the same version.

Upstream's `Test` and `Windows builds` workflows are disabled on the fork with `gh workflow disable`.
