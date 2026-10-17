# Spec examples

`docs/spec.md` is the definition of PHP# v1, and `README.md` shows its first example. Every rule comes with code annotated `// compiles`, `// compile error: …`, `// throws …` or the value a line gives. `sharp/bin/spec` compiles every one of those blocks with the `mago` built from `sharp/mago/`, runs the ones that claim a runtime result on the engine, and compares each annotated line with its annotation.

The examples that fail today are listed in `sharp/baseline/spec/`, one empty file each. Their count is the v1 meter: v1 is done when the folder is gone. CI keeps the list honest: an example that passes leaves it, an example off it passes, and an example the base already checked never joins it.

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

Run lines reach a `foreign` object as PHP# code does, through a constructor, since only a module binding creates one (§29). A run line may declare the run class's constructor, as `public ExampleRun(private Deploy deploy) { }`, and the run gets each parameter from `Sharp\Container`, as the container fills a bound class's constructor. The example's module binds the services, and the standard library's module binds `Environment` and the other effect classes. A run class with no constructor parameters is created without its constructor, as before.

A run line calls the code whose claim it restates, as `this.deploy.target();` calls `target()`. The checker can't see that: `throw new OutOfRangeException();   // throws OutOfRangeException` would satisfy the same claim. So the review of the pull request that changes a run line checks it.

The check fails at the run marker's own line when a run comment follows a block that is not its example's last, when its id is not the block's, and when it has no `-->` line before the next fence or comment.

### How a fragment becomes a file

`sharp/bin/spec` reads the block's top-level lines, the ones outside any braces the block opens:

- `namespace` and `import` lines go to the head of the file.
- A type declaration, a line with `class`, `struct`, `enum`, `interface`, `event` or `extern` after its modifiers, stays at file level with its body.
- Under `statements`, a line that starts with an access modifier or `static`, or a method signature with no modifier such as `void reserve(string sku, int count) { … }`, is a member of the example class, and every other line runs in its `run` method.
- Under `members`, every other line is a member of the example class.

A line goes where the line that starts its construct goes. A construct continues on every line inside brackets the construct opened, on a line that starts with `{`, `:` or `=>`, and after an attribute line. So a method whose `=> …` body sits on the line under its signature stays one member, and a run decides whether a line runs by the line that starts its construct. A template string open across lines counts as an open bracket.

A file with no `namespace` line gets `namespace App;`, the namespace of the shared setup classes, so the block's names resolve with no imports, as the reader assumes.

Each type then gets a file of its own, as section 27 says a `.sharp` file declares one type. The types of the block, of its setup and the example class each go to the path their namespace and name give, as `app/Store/Cart.sharp` for `App.Store.Cart`, after the file's `namespace` and `import` lines. What is no type, such as an `extern` line or a `module` block, stays with the first type, and a comment above a type goes with it. Each file maps its lines to the spec's, so an error still names the spec's line. A block whose first line is a path comment, such as `// app/Store/Cart.sharp`, keeps every type it writes in that file, as written, so `one-type-per-file` shows its error, and each type its setup alone declares still gets a file of its own. Two files of one example at one path stop the run, since two types of one name are an error in the example itself.

The spec writes `…` for code the reader doesn't need. `sharp/bin/spec` replaces it, as Kotlin's `TODO()` and Rust's `todo!()` stand in for a missing body:

- `{ … }` after a type's header, or after `if`, `else`, `while`, `for`, `do`, `try`, `catch` or `finally`, becomes `{ }`, so a run passes through it.
- `{ … }` anywhere else becomes `{ throw new Elided(); }`.
- `(…)` becomes `()`.
- `match (x) { … }` becomes `match (x) { default => throw new Elided() }`.
- `Plan plan = …;`, a declaration with its type written, becomes `Plan plan = Elided.value() as Plan ?? throw new Elided();`. PHP# refuses to assign a `throw`, whose type is `never`.
- A line holding only `…` that ends a function's body, the braces after a header that is neither a type's nor a control statement's, becomes `throw new Elided();`, as `{ … }` does there, so a body that returns a value still compiles. Any other line holding only `…` is dropped, so a statement after it still runs.
- Any other `…` becomes `throw new Elided()`.

