# Slice 27: PHP# files

Spec section 27. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Most of section 27 already holds on master: the extension, the grammar each layer picks from it, magic constants refused, `Position.current()` in a body, the three refusals of a missing, stale or foreign compiled file, unsuppressible errors, `mago compile` over vendor packages, the `.lean` file it creates, and every position a developer reads. Every error, exception, stack frame and warning names the `.sharp` file and its line, with and without opcache, under the tracing JIT and through the file cache. Two examples fail, and probing every rule line found four more gaps:

- **One type per file is silently accepted in every form.** Two classes, a class beside an interface, enum, struct or event, two events, a type named unlike its file (`Misnamed.sharp` declaring `Elsewhere`, `Casefold.sharp` declaring `CaseFold`), and a class beside an `extern` line all compile. A class named unlike its file is then unreachable by name: plain PHP gets "no class `App\Shop\Fresh`" after `Fresh.sharp` is renamed `Renamed.sharp`, and the second class of `TwoClasses.sharp` is never found. `one-type-per-file` fails on it.
- **A `Position.current()` default is refused** as "not supported yet". `position` fails on it.
- **Preloading a Composer project crashes the engine.** The plugin's `autoload.php` calls `Sharp\Internal\readModule`, and a preload script that calls it alone fails at the end of the preload request with a freed value in `container_bindings`. No preloaded app can start. This is section 32's code, found here because section 27's Composer crossing must hold under preloading.
- **`<?php` in a `.sharp` file is refused with the generic "Parse error encountered during parsing"**, which names no fix.

Two findings shape the work more than the code does:

- **`sharp/bin/spec` writes every type of a block and its setup into one file.** 29 examples write several types into one generated file: `one-type-per-file` on purpose, `one-body-per-method` and `sub-interface-defaults` in their own text, and 26 through their setup or the `Example` wrapper class, `position` and `listeners` among them. `listeners`' setup declares an interface and two events beside its class. The rule breaks those 26 unless the runner writes each type to its own file first. That is a change to the guarded `sharp/bin/spec`, in its own PR (decision 3).
- **The spec text breaks its own rule twice:** `one-body-per-method` writes five types under `// app/Tickets.sharp`, and `sub-interface-defaults` four under `// app/Shows.sharp` (decision 4).

The rule also reaches tests: 49 of the 143 `.sharp` fixtures under `Zend/tests/sharp/` declare several types or one named unlike the file, and up to 178 of the 641 mago tests that write PHP# types write several in one source (decision 1).

## Decisions team-lead approves

1. **The rule covers every `.sharp` file that declares a type, as line 3508 says.** The folder-namespace rule stays where it is, on files in a PSR-4 folder, because only PSR-4 maps a folder to a namespace. The cost is test sources, rewritten one at a time:
   - 49 engine fixtures: 43 declare several types, 6 declare one named unlike the file (`trace grep` over `Zend/tests/sharp/*.sharp`).
   - Up to 178 mago test functions whose sources declare several PHP# types, of 641 that declare any (an upper bound: some already put each type in its own file). How many name a type unlike the file is measured once the rule runs.
   - `sharp/harness/`, `sharp/composer/library/` and `sharp/phpunit/` already hold: their only multi-declaration file is `Laravel.sharp`, which declares no type.
   - The alternative, the PSR-4 gate the folder rule uses, costs no test, but leaves a file outside any PSR-4 folder free to hold several types, which line 3508 forbids. It needs a spec change, so it is Jordan's call, and this note does not propose it.
2. **`check_declarations` enforces the rule,** beside the folder-namespace rule it already runs in `crates/semantics/src/internal/checker/sharp.rs`. It already collects the file's declarations with `declarations(program)`. A type is a class, interface, enum, struct or event at file level. `extern` lines and `module` blocks are not types, so an `extern` file, a `Module.sharp`, a `Module.<Environment>.sharp` and a test file's `module : …` block need no exemption. The errors land on the type's name:
   - a second type: "A `.sharp` file declares one type: move `StoreHelper` to `StoreHelper.sharp`."
   - a type named unlike the file, case included, because Linux's PSR-4 lookup is: "`Misnamed.sharp` declares `Elsewhere`: rename the file `Elsewhere.sharp`, or the type `Misnamed`."
   - This also makes true what `mago compile` already assumes. `compile.rs` stamps `<folder>/<Class>.sharp` and `.php`, absent, as inputs of a file that names a default class its namespace could shadow (line 320). A shadowing class added in a misnamed file changes neither, so the file stays current while its name now resolves elsewhere.
