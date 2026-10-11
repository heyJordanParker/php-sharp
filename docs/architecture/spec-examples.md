# Spec examples

`docs/spec.md` is the definition of PHP# v1, and `README.md` shows its first example. Every rule comes with code annotated `// compiles`, `// compile error: …`, `// throws …` or the value a line gives. `sharp/bin/spec` compiles every one of those blocks with the pinned `mago`, runs the ones that claim a runtime result on the engine, and compares each annotated line with its annotation.

The examples that fail today are listed in `sharp/baseline/spec`. Its length is the v1 meter: v1 is done when the file is empty. CI keeps the list honest and lets it only shrink, except in a pull request that changes the spec and nothing else.

## Precedents

- **Rust's doctests** compile every block in the documentation. Hidden lines starting with `# ` supply setup the reader doesn't need, and `compile_fail`, `should_panic` and `ignore` on the fence say what the block must do.
- **Go's testable examples** run a function and compare what it prints with its `// Output:` comment.
- **Python's doctest** runs each `>>>` line and compares its value with the line below. The `ELLIPSIS` option lets `...` match any text.

PHP#'s spec takes from each:

- **From Rust:** every block compiles, and hidden setup lives with the example. Markdown can't hide lines inside a block, so the setup goes in an HTML comment on the line before it, which GitHub doesn't render.
- **From Go and Python:** the expected result sits on the line it describes. The spec already writes it there, as `// compile error: …` or `// 3`, so the check reads the spec's own annotations. It adds none.
- **From Python:** `…` in an expected value matches any text, as `ELLIPSIS` does.

## The marker

Every `csharp`, `ts` and `php` block in `docs/spec.md` and `README.md` has a marker on the line before its fence. A block without one fails the check, so a new example can't skip it. `text`, `lean` and `sh` blocks show messages, proofs and commands, and are not checked. Any other fence language fails the check.

```text
<!-- example <id>[: <shape>][ in <Class>][ alone[ <lines>]]
<setup lines>
-->
```

- **`<id>`** names the example, in kebab case, and is unique across both files. It never changes when the text around the block moves. The section is the heading the block sits under.
- **`<shape>`** says how the block becomes a file:
  - none: the block is a whole `.sharp` or `.php` file.
  - `members`: the block's lines go in the body of a class.
  - `statements`: the block's lines go in the body of a method of a class.
- **`in <Class>`** names that class. It is `Example` otherwise. A constructor needs its class's name, as in `public StoreService(…)`.
- **`alone`** checks each line that has a trailing comment by itself, together with the block's other lines. A block that lists alternatives, such as `int price = …;` four times, needs it. `alone 3-4` names the lines, counted from the block's first line, when a commented line is not an alternative.
- **Setup lines** are compiled with the block and never rendered. They are PHP# for a PHP# block and PHP for a PHP block.
- **Two blocks with the same `<id>`** are one example with two files, such as a plain PHP caller and the PHP# class it calls. The blocks must be next to each other.
- **A template** replaces the shape when none fits. It is the setup with a line holding only `…`, where the block goes. The block's namespace, import and type declaration lines still go to the head of the file, and every other line goes in the `…` slot.
- **`<!-- example <id>: ignore <reason> -->`** skips a block that is not complete code by design. Only two blocks have it: `naming-values` in §16 and `native-body` in §29. Adding `ignore` to an example that had none counts as adding an entry to the list.

### How a fragment becomes a file

`sharp/bin/spec` reads the block's top-level lines, the ones outside any braces the block opens:

- `namespace` and `import` lines go to the head of the file.
- A type declaration, a line with `class`, `struct`, `enum`, `interface`, `event` or `extern` after its modifiers, stays at file level with its body.
- Under `statements`, a line that starts with an access modifier or `static`, or a method signature with no modifier such as `void reserve(string sku, int count) { … }`, is a member of the example class, and every other line runs in its `run` method.
- Under `members`, every other line is a member of the example class.

A line goes where the line that starts its construct goes. A construct continues on every line inside brackets the construct opened, on a line that starts with `{` or `:`, and after an attribute line. A template string open across lines counts as an open bracket.

A file with no `namespace` line gets `namespace App;`, the namespace of the shared setup classes, so the block's names resolve with no imports, as the reader assumes.

