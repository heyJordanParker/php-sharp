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
- **`<!-- example <id>: ignore <reason> -->`** skips a block that is not complete code by design. Only two blocks have it: `naming-values` in §16 and `native-body` in §29. Adding `ignore` to an example that had none counts as adding an entry to the list, also under a new id: an ignored block whose code a checked example ran at the base fails the comparison.

**Run lines** follow the closing fence of the example's last block in a second hidden comment, for a claim the block itself can't run:

```text
<!-- run <id>
<run lines>
-->
```

Run lines are PHP# and carry annotations like any other line. They run after the block's own lines, in its `run` method when the block is `statements`, and otherwise in a `run` method of their own. A value, `throws` or message annotation that no run reaches, such as `// throws OutOfRangeException when no argument was passed` on a method's line, fails the check before anything compiles, unless a run line of the same example makes the same claim. Only a run line restates a claim, never another line of the block. `environment` (§29) shows its `throws` this way, and `copy-on-write`, `capture-by-reference` and `capture-and-change` check the values their prose states, such as `a.count();   // 1`.

A run line calls the code whose claim it restates, as `new Deploy(new Environment()).target();` calls `target()`. The checker can't see that: `throw new OutOfRangeException();   // throws OutOfRangeException` would satisfy the same claim. Only a spec PR changes run lines, so its review checks it.

The check fails at the run marker's own line when a run comment follows a block that is not its example's last, when its id is not the block's, and when it has no `-->` line before the next fence or comment.

### How a fragment becomes a file

`sharp/bin/spec` reads the block's top-level lines, the ones outside any braces the block opens:

- `namespace` and `import` lines go to the head of the file.
- A type declaration, a line with `class`, `struct`, `enum`, `interface`, `event` or `extern` after its modifiers, stays at file level with its body.
- Under `statements`, a line that starts with an access modifier or `static`, or a method signature with no modifier such as `void reserve(string sku, int count) { … }`, is a member of the example class, and every other line runs in its `run` method.
- Under `members`, every other line is a member of the example class.

A line goes where the line that starts its construct goes. A construct continues on every line inside brackets the construct opened, on a line that starts with `{` or `:`, and after an attribute line. A template string open across lines counts as an open bracket.

A file with no `namespace` line gets `namespace App;`, the namespace of the shared setup classes, so the block's names resolve with no imports, as the reader assumes.

The spec writes `…` for code the reader doesn't need. `sharp/bin/spec` replaces it, as Kotlin's `TODO()` and Rust's `todo!()` stand in for a missing body:

- `{ … }` after a type's header, or after `if`, `else`, `while`, `for`, `do`, `try`, `catch` or `finally`, becomes `{ }`, so a run passes through it.
- `{ … }` anywhere else becomes `{ throw new Elided(); }`.
- `(…)` becomes `()`.
- `match (x) { … }` becomes `match (x) { default => throw new Elided() }`.
- `Plan plan = …;`, a declaration with its type written, becomes `Plan plan = Elided.value() as Plan ?? throw new Elided();`. PHP# refuses to assign a `throw`, whose type is `never`.
- A line holding only `…` is dropped.
- Any other `…` becomes `throw new Elided()`.

A line that ends a statement with no `;`, such as `7 / 2`, gets one, and a method signature with no body, such as `public void tag(string label = null)`, gets `{ … }`. A top-level statement that is a bare expression, such as `order == sameOrderLoadedAgain;`, runs as the argument of the call that records values, because the spec writes it to show what it gives, and PHP# refuses a statement with no effect. A top-level `return x;` under `statements`, such as `return 2.0 ** -1;`, runs the same way, since the `run` method returns nothing. Each rewrite keeps the line on its own line, so every line number the checker reports is the spec's.

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

**The text after `compile error:` is not compared.** The spec writes it for the reader, and most of it is a paraphrase, such as "int is not bool" for the checker's "`if` takes a `bool`, but this is `int`." Comparing it would fail examples whose checker is right. What a message says is guard 2's job, the message rules in mago-sharp. The check still refuses four wrong reasons:

- **A refusal is not the error the spec means.** An error whose message holds "not supported yet" never satisfies a `compile error` line. It says the feature is missing, not that the rule holds.
- **A missing class is not the error the spec means either, unless the line names it.** mago's `non-existent-class-like` error, such as "Cannot find class, interface, enum, or type alias `App\Mailer`", says the setup lacks a name, so it satisfies a `compile error` line only when that line writes the missing name itself, outside a string. So `public void refresh() on OrderPayed { … }   // compile error: there is no event OrderPayed` passes on the error that `OrderPayed` is missing, and a missing name written on another line still fails as missing setup. mago quotes the missing name last in the message, and the check reads that name.
- **A parse error satisfies only a `parse error` line.** That is a parse error mago reports as "Parse error encountered during parsing …", which says the parser could not read the line. A message the parser writes to name a PHP# rule, such as "PHP# has no `echo`: write `printf` or `fwrite`.", satisfies a `compile error` line.
- **Every error must belong to a line that expects one.** An error belongs to the line its primary span starts on. An error on a line with no annotation, or with prose, moves to a `compile error` line only when three things hold:
  - mago reports it for a whole construct at the construct's first line. `MOVES` in `sharp/bin/spec` lists those errors: `match-not-exhaustive`, `rejected-nullable-parameter`, and "A `match` needs a `default` arm.", matched by its message because its code is the generic `semantics`. A new one joins the list in a spec PR.
  - the `compile error` line sits inside the brackets the error's line opens, a `{` that starts the next line included.
  - exactly one such line does.

  So "A `match` needs a `default` arm.", which mago reports at the `match` keyword, belongs to the arm annotated below it. A method header's "Missing return statement" or a lambda call's "Invalid assignment" never moves into the body. An error on a line annotated `compiles` stays on that line. An error no line expects fails the example.

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
README	readme-cart
§6	nullable-members
§12	iterables
```

- **The section** is the heading the block sits under, as in `§6.1`, or `README`.
- **The id** is the marker's. A line number would change on every spec edit.

**The list records which examples fail, not how,** as test262's expected-failure lists do in V8 and SpiderMonkey. A listed example may change its errors, so a checker that turns a parse error into a named "not supported yet" refusal passes, and so does a Mago re-pin that does the same. The list doesn't notice a listed example getting worse. mago-sharp's and php-sharp's own tests cover the rules those examples use.

`sharp/bin/spec` fails when the list differs from what the examples do, and prints the `cp` command that writes the new list, as `sharp/bin/test --upstream` does for its baseline. An example that fails prints every line that differs from its annotation, with the checker's message or the value it gave.

## CI

`sharp/bin/test --spec` runs `sharp/bin/spec` after the suite, as `--census` runs `sharp/bin/census`, with the engine and the `mago` that `sharp/bin/test` already installed. `LINUX` adds `--spec` to its `Test PHP#` step, so `CI_PASSED` covers it. This main check compares the list with what the examples do.

`CHANGES` treats `docs/spec.md` and `README.md` as code, so a pull request that changes only the spec still runs `LINUX`.

**The base branch runs the guard.** A `pull_request` workflow runs the pull request's own copy of its file, so a pull request could change or delete a step that judges it. `.github/workflows/completes.yml` runs on `pull_request_target` instead, which runs the file as the base branch has it. It has two jobs, `SPEC_GUARD` and `COMPLETES`. Each one:

- checks out the pull request's merge commit only as data, with `persist-credentials: false`;
- fetches the base commit and writes the base's `sharp/bin/spec` to `$RUNNER_TEMP`, outside the checkout;
- runs only that copy, on the runner's own PHP, with `permissions: contents: read` and no secrets.

No file of the pull request runs, which is what makes `pull_request_target` safe here: GitHub's warning is about running a pull request's code with the base's permissions. Neither job builds anything. A base with no `sharp/bin/spec` prints a "nothing to compare" line and passes.

```yaml
          git fetch --depth=1 origin "$BASE"
          if ! git cat-file -e "$BASE:sharp/bin/spec"; then
            echo "sharp/bin/spec does not exist at $BASE, so there is nothing to compare."
            exit 0
          fi
          git show "$BASE:sharp/bin/spec" > "$RUNNER_TEMP/spec"
          php "$RUNNER_TEMP/spec" --base "$BASE"
```