3. **`sharp/bin/spec` writes each file-level type to its own file, in its own PR first.** `build_file` returns one file per type: a whole-file block with a path comment stays one file as written, so `one-type-per-file` still shows its error, and every other type, from the block or its setup, goes to `app/<namespace path>/<Name>.sharp` with the block's `namespace` and `import` lines. The `Example` or `in <Class>` wrapper class gets its own file. Each file keeps its own line map, so every reported line stays the spec's. `variant_files`' collision rename to `Name1.sharp` goes, because two files at one path is two declarations of one name, which mago already refuses. `sharp/spec-self-test.md` gains an example whose setup declares two types, and `docs/architecture/spec-examples.md`'s "How a fragment becomes a file" says it. Today's run passes 185 examples, and that PR must leave exactly the same list.
4. **The two spec examples that break the rule become one block per file under their id,** as `event-declarations` already is: `one-body-per-method` writes `ShowsLabel.sharp`, `ShowsTitle.sharp`, `Ticket.sharp`, `Sticker.sharp` and `Stray.sharp`, and `sub-interface-defaults` writes `Shows.sharp`, `ShowsMore.sharp`, `ShowsLess.sharp` and `Fancy.sharp`. Their annotations and run lines do not change. No rule changes.
5. **A `Position.current()` default gives the caller's position, built in the bridge from the checker's callee,** as C++20's `source_location::current()` and Swift's `#file` defaults are filled in at the call. Decision 037 already says a plain PHP caller gets the position where the parameter is declared, which is what a PHP default gives.
   - The semantic check drops the refusal at `sharp.rs` line 1445 for a default that is exactly `Position.current()`.
   - The codex scanner sets a new parameter flag, `MetadataFlags::CALLER_POSITION`, on such a parameter in a `.sharp` file. The codex already carries PHP# facts this way (`SHARP`, `NAMED_CONSTRUCTOR`, `INIT`, `EVENT`, `FIELD`).
   - `Lowering::sharp_arguments`, the one place a PHP# call lowers its arguments against its callee, appends a named argument for each flagged parameter the call leaves out: `caller: new \Sharp\Position(__FILE__, 12, 9, 'App.Reports.run')`, the call's start line and byte column and the caller's dotted function. `current_position` already builds that node for a body, and takes a span instead of the class node.
   - The callee's own default lowers to the position of the parameter's declaration, `new \Sharp\Position(__FILE__, 7, 39, 'App.Reports.logSlow')`, which PHP 8.1's `new` in initializers allows. A plain PHP caller and any call the bridge has no callee for get it.
   - The engine needs no change: `Sharp\Position`'s constructor already derives `directory`.
6. **`<?php` at the head of a `.sharp` file fails as a typo does,** at the tag, and the file parses on.
7. **The Composer plugin's class map lists only a file's own type.** `Plugin::declaredClasses` names a class from the file's name whatever the file declares, so today's map lists `App\Rules\Module.Production`, which is no class name, plus `App\Rules\Module`, `App\Rules\Externs`, `Sharp\Builtins` and every misnamed file. It will list a file only when it declares a type named like it, read as its interface test already reads `interface <Name>`. The PSR-4 path in `autoload.php` needs no change.
8. **The preload crash is fixed in a `fix/` PR of its own, before this one,** because it stops every preloaded app, not only section 27's crossing. The backtrace stops in `zm_deactivate_sharp` (`sharp.c` line 7722) destroying `container_bindings`, on a value with refcount 67130399 and type 1, freed memory. `sharp_container_read` compiles the module file, runs it, then destroys its op array while the bindings still hold its rows. The likeliest cause, unconfirmed: under preloading the rows reference values the op array owns, which the destroy frees. The fix PR proves the cause first, under `USE_ZEND_ALLOC=0 MallocScribble=1`. The alternative is to fix it in this PR, which then also completes a section 32 behavior.
9. **New engine test files:** `Zend/tests/sharp/position_caller.phpt` and `position_caller_preload.phpt`. Their rows are a PHP# caller, a PHP# caller through a named argument, a constructor and a static call, a plain PHP caller, and a call through each remaining form decision 5 reaches. The one-type rule and the opening tag are checker rules, so their tests are rows in `crates/semantics/tests/sharp.rs` and the syntax crate's tests, with no engine test.
10. **The flag's name, `CALLER_POSITION`,** from these candidates, each in `parameter.flags.contains(MetadataFlags::…)`:
    - `CALLER_POSITION`: what the parameter receives. Reads "the parameter takes the caller's position".
    - `CALL_SITE_DEFAULT`: when the default is evaluated. Reads wider than this one form.
    - `POSITION_DEFAULT`: the default's form. Leaves "which position?" open.
    - `DEFAULTS_TO_CALLER`: the same idea as a verb phrase, where every neighbouring flag is a noun or an adjective.
    - `CURRENT_POSITION`: repeats the call, and reads as the callee's own position.

    Runner-up: `CALL_SITE_DEFAULT`, if a second call-site default ever joins `Position`.