The spec writes `…` for code the reader doesn't need. `sharp/bin/spec` replaces it as Kotlin's `TODO()` and Rust's `todo!()` stand in for a missing body:

- `{ … }` after a type's header, or after `if`, `else`, `while`, `for`, `do`, `try`, `catch` or `finally`, becomes `{ }`, so a run passes through it.
- `{ … }` anywhere else becomes `{ throw new Elided(); }`.
- `(…)` becomes `()`.
- `match (x) { … }` becomes `match (x) { default => throw new Elided() }`.
- `Plan plan = …;`, a declaration with its type written, becomes `Plan plan = Elided.value() as Plan ?? throw new Elided();`. PHP# refuses to assign a `throw`, whose type is `never`.
- A line holding only `…` is dropped.
- Any other `…` becomes `throw new Elided()`.

A line that ends a statement with no `;`, such as `7 / 2`, gets one, and a method signature with no body, such as `public void tag(string label = null)`, gets `{ … }`. A top-level statement that is a bare expression, such as `order == sameOrderLoadedAgain;`, runs as the argument of the call that records values, because the spec writes it to show what it gives, and PHP# refuses a statement with no effect. Each rewrite keeps the line on its own line, so every line number the checker reports is the spec's.

### Five blocks, five shapes

A whole class, the README's `Cart`, with its plain PHP caller as the same example. `Line` comes from the shared setup:

````diff
+<!-- example readme-cart -->
 ```csharp
 // app/Store/Cart.sharp
 namespace App.Store;
 …
 ```
 …
+<!-- example readme-cart: statements
+$line = new App\Store\Line(500, false);
+-->
 ```php
 $cart = new App\Store\Cart();
 $cart->add($line);
 echo $cart->total();
 ```
````

Members that declare `note` twice, checked one line at a time (section 6):

````diff
+<!-- example nullable-members: members alone -->
 ```csharp
 private int? total;                      // compiles: starts as null
 public string? nickname { get; set; }    // compiles: starts as null
 public string? note { get; }             // compile error: give note an initial value, or a set accessor
 public string? note { get; } = null;     // compiles
 ```
````

Statements whose values run on the engine (section 24):

````diff
+<!-- example integer-division: statements -->
 ```csharp
 7 / 2          // 3
 -7 / 2         // -3
 7 / 2.0        // 3.5
 ```
````

A pair of files, a plain PHP parent and its PHP# subclass, where two lines replace the same method (section 22):

````diff
+<!-- example plain-php-overrides -->
 ```php
 abstract class Report
 …
 ```

+<!-- example plain-php-overrides: alone 4-5 -->
 ```csharp
 public class SalesReport : Report
 …
 ```
````

Plain PHP, whose values are PHP's own (section 12):

````diff
+<!-- example php-spread-renumbers: statements -->
 ```php
 $defaults = ["5" => 10, "pro" => 20];
 $overrides = ["5" => 30];
 [...$defaults, ...$overrides];             // [0 => 10, "pro" => 20, 1 => 30]: the "5" key is gone
 array_replace($defaults, $overrides);      // ["5" => 30, "pro" => 20]: what PHP# runs for a Map spread
 ```
````

## The annotations

A trailing `//` comment on a code line is its annotation. A comment alone on a line continues the one above. These are every form the spec and the README use today, with how each is checked:

| Form | Example | Checked |
|---|---|---|
| `compiles`, `compiles: …`, `compiles, …`, `compiles; runs: …` | `// compiles: starts as null` | no error on the line |
| `fits: …`, `accepted: …` | `// fits: StripeClient is declared Http` | no error on the line, or no throw for plain PHP |
| `compile error`, `compile error: …`, `compile error when …`, `compile error outside …`, `compile error, same message` | `// compile error: write the type` | an error on the line |
| `<words>: compile error` | `// enum case: compile error` | an error on the line |
| `parse error: …` | `// parse error: let takes no written type` | a parse error on the line |
| `throws <Class>`, `throws <Class> when …`, `throws <Class>: …` | `// throws OutOfRangeException when lines is empty` | the line throws that class or a subclass |
| `throws: "<message>"`, `…; throws: "<message>"` | `// throws: "id: expected int, got string 'abc'"` | the line throws that exact message |
| `<value>`, `<value>: …`, `<value>, …` | `// 3`, `// true: the same object`, `// {}` | the line gives that value |
| `<type>: <value>` | `// int: "Total: 3"` | the line gives that value |
| anything else | `// field`, `// pure`, `// runs as trim($name)` | prose: no error on the line |

