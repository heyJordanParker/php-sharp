# PHP#

PHP# is a typed dialect of PHP with C#-shaped syntax. A `.sharp` file compiles to the same engine as a `.php` file, so the two call each other freely, and an app moves to PHP# one file at a time.

The checker, a fork of [Mago](https://github.com/heyJordanParker/mago-sharp), type-checks PHP and PHP# files together. The engine runs the same Mago front end, so both read every `.sharp` file the same way.

## Principles

**If it compiles, it's proven.**
An Agent knows it's wrong the instant it writes the mistake.
Precedent: Elm, Rust and Lean.

**Signatures tell the whole truth.**
Read the signature and you know everything the code does.
Precedent: Haskell and Rust.

**Everything is instant.**
Every tool answers instantly to any number of parallel Agents.
Precedent: Go and esbuild.

## Example

```csharp
// app/Store/Cart.sharp
namespace App.Store;

public class Cart
{
    public List<Line> lines { get; private set; } = [];

    public void add(Line line) { this.lines.add(line); }

    public int total() => this.lines.filter(l => !l.refunded).sumOf(l => l.amount);

    public string status() => match (this.lines.count()) {
        0 => "empty",
        default => "open",
    };
}
```

Plain PHP uses it like any other class:

```php
$cart = new App\Store\Cart();
$cart->add($line);
echo $cart->total();
```

## Language

- [docs/spec.md](docs/spec.md) states every rule of the language.
- [docs/decisions/](docs/decisions/) records why each language decision was made, with the options considered and the languages behind them.

## Build and test

Run these from the repository root:

```sh
sharp/bin/build             # build sharp/build/<os>-<arch>/sapi/cli/php
sharp/bin/test              # run the PHP# suite, Zend/tests/sharp/
sharp/bin/test --upstream   # run php-src's own suites against the upstream baseline
```

[sharp/README.md](sharp/README.md) lists every option, the macOS dependencies, the Linux image and CI.

## Runtime image

`sharp/docker/runtime/` builds docker-library's `php:8.5-fpm-trixie` image from this repository, with PHP# built in. An app switches to PHP# by changing only its `FROM` line. Each version tag, such as `v0.1.0`, publishes it as `ghcr.io/heyjordanparker/php-sharp:<version>`.

## Composer plugin

`sharp/composer/` is the Composer plugin `heyjordanparker/php-sharp-composer`. With it, Composer's autoloader finds `.sharp` files by the same PSR-4 rules as `.php` files.

## A fork of php-src

This repository is a fork of [php-src](https://github.com/php/php-src) and tracks upstream. `.php` files compile exactly as upstream compiles them. php-src's own README is kept in [docs/php-src.md](docs/php-src.md), and the repository keeps php-src's [LICENSE](LICENSE).