## Questions for Jordan

Each one decides what users write. The note recommends and decides none.

1. **Line 3554: what does a call through a function value give a `Position.current()` default?** `Function<int(int)> f = Values.bump;` compiles today for `bump(int value, int by = 1)`, so a function type can leave out a defaulted parameter, and `f(1)` has no call site the bridge can fill. Recommended: the declaration's position, as a plain PHP caller gets (decision 037). Swift and C++ fill a default only at a direct call too. The alternative is a compile error where a method with a caller-position parameter becomes a function value whose type leaves it out.
2. **Line 3554: does a default that reads one field of the position give the caller's?** `int line = Position.current().line` is refused today as "This expression is not supported yet in PHP#." Recommended: a compile error that names the form, "Take the whole position: `Position caller = Position.current()`.", because decision 037 chose one value over C#'s separate caller attributes. The alternative gives the caller's line, as `std::source_location::current().line()` does as a C++ default.
3. **Line 3567: does anything run `mago compile` at deploy, or only the user's deploy script?** Nothing runs it today, and the engine refuses every file it would have compiled, naming the command. Recommended: the user's deploy runs it, as the line says, and no install hook does, because a compile proves laws with Lean, whose first run downloaded 3.0 GB in 31 seconds on CI's image (`docs/architecture/spec-examples.md`), and a hook would run it on every `composer install` in development too. The alternative is the Composer plugin running it on `composer install --no-dev` or `dump-autoload --optimize`.

## Questions for team-lead

1. **Line 3495: are editors in scope?** The engine, the checker and the Composer plugin each choose the grammar from the exact extension `.sharp`. No editor support ships: `mago` has no language server, and `editor-url` only makes terminal links. Recommended: the line holds through the extension, and an editor package is its own feature.
2. **The branch for decision 3's `sharp/bin/spec` PR,** and for decision 8's fix PR.

## How the states were observed

- **mago:** release, built from master `d31140d813` with `CARGO_PROFILE_RELEASE_LTO=off` under `rustup run 1.97.0`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 4. It prints `mago 0.3.1`.
- **Engine:** `sharp/bin/build` at `d31140d813`, PHP 8.5.11 NTS DEBUG. Every run used `php -n`, so no Xdebug and no ini file.
- **Spec run:** `sharp/bin/spec` with that pair: "Checked 201 examples, ignored 1, and 16 fail", equal to `sharp/baseline/spec`. Section 27 has three examples. `file-header` passes, and so does `position`'s every line but one. The two listed fail:

```text
FAIL §27 one-type-per-file (docs/spec.md:3511)
  docs/spec.md:3516 expects a compile error, and the checker accepts it
FAIL §27 position (docs/spec.md:3524)
  docs/spec.md:3538 expects no error, and the checker reports: A `Position.current()` default is not supported yet in PHP#.
```