A value is a number, `true`, `false`, `null`, a quoted string, or a balanced `[…]` or `{…}`. A string value matches its quoted literal, or its raw text for JSON such as `{"counts":[]}`, and `…` inside it matches any text. Any other value is read as a PHP literal and compared with `===`, so `["5" => 30]` matches the array PHP builds from it.

**Every line is checked, annotated or not.** A line with no annotation, or with prose, must compile with no error. A `compile error` line must get one. The check is the whole block, so a block the checker can't read fails on its first line.

**The text after `compile error:` is not compared.** The spec writes it for the reader, and most of it is a paraphrase, such as "int is not bool" for the checker's "`if` takes a `bool`, but this is `int`." Comparing it would fail examples whose checker is right. What a message says is guard 2's job, the message rules in mago-sharp. The check still refuses three wrong reasons:

- **A refusal is not the error the spec means.** An error whose message holds "not supported yet" never satisfies a `compile error` line. It says the feature is missing, not that the rule holds.
- **A parse error satisfies only a `parse error` line.** That is a parse error mago reports as "Parse error encountered during parsing …", which says the parser could not read the line. A message the parser writes to name a PHP# rule, such as "PHP# has no `echo`: write `printf` or `fwrite`.", satisfies a `compile error` line.
- **Every error must belong to a line that expects one.** An error belongs to the line its primary span starts on. An error on a line with no annotation, or with prose, belongs to the one `compile error` line in the same top-level construct of the block, so "A `match` needs a `default` arm.", which mago reports at the `match` keyword, belongs to the arm annotated below it. An error on a line annotated `compiles` stays on that line. An error no line expects fails the example.

**A `throws: "<message>"` is compared exactly.** The spec quotes it, and the message is what a caller of `parse` reads.

**A runtime line runs only when the block compiles.** For a block with `compile error` lines, the run uses the block with those lines blanked, and that file must compile with no error. A value line runs as the argument of a call that records its value. A `throws` line runs inside a `try`, so the lines after it still run.

## Setup

**Shared classes live in `sharp/harness/`**, one class per file at the path its namespace names. It holds the names the spec uses without declaring them:

- PHP# classes, such as `App.Order`, `App.Line`, `App.Money` and `App.Store.Line`.
- Plain PHP stubs for what the spec calls plain PHP, such as `Illuminate\Database\Eloquent\Model`, Laravel's facades and `Stripe\StripeClient`.
- Plain PHP functions the spec calls by a bare name, `charge`, `deliver` and `render`, in `app/functions.php`, which the run loads before the example.
- `App.Elided`, the exception `…` throws, and `App.Outcome`, the plain PHP class a run records values and throws with.

A block's own variables, such as `lines` or `p`, are its setup lines, next to the block, because each block needs different ones.

**Each example compiles as its own project:** a temporary folder with `sharp/harness/`, the example's files, a `composer.json` that maps `App\` to `app/`, and `sharp/composer/` at `vendor/heyjordanparker/php-sharp-composer/`, where a Composer project has it. Mago treats a `.sharp` file as the standard library only when the nearest `composer.json` names that package, so the library can't sit anywhere else, and a law's Lean module name comes from the project's PSR-4 folder. A harness file is left out when the example declares a class it declares, and an example file at a harness file's path replaces it, so the example's `Order` replaces the harness's `Order`. One project per example also keeps one crash from failing the rest: `value as List<string>` panicked `mago compile` (task #456), and a crash fails only its own example.

`sharp/bin/spec` first compiles the harness alone, and fails when the harness has any error, so a broken setup never shows up as a failing example.

## The list

`sharp/baseline/spec` follows `sharp/baseline/darwin` and `sharp/baseline/linux`: one tab-separated line per example that fails, in natural order, with no header.

```text
README	readme-cart	0
§6	nullable-members	1
§12	iterables	3
```

- **The section** is the heading the block sits under, as in `§6.1`, or `README`.
- **The id** is the marker's. A line number would change on every spec edit.
- **The last field** counts the errors that say "not supported yet" across the example's files.

`sharp/bin/spec` fails when the list differs from what the examples do, and prints the `cp` command that writes the new list, as `sharp/bin/test --upstream` does for its baseline. An example that fails prints every line that differs from its annotation, with the checker's message or the value it gave.

