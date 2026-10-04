# PHP#

PHP# is Dent's own PHP. It is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [spec/language.md](spec/language.md).

## Branches

- `sharp` is the development branch and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.11`.
- Upstream branches such as `master` and `PHP-8.5` stay untouched.
- Moving to a newer 8.5.x release means rebasing `sharp` onto that release's tag.
- Everything PHP# owns outside the engine sources lives under `sharp/`, so a rebase only meets conflicts in engine files a feature had to change.
- PHP# tests live in `Zend/tests/sharp/`, next to the engine tests.

## Commands

Run every command from the repository root.

```sh
sharp/bin/build                 # incremental build of sharp/build/<os>-<arch>/sapi/cli/php
sharp/bin/build --clean         # rebuild from scratch
sharp/bin/test                  # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test Zend/tests/foo   # run these .phpt files or directories instead
sharp/bin/test --opcache        # run without opcache, with opcache, and from the opcache file cache
sharp/bin/test --upstream       # run Zend/tests, ext/reflection, ext/tokenizer and ext/opcache
```

`--linux` runs either command inside the Debian trixie image from `sharp/docker/`, for example `sharp/bin/build --linux` and `sharp/bin/test --linux --opcache`.

Every PHP# feature must pass `sharp/bin/test --opcache`. `sharp/bin/test --upstream` must match [baseline.md](baseline.md).

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file or `sharp/bin/build` changes.

## macOS dependencies

```sh
brew install autoconf bison re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## CI

`.github/workflows/sharp.yml` builds and tests on `ubuntu-latest` and `macos-latest` for every push and pull request to `sharp`.