- **Probes:** `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-files/ws/` is a Composer project: `App\` maps to `app/`, and `heyjordanparker/php-sharp-composer` installs from `sharp/composer/` as a copy, through Composer 2's path repository. `compile.sh ws` runs `mago compile` with `MAGO_REPORTING_FORMAT=emacs`. `probes/runtime.php` runs the engine probes through the project's real `vendor/autoload.php`, `probes/state.php <class>` loads one class, and `probes/preload*.php` are the preload scripts. Every message and value below is their output.

# Every rule of section 27, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), **crashes** and **not built**.

## Files and their grammar

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3495 | The extension marks a PHP# file, and the engine, Composer, editors and the checker choose the grammar from the filename | Works for the engine (`sharp_is_sharp_file`, `memcmp` on `.sharp`), the checker (`Dialect::of`, `ends_with(b".sharp")`), and the plugin (`getExtension() === 'sharp'`, `$findFile`'s `'.sharp'`), each exact and lowercase. No editor support ships | None new. Editors: team-lead question 1 | |
| 3497 | A PHP# file has no opening tag; its first line is code | Works: `file-header` passes. `<?php` first is refused with "Parse error encountered during parsing" at 1:1 and 2:1 | Parser (decision 6) | `ParseError::PhpSyntaxInSharp` |
| 3506 | The extension is `.sharp` | Works | None new | |
| 3508 | A `.sharp` file that declares a type declares one | **Silently accepted:** `TwoClasses.sharp`, `WithInterface.sharp`, `WithEnum.sharp`, `WithStruct.sharp`, `WithEvent.sharp`, `TwoEvents.sharp`, `Second.sharp` (`First` then `Second`) and `ExternAndClass.sharp` compile. `App\Rules\Helper`, `TwoClasses.sharp`'s second class, is "no class" through Composer | Semantics (decisions 1, 2) | `check_declarations`, `declarations` |
| 3508 | That type is named like the file | **Silently accepted:** `Misnamed.sharp` declaring `Elsewhere`, `Casefold.sharp` declaring `CaseFold`, and `Fresh.sharp` renamed `Renamed.sharp` compile; plain PHP then gets "no class `App\Shop\Fresh`" | Semantics (decision 2) | `check_declarations` |
| 3508 | Every type, events and interfaces included, sits at file level | Works: "A class can't declare a type inside it: declare `Inner` in its own file." | None new | |
| 3508 | in the namespace its folder names (section 5) | Works under PSR-4: `WrongFolder.sharp` gets "This file's folder holds the namespace `App.Rules`, so the file declares `namespace App.Rules;`." | None new | `check_declarations` |
| 3508, 3516 | A second type is a compile error | **Silently accepted:** `one-type-per-file` line 3516 | Semantics (decision 2), and `sharp/bin/spec` keeps the example one file (decision 3) | |
| 3508 | An `extern` file and a `Module.sharp` hold no type, so the rule doesn't cover them | Works: `Externs.sharp` (two `extern` lines), `Module.sharp` and `Module.Production.sharp` compile, and must keep compiling under the rule | None new: the rule counts types only | |
| 3519 | Plain PHP files keep `.php` and `<?php`, and the two call each other freely | Works: plain PHP's `(new App\Shop\Order())->total()` gives `500` through Composer's PSR-4 loader and its optimized class map, with and without opcache. PHP# calling plain PHP: section 23's probes (#145) and #124 | None new | |
| 3519 | Composer's autoloader tries `.sharp` after `.php` | Works: with `Twin.php` and `Twin.sharp` both declaring `App\Shop\Twin`, the class says `php`, and `mago compile` refuses `Twin.sharp` with "Class `Twin` is already defined elsewhere." | None new | `autoload.php` registers after Composer's loader |

## `Position`

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3521 | `__DIR__`, `__FILE__`, `__LINE__`, `__FUNCTION__`, `__METHOD__`, `__NAMESPACE__` and `__CLASS__` are removed | Works: each fails as a typo does | None new | `report_unsupported` |
| 3521 | Every other `__Something__` form is removed | Works: `__TRAIT__`, `__PROPERTY__`, `__COMPILER_HALT_OFFSET__`, `__Shop__`, the lowercase `__file__` and a method named `__Thing__` fail as a typo does | None new | `report_unsupported` |
| 3521 | `Position` is a standard library type, imported by default (section 23) | Works: a bare `Position` resolves to `Sharp.Position`, and a class of the file's namespace shadows it (#145's N16) | None new | `shadowable_default_classes` |
| 3521 | It has `file`, `directory`, `line`, `column` and `function` | Works: `ext/sharp/sharp.stub.php`'s `Sharp\Position`; the constructor derives `directory` | None new | |
| 3521 | `function` is the fully qualified dotted name | Works: `App.Pos.Here.fromStatic` | None new | `Lowering::enter` |
| 3553 | `Position.current()` in a body gives the position where it is written | Works: a static method (`Here.sharp:5:44 App.Pos.Here.fromStatic`), a method, an accessor (`App.Pos.Here.property`), a constructor (`App.Pos.Here.Here`) and a lambda (the member that holds it, `App.Pos.Here.fromLambda`). Columns count bytes from 1 | None new | `Lowering::current_position` |
| 3554 | As a parameter's default, `Position.current()` gives the caller's position | **Refused:** "A `Position.current()` default is not supported yet in PHP#." `position` line 3538 | Semantics, codex, bridge (decision 5), Jordan questions 1 and 2 | `sharp_arguments`, `current_position` |

## Compiling and running

| Line | Rule | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| 3555 | A `.sharp` file runs only after the checker accepts it; a type error stops it | Works: `Suppressed.sharp`'s type error leaves no `.sharpc`; loading it gives "app/Rules/Suppressed.sharp isn't compiled. Run vendor/bin/mago compile.", and with `sharp.compile_command` set "app/Rules/Suppressed.sharp has 1 error:\nline 8: Invalid return type for method `Suppressed.total`: expected `int`, but found `string`." | None new | `sharp_refuse_checked` |
| 3555 | A pragma, `ignore` or the baseline hide a warning, never an error | Works: a `// @mago-expect analysis:invalid-return-statement` pragma leaves the error and the refusal. `mago/tests/sharp.rs` covers all three under `analyze` and `compile` (`compile_refuses_a_sharp_file_whose_error_a_pragma_an_ignore_entry_or_a_baseline_entry_targets`) | None new | `Issue::can_be_suppressed_in` |
| 3557 | `mago compile` checks every `.sharp` file, vendor packages included | Works: an error in `vendor/heyjordanparker/php-sharp-composer/library/Sharp/Builtins.sharp` was reported and refused with "… comes from the Composer package heyjordanparker/php-sharp-composer. Update the package, or report the error to its maintainers." | None new | `CompileCommand::execute` |
| 3557 | It creates a missing `.lean` file beside a class that has a law, and never rewrites an existing one | Works: `class-law`'s `Money` got `app/Shared/Money.lean` with its theorem, and a line appended to it survived the next compile | None new (section 28.1) | `mago_sharp_lean::Lean::prove` |
| 3559, 3562 | An uncompiled file is refused, naming the fix | Works, word for word: "app/Shop/Fresh.sharp isn't compiled. Run vendor/bin/mago compile." | None new | `sharp_refuse`, `refusal_not_compiled.phpt` |
| 3563 | An out-of-date file is refused, naming the file that changed | Works, word for word: "app/Shop/Order.sharp is out of date (app/Shop/Money.sharp changed). Run vendor/bin/mago compile." | None new | `refusal_out_of_date_input.phpt` |
| 3564 | A file compiled for another engine is refused | Works, word for word, after one ABI byte of `Fresh.sharpc` changed: "app/Shop/Fresh.sharp was compiled for a different PHP# engine. Install the mago-sharp release that matches this engine." | None new | `refusal_different_engine.phpt` |
| 3567 | A deploy runs `vendor/bin/mago compile` before the code runs | Holds through the refusals above: a deploy that skips it stops at its first PHP# file | None new; Jordan question 3 | |
| 3568 | A type error or a broken structure rule stops the file, and so does a law without a proof, a gap, or a proof of a deleted law | Works: section 28's examples pass; `structure_rules.phpt` and `laws.phpt` | None new (section 28, #113) | |
| 3569 | A whole-project rule can lag in development, and is exact at deploy | Works by design: `sharp.compile_command` compiles on demand from a stale file, and `mago compile` checks every rule | None new | |