## CI

`sharp/bin/test --spec` runs `sharp/bin/spec` after the suite, as `--census` runs `sharp/bin/census`, with the engine and the `mago` that `sharp/bin/test` already installed. `LINUX` adds `--spec` to its `Test PHP#` step, so `CI_PASSED` covers it, and runs one more step on a pull request:

```yaml
      - name: Compare sharp/baseline/spec with the base branch
        if: github.event_name == 'pull_request'
        env:
          BASE: ${{ github.event.pull_request.base.sha }}
        run: |
          git fetch --depth=1 origin "$BASE"
          sharp/docker/run php sharp/bin/spec --base "$BASE"
```

The comparison needs no build. It reads `sharp/baseline/spec`, `docs/spec.md` and `README.md` at the base commit with `git show`, so it runs on the PHP of the `sharp/docker/` image.

`CHANGES` treats `docs/spec.md` and `README.md` as code, so a pull request that changes only the spec still runs `LINUX`.

**`Completes:` runs in its own workflow,** `.github/workflows/completes.yml`, on the `pull_request` types `opened`, `edited`, `synchronize` and `reopened`. Its one job, `COMPLETES`, reads the body's `Completes:` line, reads `sharp/baseline/spec` at the pull request's head with `git show`, and fails when a named section still has an entry. It builds nothing, so an edited body reruns it in seconds, and the main workflow never runs on `edited`. A body with no `Completes:` line passes.

CI fails in each of the five cases:

1. **An example that is not on the list fails.** `sharp/bin/spec`: the list differs from the run.
2. **A listed example passes.** The same check.
3. **A PR adds an entry to the list.** `--base`: the head list has an id the base list doesn't, and the example's code and setup are the same at both commits.
4. **`Completes: §6.1, §9` names a section that still has entries.** `COMPLETES`: `§9` covers `§9` and every `§9.x`.
5. **A PR raises the count of "not supported yet" refusals in a section it doesn't complete.** `--base`: the section's sum of the last field rises across the examples whose code and setup are the same at both commits.

**Only a spec PR may grow the list.** Cases 3 and 5 count only examples whose code and setup are the same at the base and the head. A new or edited example may join the list only in a pull request that changes nothing outside `docs/spec.md`, `README.md` and `sharp/baseline/spec`, so a builder can't edit an example and break the engine in one pull request. A ruling Jordan approves lands with its examples before any builder implements it, and those examples are v1's remaining work. Adding `ignore` to an example counts as adding an entry.

## Runtime

Measured on an Apple M-series Mac with the pinned `mago` at `449377ad4` and a debug build of the engine, on a prototype project of 25 PHP# files: the README's `Cart`, a `Line`, one example class and the 22 standard library files.

- **One `mago compile`:** 0.24 to 0.45 seconds, most of it starting threads.
- **Four at once with `MAGO_THREADS=1`:** 0.18 seconds for all four.
- **One run on the engine:** 0.13 seconds for the README's plain PHP caller.

`sharp/bin/spec` runs four single-threaded compiles at a time. A full run over the 160 examples, 158 checked and 2 ignored, takes 26 seconds on that Mac.

## Files

```text
sharp/
├── bin/spec              # new: PHP, like census. Checks every example, or with --base compares two commits
├── bin/test              # --spec runs sharp/bin/spec after the suite
├── baseline/spec         # new: the examples that fail today, the v1 meter
└── harness/              # new: the classes and plain PHP stubs the examples share
docs/
├── spec.md               # a marker above every csharp, ts and php block
└── architecture/spec-examples.md
README.md                 # a marker above its two blocks
sharp/README.md           # documents --spec, the list and COMPLETES
docs/how-we-work.md       # a PR that completes a section says Completes: §N
.github/workflows/
├── sharp.yml             # CHANGES counts the spec as code, LINUX runs --spec and --base
└── completes.yml         # new: the COMPLETES job
```

`COMPLETES` joins the `master merge gate` ruleset after this lands.

## The markers

`docs/spec.md` has a marker above each of its 161 checked blocks, and `README.md` above each of its 2. Three examples have two blocks, so there are 160 examples in all: 158 checked and 2 ignored. The markers themselves are the record, so this note lists none of them by line.