A line that ends a statement with no `;`, such as `7 / 2`, gets one, and a method signature with no body, such as `public void tag(string label = null)`, gets `{ … }`. So does a library declaration written with its `;`, such as `public List<TResult> map<TResult>(Function<TResult(T)> f);`, since a class holds no method without a body. A field, an abstract method, an event and a method with an `=>` body keep their `;`. A run that leaves out a `compile error` line reads past it, so a signature whose `=> …` line it leaves out still gets `{ … }`. A top-level statement that is a bare expression, such as `order == sameOrderLoadedAgain;`, runs as the argument of the call that records values, because the spec writes it to show what it gives, and PHP# refuses a statement with no effect. A top-level `return x;` under `statements`, such as `return PaymentResult.Declined(reason: "card expired");`, runs the same way, since the `run` method returns nothing. Each rewrite keeps the line on its own line, so every line number the checker reports is the spec's.

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
 private int? total = null;               // compiles: starts as null
 public string? nickname { get; set; }    // compile error: give nickname an initial value, or set it on every path through the constructor
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
| `fits: …`, `accepted: …` | `// fits: StripeClient is declared Http` | no error on the line, or for plain PHP no throw but an `App\Elided` from a body the spec leaves out |
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

**The text after `compile error:` is not compared.** The spec writes it for the reader, and most of it is a paraphrase, such as "int is not bool" for the checker's "`if` takes a `bool`, but this is `int`." Comparing it would fail examples whose checker is right. What a message says is guard 2's job, the message rules in `sharp/mago/`. The check still refuses four wrong reasons:

- **A refusal is not the error the spec means.** An error whose message holds "not supported yet" never satisfies a `compile error` line. It says the feature is missing, not that the rule holds.
- **A missing class is not the error the spec means either, unless the line names it.** mago's `non-existent-class-like` error, such as "Cannot find class, interface, enum, or type alias `App\Mailer`", says the setup lacks a name, so it satisfies a `compile error` line only when that line writes the missing name itself, outside a string. So `public void refresh() on OrderPayed { … }   // compile error: there is no event OrderPayed` passes on the error that `OrderPayed` is missing, and a missing name written on another line still fails as missing setup. mago quotes the missing name last in the message, and the check reads that name.
- **A parse error satisfies only a `parse error` line.** That is a parse error mago reports as "Parse error encountered during parsing …", which says the parser could not read the line. A message the parser writes to name a PHP# rule, such as "A PHP# parameter needs a type, as in `int extra`.", satisfies a `compile error` line.
- **Every error must belong to a line that expects one.** An error belongs to the line its primary span starts on. An error on a line with no annotation, or with prose, moves to a `compile error` line only when three things hold:
  - mago reports it for a whole construct at the construct's first line. `MOVES` in `sharp/bin/spec` lists those errors: `match-not-exhaustive`, `rejected-nullable-parameter`, and "A `match` needs a `default` arm.", matched by its message because its code is the generic `semantics`. A new one joins `MOVES` in a pull request that changes `sharp/bin/spec` alone.
  - the `compile error` line sits inside the brackets the error's line opens, a `{` that starts the next line included.
  - exactly one such line does.

  So "A `match` needs a `default` arm.", which mago reports at the `match` keyword, belongs to the arm annotated below it. A method header's "Missing return statement" or a lambda call's "Invalid assignment" never moves into the body. An error on a line annotated `compiles` stays on that line. An error no line expects fails the example.

**A `throws: "<message>"` is compared exactly.** The spec quotes it, and the message is what a caller of `parse` reads.

**A runtime line runs only when the block compiles.** For a block with `compile error` lines, the run uses the block with those lines blanked, and that file must compile with no error. A value line runs as the argument of a call that records its value. A `throws` line runs inside a `try`, so the lines after it still run. A plain PHP line that claims no error, `compiles`, `fits:` or `accepted:`, runs inside a `try` that takes only `App\Elided`: the call reached a body the spec leaves out, so PHP# took it, as `raw-generic-objects`' `$report->count($page)` does.

## Setup

**Shared classes live in `sharp/harness/`**, one class per file at the path its namespace names. It holds the names the spec uses without declaring them:

- PHP# classes, such as `App.Order`, `App.Line`, `App.Money` and `App.Store.Line`.
- Plain PHP stubs for what the spec calls plain PHP, such as `Illuminate\Database\Eloquent\Model`, Laravel's facades and `Stripe\StripeClient`.
- `App.Elided`, the exception `…` throws, and `App.Outcome`, the plain PHP class a run records values and throws with.

A block's own variables, such as `lines` or `p`, are its setup lines, next to the block, because each block needs different ones.