## Positions real code hits

Every row names the `.sharp` file and its line, never a compiled file, in four modes: no opcache; opcache shared memory with the tracing JIT on every hot counter; the opcache file cache, primed and then used; and preloading.

| Probe | Observed in the first three modes | Preloading |
|---|---|---|
| A checker error | `app/Pos/Magic.sharp:8:19:error - semantics: …` | n/a |
| A refusal at run time | "app/Rules/Suppressed.sharp has 1 error:\nline 8: …" | crashes |
| An exception | `OutOfRangeException at …/app/Fail/Thrower.sharp:14` | **crashes** before the first class loads |
| A stack frame | `#0 …/app/Fail/Thrower.sharp(9): App\Fail\Thrower->inner()` | crashes |
| An engine error | `DivisionByZeroError: Division by zero at …/app/Fail/Thrower.sharp:19` | crashes |
| A PHP warning | `hex2bin(): Hexadecimal input string must have an even length at …/app/Fail/Thrower.sharp:24` | crashes |

The bridge writes each node's `.sharp` line into the compiled file (`sharp_translate` sets `lineno` from it), and the engine compiles the file under its source's path, so no source map exists or is needed. The preload crash reproduces with a preload script that holds only `\Sharp\Internal\readModule(…/library/Sharp/Module.sharp)`, and not with one that holds only Composer's `ClassLoader` or only `requireNative` (decision 8).

