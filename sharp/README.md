# PHP#

PHP# is Dent's own PHP. It is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [spec/language.md](spec/language.md).

## Branches

- `master` is PHP#'s only line and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.11`, and all work lands on it directly.
- Upstream's other branches, such as `PHP-8.5`, are read-only mirrors. Upstream's own `master` is reached through the `upstream` remote.
- Moving to a newer 8.5.x release means rebasing `master` onto that release's tag.
- PHP# owns `sharp/`, `Zend/tests/sharp/` and `.github/workflows/sharp.yml`. Everything else is an engine file, and a rebase only meets conflicts in engine files a feature had to change.

## Commands

Run every command from the repository root.

```sh
sharp/bin/build                 # incremental build of sharp/build/<os>-<arch>/sapi/cli/php
sharp/bin/build --clean         # rebuild from scratch
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --opcache        # also run with the opcache file cache, priming it and then using it
sharp/bin/test --upstream       # run Zend/tests, ext/reflection, ext/tokenizer and ext/opcache
```

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux --opcache`.

Every PHP# feature must pass `sharp/bin/test --opcache`. `sharp/bin/test` disables the JIT.

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global`, `sharp/docker/Dockerfile` or `sharp/bin/build` changes.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --upstream` compares the run without opcache with `sharp/baseline/darwin` or `sharp/baseline/linux`, and fails on any difference. Each file lists every test that did not pass on the untouched `php-8.5.11` build, as `STATUS<TAB>path`. An upstream FAIL, BORK or LEAK fails the run before any comparison, so the baseline only ever holds skips and expected failures. A changed result is a regression until the Architect accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## Runtime image

`sharp/docker/runtime/` holds docker-library's `php:8.5-fpm-trixie` recipe, built from this repository's source instead of the php.net tarball, with `ext/sharp` built in. It keeps docker-library's layout, so `docker-php-ext-*` and `install-php-extensions` work unchanged and an app switches to PHP# by changing only its `FROM` line. The `docker-php-*` scripts are verbatim copies. Compare them and the Dockerfile with docker-library's `8.5/trixie/fpm` on every rebase.

The build context is the git tree, the same one CI builds from:

```sh
git archive HEAD | docker build -f sharp/docker/runtime/Dockerfile -t php-sharp -
```

## Composer

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. Composer's autoloader then tries `.sharp` after `.php` by the PSR-4 rules, the way it tries `.hh` on HHVM. Composer's class map scanner reads only `<?php` files, so on `composer dump-autoload --optimize` the plugin writes every `.sharp` file under a PSR-4 folder to `vendor/composer/autoload_sharp.php`, and the autoloader adds it to the class map.

## macOS dependencies

```sh
brew install autoconf bison re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## CI

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on `macos-latest`, for every push and pull request to `master` that changes more than documentation. The Linux job also fails when a generated file differs from the committed one. That step copies `.github/actions/verify-generated-files/action.yml`, so compare the two on every rebase.

Pushing a `v*` tag runs both jobs, and when they pass, the `IMAGE` job publishes the runtime image as `ghcr.io/heyjordanparker/php-sharp:<version>` for `linux/amd64` and `linux/arm64`. Each platform builds on its own native runner through `docker/github-builder`.

Upstream's `Test` and `Windows builds` workflows are disabled on the fork with `gh workflow disable`.