**Each example compiles as its own project:** a temporary folder with `sharp/harness/`, the example's files, a `composer.json` that maps `App\` to `app/`, and `sharp/composer/` at `vendor/heyjordanparker/php-sharp-composer/`, where a Composer project has it. Mago treats a `.sharp` file as the standard library only when the nearest `composer.json` names that package, so the library can't sit anywhere else, and a law's Lean module name comes from the project's PSR-4 folder. A harness file is left out when the example declares a class it declares, and an example file at a harness file's path replaces it, so the example's `Order` replaces the harness's `Order`. One project per example also keeps one crash from failing the rest: `value as List<string>` panicked `mago compile` (task #456), and a crash fails only its own example.

`sharp/bin/spec` first compiles the harness alone, and fails when the harness has any error, so a broken setup never shows up as a failing example.

## The list

`sharp/baseline/spec/` holds one empty file per example that fails, at `<section>/<id>`. towncrier keeps changelog entries the same way, one news fragment per file, so pull requests that list or fix different examples never edit the same file and never conflict.

```text
sharp/baseline/spec/
├── README/readme-cart
├── §6/nullable-members
└── §12/iterables
```

- **The section** is the heading the block sits under, as in `§6.1`, or `README`.
- **The id** is the marker's. A line number would change on every spec edit.

`sharp/bin/spec` fails on a file that is not empty, on a path that is not `<section>/<id>`, and on an id listed under two sections.

**The list records which examples fail, not how,** as test262's expected-failure lists do in V8 and SpiderMonkey. A listed example may change its errors, so a checker that turns a parse error into a named "not supported yet" refusal passes, and so does a later change to `sharp/mago/` that does the same. The list doesn't notice a listed example getting worse. Mago's own tests and `Zend/tests/sharp/` cover the rules those examples use.

`sharp/bin/spec` fails when the list differs from what the examples do, and prints the commands that make it match: `git rm -q sharp/baseline/spec/<section>/<id>` for an example that passes, and `mkdir -p` and `touch` for one that fails. An example that fails prints every line that differs from its annotation, with the checker's message or the value it gave.

**An open pull request that edited the old one-file list** converts once, when it merges master. git reports a modify/delete conflict on the file `sharp/baseline/spec`. Resolve it with `git rm sharp/baseline/spec`, then run `sharp/bin/test --spec` and the commands it prints: they delete the files of the examples the pull request fixed and add the files of examples it lists.

## CI

`sharp/bin/test --spec` runs `sharp/bin/spec` after the suite, as `--census` runs `sharp/bin/census`, with the engine and the `mago` that `sharp/bin/test` already built from `sharp/mago/`. `LINUX` adds `--spec` to its `Test PHP#` step, so `CI_PASSED` covers it. This main check compares the list with what the examples do.

**The checker tests itself first.** `sharp/bin/test --spec` runs `sharp/bin/spec --self-test` before the examples. It checks the examples of `sharp/spec-self-test.md`, one for each rule `check_compile`, `check_run` and the annotation reader apply, and fails unless each gives exactly the problems the `<!-- expect <id>` comment after it lists. Most of those examples break their rule on purpose, so the problems they expect are the rule at work, and each fails when its rule is switched off. An expect comment counts lines from the example's first fence, as `line 2`, so a row above can grow, and `…` matches any text, so a checker change that rewords a message keeps the row. The file is not guarded, because a checker change that turns a refusal it uses into a real error changes that row in the same pull request.

`CHANGES` treats `docs/spec.md`, `README.md` and `sharp/spec-self-test.md` as code, so a pull request that changes only one of them still runs `LINUX`.

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

`SPEC_GUARD` runs `--base`. It reads `docs/spec.md` and `README.md` at the base with `git show` and lists `sharp/baseline/spec/` there with `git ls-tree`, and it reads all three from the merge commit's checkout. It lists the changed files with `git diff --name-only --no-renames -z`. With `--no-renames`, as `CHANGES` uses, a renamed file shows its old path too, so a guarded file can't move unseen. With `-z`, git doesn't quote a path that holds a non-ASCII byte, so a path such as `sharp/baseline/spec/§6.1/map-reads` still matches. The base must be a commit in the repository, and any git call that fails fails the check.

**The guard reads no symbolic link.** Before it reads anything, each job fails when the pull request's tree holds a symbolic link at `docs/spec.md`, `README.md`, `sharp/baseline/spec` or a guarded file, anywhere in `sharp/baseline/spec/`, or at a folder above any of them. It checks both git's mode `120000` in `git ls-files -s` and `is_link()` on the checkout. A link could point to a file outside the checkout, and the job's log is public, so the message names the link's path and never its target. A commit with no `sharp/baseline/spec/` lists no example. The checker finds the repository from its own folder, and from the working directory when it runs outside a repository, as the copy in `$RUNNER_TEMP` does.

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
3. **An example the base already checked joins the list.** `SPEC_GUARD`: the head list has an id the base list doesn't, and the base has an example with the same fingerprint, under that id or another.
4. **`Completes: §6.1, §9` names a section that still has entries.** `COMPLETES`.