## Crossings

| Crossing | Observed |
|---|---|
| Plain PHP calls a PHP# class through Composer | `500`, through PSR-4 and `dump-autoload --optimize` |
| A `.sharp` file is renamed before a compile | The new name "isn't compiled"; the old class is "no class" |
| …and after a compile | `.sharp/app/Shop/` holds `Renamed.sharpc` and no `Fresh.sharpc`: the build mirrors the sources. The class it still declares is unreachable by its name, which decision 2 turns into a compile error |
| A stale `.sharp/` build from an older compile | Each refusal above, word for word. Opcache drops a cached op array when its `.sharpc` goes out of date (`opcache_revision.phpt`) |
| An authoritative class map | Holds only files dumped before; a later file is "no class", as Composer documents for `--classmap-authoritative` |

## Existing work

| Work | What it holds | How this Slice uses it |
|---|---|---|
| php-sharp #24, #30 | `Position` replaces the magic constants; `function` is the dotted name | The spec text |
| mago-sharp #32, php-sharp #50 | The removed forms refused by name; `Position` added and run on the engine | `report_magic_constant`, `Sharp\Position` |
| mago-sharp #55 | `Position.current()` takes `__FILE__` where the compiled file runs | `current_position`, reused for the caller's position |
| mago-sharp #24, #37, #39; php-sharp #52, #48 | The `.sharpc` format, `mago compile`, each compiled file's inputs, and the three refusals and their texts | Every compiled-file row; no change |
| mago-sharp #45; php-sharp #35 | Errors in a `.sharp` file can't be suppressed | Line 3555; no change |
| mago-sharp #173, #95 | File-level code refused; capitalized namespaces | Beside the new rule in `sharp.rs` |
| php-sharp #8, #137 | The Composer plugin and its class map, an interface's `Defaults` trait in it | Decision 7 |
| mago-sharp #150, php-sharp #113 | Laws, proofs and structure rules | Lines 3557 and 3568; no change |
| php-sharp #145, open | Section 23: namespaces, imports and default names | `Position` as a default name. Of this Slice's files, #145 changes only `sharp.rs`, in three places outside `check_declarations` and the default's refusal, and `error.rs`, where it adds `UsesOnImportInSharp` and decision 6 adds one `Display` arm |
| php-sharp #121 | Modules, `readModule` | Decision 8's crash is in its code |

No branch or PR, merged or open, in either repository holds a type-per-file rule, a file-name rule, a caller-position default or a source map.

## Files

```text
sharp/                                                  the PR before this one (decision 3), guarded
├── bin/spec                                            build_file writes each file-level type to its own file
└── spec-self-test.md                                   an example whose setup declares two types
docs/architecture/spec-examples.md                      "How a fragment becomes a file" says it

ext/sharp/sharp.c                                       the fix PR (decision 8): sharp_container_read keeps no value its op array frees

docs/
├── architecture/slices/27-files.md                     new: this note
└── spec.md                                             one-body-per-method and sub-interface-defaults: one block per file (decision 4)
sharp/
├── baseline/spec/§27/one-type-per-file                 deleted
├── baseline/spec/§27/position                          deleted
├── composer/src/Plugin.php                             declaredClasses lists only the file's own type (decision 7)
└── mago/crates/
    ├── syntax/src/error.rs, parser/                    PhpSyntaxInSharp's message for <?php, raised at the file's first token (decision 6)
    ├── semantics/src/internal/checker/sharp.rs         check_declarations: one type, named like its file; the Position.current() default accepted
    ├── semantics/tests/sharp.rs                        one test per rule, forms as rows
    ├── codex/src/metadata/flags.rs                     MetadataFlags::CALLER_POSITION
    ├── codex/src/scanner/                              sets it on a Position.current() default
    ├── sharp-bridge/src/lower.rs                       sharp_arguments passes the caller's position; the default lowers to the declaration's
    ├── sharp-bridge/tests/lower.rs                     a row per call form
    └── */tests/                                        sources that declare several types split per file (decision 1)
Zend/tests/sharp/
├── position_caller.phpt                                new (decision 9)
├── position_caller_preload.phpt                        new (decision 9)
└── *.sharp                                             49 fixtures split or renamed (decision 1)
```

Every change runs only in a `.sharp` file: the new rule and parse error read only PHP# programs, the flag is set only in a `.sharp` file, and the class map change reads only `.sharp` files. A plain PHP project's analysis does not change.