`SPEC_GUARD` runs `--base`. It reads `sharp/baseline/spec`, `docs/spec.md` and `README.md` at the base with `git show`, and from the merge commit's checkout. It lists the changed files with `git diff --name-only --no-renames -z`. With `--no-renames`, as `CHANGES` uses, a renamed file shows its old path too, so a guarded file can't leave its folder unseen. With `-z`, git doesn't quote a path that holds a non-ASCII byte, so `sharp/harness/app/fünctions.php` still matches its folder. The base must be a commit in the repository, and any git call that fails fails the check.

**The guard reads no symbolic link.** Before it reads anything, each job fails when the pull request's tree holds a symbolic link at `docs/spec.md`, `README.md` or `sharp/baseline/spec`, anywhere in a guarded folder, or at a folder above any of them. It checks both git's mode `120000` in `git ls-files -s` and `is_link()` on the checkout. A link could point to a file outside the checkout, and the job's log is public, so the message names the link's path and never its target. Only a commit that has no `sharp/baseline/spec` reads as "nothing to compare". The checker finds the repository from its own folder, and from the working directory when it runs outside a repository, as the copy in `$RUNNER_TEMP` does.

`SPEC_GUARD` and `COMPLETES` join the `master merge gate` ruleset after this lands. Until then a pull request's guard runs only from a base that has this workflow, so this pull request, whose base has none, proves the guard locally.

**A law needs Lean.** mago proves a law (section 28) with Lean 4.34.1, so the `sharp/docker/` image installs Debian's `elan` beside `rustup`, and `sharp/docker/run` sets `ELAN_HOME=/src/sharp/build/elan`, as it sets `CARGO_HOME`. It also sets `XDG_CACHE_HOME=/src/sharp/build/cache`, because mago builds its Lean runtime library in its cache folder, and the container's user has no writable home. The first law example a run compiles downloads the toolchain, which took 31 seconds and 3.0 GB in `debian:trixie`. CI caches none of it, because restoring 3 GB is no faster than the download. When mago installs elan into its own cache folder, the §28 Slice removes `elan` from the image.

**`Completes:` runs in the same workflow,** on the `pull_request_target` types `opened`, `edited`, `synchronize` and `reopened`. Its job, `COMPLETES`, runs the base's `sharp/bin/spec --completes` with the body in `BODY`, on the merge commit's checkout. It builds nothing, so an edited body reruns it in seconds, and the main workflow never runs on `edited`. A body with no claim passes. A line is a claim when the word `completes`, in any case, starts it after only non-letters, such as `-`, `1.`, `*`, `>` or backticks, and only non-letters follow it before a section start: `§`, a digit or `README`. Prose that names the `COMPLETES` job goes on with a letter, so it stays prose. `COMPLETES` fails:

- on a claim that is not exactly `Completes: §6.1, §9` on its own line;
- on a named section with no checked example, such as `§99`;
- on a named section that still has an entry in `sharp/baseline/spec`. `§9` covers `§9` and every `§9.x`.

| Body line | Read as |
|---|---|
| `Completes: §6.1, §9` | the claim, checked |
| `Completes: README` | the claim, checked |
| `Completes: README, §6` | the claim, checked |
| `Completes: 6.1, 9` | a malformed claim: fails |
| `1. Completes: §6` | a malformed claim: fails |
| `Completes - §6` | a malformed claim: fails |
| `**Completes:** §6` | a malformed claim: fails |
| `- Completes: §6` | a malformed claim: fails |
| `completes: §6` | a malformed claim: fails |
| `Completes §6` | a malformed claim: fails |
| `` - `COMPLETES` runs `--completes` on the body. `` | prose: no claim |
| `` - `COMPLETES` runs on every body edit, and §6 still has entries. `` | prose: no claim |
| `Completes the work the §6 Slice started.` | prose: no claim |
| ``The `COMPLETES` job fails while §6 has entries.`` | prose: no claim |

CI fails in each of the four cases:

1. **An example that is not on the list fails.** `sharp/bin/spec`: the list differs from the run.
2. **A listed example passes.** The same check.
3. **A PR adds an entry to the list.** `SPEC_GUARD`: the head list has an id the base list doesn't, and the pull request changes more than `docs/spec.md`, `README.md` and `sharp/baseline/spec`, or the base has an example with the same fingerprint, under that id or another.
4. **`Completes: §6.1, §9` names a section that still has entries.** `COMPLETES`.