**A feature PR changes the spec with its code.** One pull request changes the language's rules in `docs/spec.md`, their examples, `sharp/baseline/spec`, the checker and the engine. The list still has to name exactly the examples that fail, so the ratchet holds: a passing example leaves the list, and an example off the list passes.

**A PR that changes the measure changes nothing else.** The measure is `sharp/bin/spec`, the one file `GUARDED` lists. A pull request that changes any other file, Markdown and `sharp/baseline/spec` aside, leaves it as the base has it, so a feature can't move the measure it is judged by. Markdown never runs, so a Markdown file, such as this note, may change beside it.

**A harness change is reviewed, not guarded.** A feature adds the harness classes and stubs its examples compile against, such as the `Username` struct the structs examples use, so `sharp/harness/` changes in feature pull requests. The pull request body lists every harness file it adds or changes, and its review checks that no stub makes an example pass falsely, such as a stub that returns the value a line claims instead of running the code that gives it.

**One fingerprint covers the whole example,** all but its id: its section, and every block's marker line, setup, fence language, code and run comment. A moved example, a renumbered heading, a changed fence language, shape, `alone`, `in <Class>` or `ignore` all change it. A renamed example keeps it. Case 3 matches it across ids, so an unchanged example can't fail and join the list, even under a renumbered heading. A pull request may rename a listed example: the new id takes the entry when the base listed the old id and the head no longer does.

**New marker or run syntax lands in the checker first.** `SPEC_GUARD` reads the head's documents with the base's checker, so a pull request that adds syntax and uses it fails to parse. The checker change merges in one pull request, and the examples that use it in the next.

## Runtime

Measured on an Apple M-series Mac with `mago` at mago-sharp commit `449377ad4`, before Mago moved into `sharp/mago/`, and a debug build of the engine, on a prototype project of 25 PHP# files: the README's `Cart`, a `Line`, one example class and the 22 standard library files.

- **One `mago compile`:** 0.24 to 0.45 seconds, most of it starting threads.
- **Four at once with `MAGO_THREADS=1`:** 0.18 seconds for all four.
- **One run on the engine:** 0.13 seconds for the README's plain PHP caller.

`sharp/bin/spec` runs four single-threaded compiles at a time. A full run over the 161 examples, 159 checked and 2 ignored, takes 26 seconds on that Mac.

## Files

Guarded files are marked `(guarded)`.

```text
sharp/
├── bin/
│   ├── spec              # (guarded) new: PHP, like census. Checks every example, compares with a base (--base), reads Completes: (--completes), or tests its own rules (--self-test)
│   └── test              # --spec runs sharp/bin/spec --self-test, then sharp/bin/spec, after the suite
├── baseline/spec/        # new: one empty file per example that fails today, the v1 meter
├── spec-self-test.md     # one example for each rule sharp/bin/spec applies, with the problems it must report
├── docker/
│   ├── Dockerfile        # installs elan, for the law examples
│   └── run               # ELAN_HOME and XDG_CACHE_HOME under sharp/build/
└── harness/              # new: the classes and plain PHP stubs the examples share, reviewed in every pull request that changes them
docs/
├── spec.md               # a marker above every csharp, ts and php block
└── architecture/spec-examples.md
README.md                 # a marker above its two blocks
sharp/README.md           # documents --spec, the list, SPEC_GUARD and COMPLETES
docs/how-we-work.md       # a PR that completes a section says Completes: §N
.github/
└── workflows/
    ├── sharp.yml         # CHANGES counts the spec and the self-test as code, LINUX runs --spec
    └── completes.yml     # new: Spec guard, on pull_request_target: the base's --base (SPEC_GUARD) and --completes (COMPLETES)
```

`SPEC_GUARD` and `COMPLETES` join the `master merge gate` ruleset after this lands.

## The markers

`docs/spec.md` has a marker above each of its 162 checked blocks, and `README.md` above each of its 2. Three examples have two blocks, so there are 161 examples in all: 159 checked and 2 ignored. Four examples have run lines: `copy-on-write`, `capture-by-reference`, `capture-and-change` and `environment`. The markers themselves are the record, so this note lists none of them by line.
