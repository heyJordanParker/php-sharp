# PHP#

PHP# is Dent's own PHP. It is a fork of php-src that keeps running plain PHP and adds a second dialect, PHP#, with C#-shaped syntax and compile-time type safety. A PHP# file compiles to the same engine as plain PHP, so the two call each other freely.

The approved language rules live in [spec/language.md](spec/language.md).

## Branches

- `sharp` is the development branch and the default branch of `heyJordanParker/php-sharp`. It starts at the upstream tag `php-8.5.11`.
- Upstream branches such as `master` and `PHP-8.5` stay untouched.
- Moving to a newer 8.5.x release means rebasing `sharp` onto that release's tag.
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

`sharp/bin/build` reruns `buildconf` and `configure` by itself when `configure.ac`, a `*.m4` file, a `Makefile.frag`, `build/Makefile.global` or `sharp/bin/build` changes.

Run one build per tree at a time. Parallel Agents each work in their own git worktree.

## Upstream baseline

`sharp/bin/test --upstream` compares the run without opcache with `sharp/baseline/darwin` or `sharp/baseline/linux`, and fails on any difference. Each file lists every test that did not pass on the untouched `php-8.5.11` build, as `STATUS<TAB>path`. A changed result is a regression until someone accepts it by committing the regenerated file. The failing run prints the `cp` command that does this.

## macOS dependencies

```sh
brew install autoconf bison re2c pkgconf icu4c libiconv libpq libsodium libzip oniguruma
```

## CI

`.github/workflows/sharp.yml` builds and tests on Linux in the `sharp/docker/` image and on `macos-latest`, for every push and pull request to `sharp` that changes more than documentation. The Linux job also fails when a generated file differs from the committed one.

Upstream's `Test` and `Windows builds` workflows are disabled on the fork with `gh workflow disable`.