**A builder PR can't change what measures it.** The spec files are `docs/spec.md`, `README.md`, `sharp/baseline/spec`, and every file in the folders `GUARDED` lists in `sharp/bin/spec`: `sharp/harness/`, `sharp/bin/`, `sharp/docker/` and `.github/`. They hold what measures the examples and everything between a CI step and the checker's exit status: the workflows, `sharp/bin/test`, `sharp/docker/run` and its image, and the checker. The guard matches whole folders, so a file added on that path is guarded too. A pull request that changes any other file must leave every example's fingerprint and every guarded file as the base has them. It can only remove entries, through the `cp` command a failing run prints. Markdown never runs, so a Markdown file outside the guarded folders, such as this note, may change beside a guarded file. A builder that needs a new image package or a workflow change lands it in a pull request of its own. The engine and its build stay the builder's, because they are what the check measures.

**One fingerprint covers the whole example,** all but its id: its section, and every block's marker line, setup, fence language, code and run comment. A moved example, a renumbered heading, a changed fence language, shape, `alone`, `in <Class>` or `ignore` all change it. A renamed example keeps it. The builder-PR rule compares it by id, and case 3 matches it across ids.

**Only a documents-only PR may grow the list.** An entry joins the list only in a pull request that changes nothing but `docs/spec.md`, `README.md` and `sharp/baseline/spec`. A pull request that also changes a guarded file can keep or shrink the list, never grow it, so a harness change can't make an unchanged example fail and list it, even under a renumbered heading. A new example that needs a new harness class lands in two pull requests: the class first, then the example and its entry. A ruling Jordan approves lands with its examples before any builder implements it, and those examples are v1's remaining work. A documents-only PR may rename a listed example: the new id takes the entry when the base listed the old id and the head no longer does.

**New marker or run syntax lands in the checker first.** `SPEC_GUARD` reads the head's documents with the base's checker, so a pull request that adds syntax and uses it fails to parse. The checker change merges in one pull request, and the examples that use it in the next.

## Runtime

Measured on an Apple M-series Mac with the pinned `mago` at `449377ad4` and a debug build of the engine, on a prototype project of 25 PHP# files: the README's `Cart`, a `Line`, one example class and the 22 standard library files.

- **One `mago compile`:** 0.24 to 0.45 seconds, most of it starting threads.
- **Four at once with `MAGO_THREADS=1`:** 0.18 seconds for all four.
- **One run on the engine:** 0.13 seconds for the README's plain PHP caller.

`sharp/bin/spec` runs four single-threaded compiles at a time. A full run over the 161 examples, 159 checked and 2 ignored, takes 26 seconds on that Mac.

## Files

Guarded folders are marked `(guarded)`.

```text
sharp/
├── bin/                  # (guarded)
│   ├── spec              # new: PHP, like census. Checks every example, compares with a base (--base), or reads Completes: (--completes)
│   └── test              # --spec runs sharp/bin/spec after the suite
├── baseline/spec         # new: the examples that fail today, the v1 meter
├── docker/               # (guarded)
│   ├── Dockerfile        # installs elan, for the law examples
│   └── run               # ELAN_HOME and XDG_CACHE_HOME under sharp/build/
└── harness/              # (guarded) new: the classes and plain PHP stubs the examples share
docs/
├── spec.md               # a marker above every csharp, ts and php block
└── architecture/spec-examples.md
README.md                 # a marker above its two blocks
sharp/README.md           # documents --spec, the list, SPEC_GUARD and COMPLETES
docs/how-we-work.md       # a PR that completes a section says Completes: §N
.github/                  # (guarded)
└── workflows/
    ├── sharp.yml         # CHANGES counts the spec as code, LINUX runs --spec
    └── completes.yml     # new: Spec guard, on pull_request_target: the base's --base (SPEC_GUARD) and --completes (COMPLETES)
```

`SPEC_GUARD` and `COMPLETES` join the `master merge gate` ruleset after this lands.

## The markers

`docs/spec.md` has a marker above each of its 162 checked blocks, and `README.md` above each of its 2. Three examples have two blocks, so there are 161 examples in all: 159 checked and 2 ignored. Four examples have run lines: `copy-on-write`, `capture-by-reference`, `capture-and-change` and `environment`. The markers themselves are the record, so this note lists none of them by line.
