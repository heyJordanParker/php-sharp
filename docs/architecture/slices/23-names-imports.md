# Slice 23: Namespaces and imports, and keyword case

Spec section 23, and section 2's rule that keywords are lowercase. team-lead approved G1 to G8, the four engine test files and `ParseError::UsesOnImportInSharp`, with the answers below, which replace the proposals of this note wherever they differ.

## Answers in force

1. **Q1:** no. An alias exists only in a framework's runtime config, so `import Log;` names a class the checker sees, as in `import Illuminate.Support.Facades.Log;`. Its error says there is no class `Log` to import, with no special case for `log()`.
2. **Q2 and G5:** one source of truth. Every public class the standard library declares directly in `Sharp` is a default name. The list is generated from the library, and a test fails when the two disagree, in place of the checker guard. `ListMethods`, `MapMethods`, `SetMethods` and `IterableMethods` move to `Sharp\Internal`, in the engine, the prelude, the bridge and the tests.
3. **P1** still waits for Jordan, and master's behavior stays.

## The answer first

Section 23 already holds on master for every example and for most rule lines: dotted namespaces, capitalized namespace parts, calls and constants through a library's namespace, `global.`, the refused single-function import and bare call, shadowing a default name, `import … as`, one name per file, and `extern` through a renamed import. The engine and the Composer plugin run every crossing probed. The checker is where every gap is:

- **`keyword-case` fails because a miscased keyword hides every other PHP# error in its file.** `Return;` is a parse-level error ("Write the keyword `return` in lowercase."), and `checks_slice` runs no PHP# check on a file with a parse error, so line 99's `TRUE` is never reported. The tree the parser builds for `Return` is the tree of `return`, so nothing justifies the gate there.
- **Some miscased keywords get no suggestion:** `THIS`, `This`, `SUPER`, `SELF`, `Typeof(…)`, `Nameof(…)`, `Exit(…)`, `value IS int`, `value As int`, `Case Paid;` in an enum and `Public class` at file level. `Struct` gets its suggestion plus a second, generic parse error.
- **Four imports are silently accepted:** an import that names nothing (`import Nowhere.Missing;`), a namespace of classes (`import App.Shared.Schema;`, `import Sharp.Json;`) and a constant (`import Vendor.Pdf.DEFAULT_DPI;`). Line 2770 allows only a class, or a namespace of a plain PHP library's functions and constants.
- **A full name in code is refused, but the error names the import only for member chains.** In a type, `new`, `nameof`, `typeof`, `catch`, a class header, an attribute, an `extern` and `Pdf.Document`, the error is "Cannot find type `App.F.App.Order`", "Class `App` not found" or a bare parse error.
- **Six classes directly in `Sharp` are not default names:** `Behavior`, `ClassMembers`, `Dispatcher`, `ImmediateDispatcher`, `InvalidFields` and `Listening`. The name resolver keeps a hand-written list, while the analyzer's extension lookup already treats every class directly in `Sharp` as imported.
- **`import X uses Y;` is two generic parse errors,** which per `docs/architecture/spec-examples.md` satisfy only a `parse error` line.
- **A plain PHP class named like a PHP# namespace compiles.** `mago analyze` reports it on the `.php` file, and `mago compile` prints only `.sharp` files' errors, so nothing refuses it.
- **Two errors don't say what the spec says they say:** `Math.floor` without its import is "Method `Math.floor` does not exist." instead of naming the import, and a bare library constant `DEFAULT_DPI` is "Undefined constant" instead of naming `Pdf.DEFAULT_DPI`.

The bridge, the engine and the Composer plugin need no change. The Slice adds one engine test that pins section 23's runtime meaning in every opcache mode. Two questions decide what users write, and one item waits on Jordan.

## Rule by rule

The states are **works**, **fails example**, **silently accepted** (code the spec refuses compiles), **wrong error** (refused, but the error isn't the one the rule describes) and **parse error** (refused by "Parse error encountered during parsing", which satisfies only a `parse error` line). The layer is what the Slice changes: **parser** and **semantics** are `sharp/mago/crates/syntax` and `crates/semantics`, **names** is `crates/names`, **analyzer** is `crates/analyzer`. "none" means the rule holds and the Slice keeps it under test.

### Section 2: keyword case (lines 86 to 101)

| # | Line | Rule | State on master | Layer | Reuses |
|---|---|---|---|---|---|
| K1 | 86 | Keywords are lowercase only | works: `is_sharp_keyword` compares exactly | none | |
| K2 | 86 | `Self` is the one capitalized keyword | works: `public Self same()` compiles, `self` gets "PHP# has no `self`" | none | |
| K3 | 86, 93 | A capitalized keyword is an ordinary name: `class Event`, `class Struct` | works: `class Struct`, `Event`, `Return`, `If`, `Throw` compile, `Return.make()`, `If.make()`, `Return r = new Return();`, locals `Match`, `Event` and a parameter `Return` compile, and the engine creates `App\N\Return`, `App\N\If` and `App\N\Throw` | none | |
| K4 | 86, 99, 100 | Where no such name exists, the error suggests the lowercase keyword | **fails example** `keyword-case`: line 99's error is lost (G1). **Wrong error** for `THIS`, `This`, `SUPER` ("Undefined constant"), `SELF` ("Cannot find type"), `Typeof(…)`, `Nameof(…)` ("Function `global.Typeof` does not exist"), `Exit(1)` ("write `global.exit(…)`"), `Case Paid;` ("Enum `Cases` cannot have properties"). **Parse error** for `value IS int`, `value As int`, `Public class Heads`, and the second error of `public Struct Point` | semantics, parser | `MiscasedKeywordInSharp`, `miscased_construct_follows`, `check_constant_access` |

What already suggests the keyword: every statement head (`If`, `IF`, `RETURN`, `Return;`, `For`, `While`, `Do`, `Try`, `Catch`, `Finally`, `Else`, `Break`, `Continue`, `Throw`, `Yield`, `Emit`, `Let`, `LET`, `Const`, `Match (…)`, `NEW`), every member and type modifier (`Private`, `Protected`, `Readonly`, `Abstract`, `Virtual`, `Final`, `Internal`, `Lazy`, `Required`, `Static`, `Sealed`), every file-level head (`Namespace`, `Import`, `Extern`, `Event`, `public Interface`, `public Enum`), and the values `TRUE`, `True`, `FALSE`, `False`, `NULL`, `Null` ("Write `true` in lowercase."). The spec's annotation text, "no name TRUE exists; did you mean true?", is a paraphrase, and `sharp/bin/spec` doesn't compare it.

### Section 23 (lines 2760 to 2890)

| # | Line | Rule | State on master | Layer | Reuses |
|---|---|---|---|---|---|
| N1 | 2762 | Namespace parts are separated by `.`, and imports use `import` | works: `namespace-and-import` | none | |
| N2 | 2762 | Every part of a `namespace` line starts with a capital letter | works: `namespace App.p;` gives "`p` must start with a capital letter: PHP# capitalizes every namespace and every type except the built-in ones." An import keeps its letters (decision 69) | none | `check_capitalized` |
| N3 | 2770 | Full names appear only in `namespace` and `import` lines, so `.` in code is always member access | see N5 | analyzer, parser | |
| N4 | 2770 | The last part of an import is a class, or the namespace of a plain PHP library's functions and constants | **silently accepted**: `import Nowhere.Missing;`, `import App.Shared.Schema;` (classes only), `import Sharp.Json;`, `import Vendor.Pdf.DEFAULT_DPI;`. A single function is refused (N9) | analyzer | `check_sharp_import`, `report_non_existent_import` |
| N5 | 2770 | A full class name in code is a compile error that names the import to add | works for member chains: `App.Shared.Schema.Entities.DatabaseEntity.class` and `x is App.Order` give "Full names appear only in `import` lines: add `import App.Order;` and write `Order`." **Wrong error** for a parameter, return, property or generic argument type ("Cannot find type `App.F.App.Order`"), `new App.Order()` ("Class `App` not found"), `nameof(App.Order)`, `typeof(App.Order)`, `catch (App.NotFound e)`, `Vendor.Pdf.DEFAULT_DPI` and `Vendor.Pdf.render(h)` ("`Vendor` names no class, and no namespace this file imports."), `Pdf.Document.blank()` ("Undefined constant `Pdf.Document`") and `new Pdf.Document()`. **Parse error** for a local's type `App.Order o = …`, a header `: App.Shared.Schema.Entities.DatabaseEntity`, an attribute `[App.Searchable]` and `extern Vendor.Pdf.render uses Files;` | analyzer, parser | `report_full_name` |
| N6 | 2770 | A module header names a namespace: `module : App.Shop` | works: section 32's examples | none | |
| N7 | 2772 | A library's function is called through its namespace: `Pdf.render(html)` | works: `library-functions`, and the engine printed `pdf(x)` | none | |
| N8 | 2772 | A root function is called as `global.now()` | works: `global.strlen(text)` | none | |
| N9 | 2772, 2800 | Importing a single function is a compile error | works: `library-functions` line 2800 | none | |
| N10 | 2772, 2791 | A bare call is a compile error that names `Pdf.render(html)` | works: `library-functions` line 2791 | none | |
| N11 | 2805 | A plain PHP constant is read through its namespace: `global.PHP_EOL`, `Pdf.DEFAULT_DPI` | works: `library-constants`, and the engine printed `300` | none | |
| N12 | 2805, 2826 | A bare uppercase name is the code's own constant: bare `PHP_EOL` is an error that says `global.PHP_EOL` | works for `PHP_EOL`. **Wrong error** for a library's constant after its namespace's import: bare `DEFAULT_DPI` is "Undefined constant: `DEFAULT_DPI`." | analyzer | the `global.PHP_EOL` help in `constant_access.rs` |
| N13 | 2830 | An import never carries `uses` | **parse error** ×2 for `import Vendor.Pdf uses Files;` | parser | the `*InSharp` parse errors |
| N14 | 2832 | The standard library lives under `Sharp`, with `Text`, `Math`, `Json`, `IO`, `Time`, `Net` and `Data` | works: the seven folders of `sharp/composer/library/Sharp/` | none | |
| N15 | 2834 | Only `Sharp` itself is imported by default, with the extensions on `string`, `int`, `float`, `List`, `Map` and `Set`: bare `Int`, bare `Key`, `name.trim()` | works for `Int`, `Key`, `Position`, `Events`, `Environment`, `List`, `Map`, `name.trim()`. **Wrong error** ("Cannot find type") for `Behavior`, `ClassMembers`, `Dispatcher`, `ImmediateDispatcher`, `InvalidFields` and `Listening`. The extension rule holds through `is_imported_by_default` (`the_standard_library_extensions_on_the_default_types_need_no_import`) | names, semantics | `shadowable_default_classes`, `default_class` (mago-sharp #179) |
| N16 | 2834 | A class the file declares or imports, or one of its namespace, shadows the default one | works: `own-namespace-shadows`; a plain PHP `App\S\Environment` shadows in `App.S`; `import Acme.Position;` of a plain PHP class shadows; `import Cache.Key;` makes `[Key(…)]` name `Cache.Key`; the engine printed `production` for `Release.stage()` through Composer | none | |
| N17 | 2859, 2870 | A static class of a standard library namespace needs its import: `Math.max(a, b)` without it is a compile error, "Math is not imported" | refused, **wrong error**: "Method `Math.floor` does not exist." and "Method `Json.encode` does not exist." | analyzer | the import help of `resolver/extension.rs` and of `match-not-exhaustive` |
| N18 | 2873 | `import X.Y as Z;` renames in this file only, as PHP's `use X\Y as Z;` | works: `import-rename`; another file's `Paper` is "Cannot find type"; the original `Document` is unbound after the rename; the engine ran `Cash.of(250)`, `List<Cash>`, `value is Cash` and `new Paper("invoice")`. The bridge writes every class by its full name and drops the import, which gives `use … as`'s meaning | none | |
| N19 | 2887 | An imported name is used once per file: two imports of one name, or an import named like a declared class, are errors | works: `import App.Order;` twice, `import Cache.Key as Order;` then `import App.Order;`, `import … as Duplicates` in `Duplicates.sharp` | none | `check_declarations` |
| N20 | 2888 | A rename changes the name, not the import; an `extern` through the new name declares the same function | works: `extern Printing.render` and `extern Pdf.render` give `duplicate-extern` | none | |
| N21 | 2890 | A class can't share its full name with a namespace; `Store/Store.sharp` is allowed; plain PHP classes count | works for PHP#: `Store.sharp` beside `Store/` is "`App.P.Store` is both a class and a namespace: rename the class or the folder.", and `Store/Store.sharp` compiles. **Silently accepted** for a plain PHP class: `Old.php` beside `Old/Fresh.sharp` compiles, because the error lands on the `.php` file, which `mago compile` never prints | analyzer | `report_class_like_named_as_a_namespace` |

### Crossings

| # | Crossing | State on master |
|---|---|---|
| C1 | A PHP# file imports a plain PHP class, function and constant | works: `import Vendor.Pdf.Document as Paper;`, `Pdf.render`, `Pdf.DEFAULT_DPI`, `Stripe.StripeClient`, checked and run |
| C2 | Plain PHP uses a PHP# class through Composer's autoloader | works, PSR-4 and `dump-autoload --optimize`: `new App\Probe()`, `App\Shop\Money::of(7)`, `new \App\Return()` |
| C3 | An import collides with a default name | works: `import Cache.Key;` shadows `Key`, so `[Key("cache_key")]` names `Cache.Key` and is "not declared with `#[Attribute]`", the error the spec renames around |
| C4 | A Laravel facade imported by its alias, `import Log;` | **wrong error**: "An import names a class or a namespace, not a function: call `global.Log(…)`.", because PHP's `log()` matches ignoring case, then "Function `Log.info` does not exist." Question 1 |

### Waiting on Jordan

- **P1. A PHP# override of a plain PHP method whose parameter is named after a PHP# keyword.** `handle(Object event)` over `handle(object $event)` is "`event` is a PHP# keyword, so it can't name a parameter.", and `handle(Object happened)` is "Parameter #1 of `Renamed.handle` is named `happened` but parent `Listener.handle` names it `event`". The Slice keeps both.

## Mechanism

Each Problem below lists its rows, its change and the code it reuses. A test per rule holds every form as a row, and each row is seen failing before its code.

### G1. A miscased keyword stops hiding the file's other errors (K4, `keyword-case`)

`crates/semantics/src/internal/mod.rs`'s `checks_slice` runs PHP# checks when every parse error of the file is `ParseError::MiscasedKeywordInSharp`. That error comes from `read_miscased_keyword`, which reads the token as the lowercase keyword, so the tree is the tree of the correct code. The gate's reason (`623b29626c`: a node the parser rejected must not get a second error) doesn't apply. `keyword-case` then reports line 99 and line 100. The bridge's `check` in `crates/sharp-bridge/src/lower/checked.rs` takes the same predicate, so `mago compile` prints the parse errors and the checker's errors together.

The alternative, reading `Return;` as a name so that it is no parse error, fixes only the one form. The gate change covers `If (TRUE) { }` and every other mix.

### G2. Every miscased keyword suggests the keyword (K4)

- **Values and calls:** "where no such name exists" needs the codebase, so the analyzer decides it. `report_keyword_in_another_case` in `crates/analyzer/src/resolver/class_name.rs` runs where a class, a type or a constant names nothing, and `FunctionCall::analyze` runs it for a bare call: `THIS`, `This`, `SUPER`, `SELF`, `Typeof(…)`, `Nameof(…)` and `Exit(…)` get `MiscasedKeywordInSharp`'s message, as "Write the keyword `this` in lowercase." `Self` gets "Write the keyword `Self` with a capital S and the rest in lowercase." A class named `This` keeps the name.
- **Infix:** `is` and `as` in another case after an operand read as the operator with the same error, in `is_at_is_or_as` in `parser/internal/pattern.rs`.
- **Members and file heads:** `Case` before a name in an enum reads as `case`, and a miscased modifier at file level followed by a modifier or a type keyword reads as the modifier, so `Public class` is one error, both in `parser/internal/common.rs`. `parse_modified_class_like_with_attributes` reads a miscased type keyword first, so `Struct` gives only its own error.

### G3. An import names a class, or a namespace of functions and constants (N4, C4)

In a `.sharp` file, `Statement::Use` in `crates/analyzer/src/statement/mod.rs` checks every import, whatever `check_use_statements` says, through `check_sharp_import` in `use_statement.rs`:

- a class-like, or a standard effect such as `Sharp.Mail`, which an import renames: compiles.
- a namespace that holds a plain PHP function or constant directly: compiles. The check scans `CodebaseMetadata::function_likes` and `constants`, which are public fields, so no new public method.
- a function whose last part starts in lowercase: today's `function-import` error.
- a constant: `constant-import`, a new issue code beside `function-import`, "An import names a class or a namespace, not a constant: import `Vendor.Pdf`, and read `Pdf.DEFAULT_DPI`."
- a namespace of classes only: `class-namespace-import`, a new issue code, "`App.Shared.Schema` holds classes only, and an import names a class or a namespace of functions: import the class, as in `import App.Shared.Schema.Column;` or `import App.Shared.Schema.Entity;`", every class of the namespace in name order. The list leaves out a module's class and every default name. A namespace with nothing left, as `import Sharp;`, gets "`Sharp` holds only default names, which every file names with no import: remove the import."
- nothing: `non-existent-use-import`, "Imported class, interface, trait, or enum `Nowhere.Missing` does not exist." A capitalized last part never gets the function message, so `import Log;` gets this one (Q1).

`scripts/regen-analyzer-issue-codes.php` lists the two new codes, and `crates/analyzer/src/code.rs` is regenerated from it. The general `check_use_statements` pass skips `.sharp` files, so an import gets one error. Bridge tests whose template imported `Lib.Calc` with no `Lib\Calc` declared now import it only when the code names `Calc`.

### G4. A full name anywhere in code names its import (N3, N5)

`report_full_class_name` in `crates/analyzer/src/resolver/class_name.rs` writes the refusal for a dotted name that is a class-like: "Full names appear only in `import` lines: add `import App.Order;` and write `Order`." A name the binder resolves against the file's namespace is tried as written too, so `App.Shop.Order` inside `App` names `App.Shop.Order`. `report_unknown_sharp_class` calls it wherever a missing class is reported. For a short name it then tries "not imported" (G8) when exactly one class-like has that short name, and the keyword (G2) only when none has it, so a class named `Event` or `Override` is never told to write a keyword:

- `report_undefined_type` (`statement/function_like/mod.rs`): parameter, return, property, local and generic argument types, and an `on` clause's events (`statement/class_like/listener.rs`).
- `new` (`expression/instantiation.rs`), `catch` (`statement/try.rs`), attributes (`statement/attributes/mod.rs`), a class header (`statement/class_like/mod.rs`), `report_non_existent_method` (`resolver/method.rs`) and `report_non_existent_class_like`, which `nameof`, `typeof`, `is`, `as` and a `match` type pattern reach.
- `report_dotted_types` (`statement/class_like/enum_case.rs`): a dotted type that names a class relative to the file's namespace, as `Shop.Order` inside `App`, and no case of an enum.

`report_full_name` in `expression/access/mod.rs` handles member chains. Its root may be a constant read, a name, or the class of a `new` without arguments. An imported root starts from its imported name, so `Pdf.Document.blank()` after `import Vendor.Pdf;` names `import Vendor.Pdf.Document;`. A root the file doesn't import starts from the name as written, then from the file's namespace, so `Shop.Order.make()` and `new Shop.Order()` inside `App` name `import App.Shop.Order;`. A function or constant read through a namespace written in two or more parts is refused with "add `import Vendor.Pdf;` and write `Pdf.render`". `check_extern` refuses an `extern` target of three or more parts.

A refused full name reports nothing more. A call or a member read through it has the error type, and so has a pattern's variable after `is` or a `match` arm tests it, and so has `typeof` of it, in `class_value` in `expression/type_of.rs`. A plain PHP file's `Missing::class` keeps its issues. A `catch` of it catches every `Throwable`, so the `try` reports no uncaught exception. A PHP# `instanceof` of no class narrows to the error type in `expression/binary/mod.rs`, so `App.Shop.Order order => order.total` is one error.

The parser reads a dotted name where it stopped: a type of any length (`peek_dotted_name` in `type_hint.rs`), a local's type at a statement's head (`is_at_dotted_typed_local`), the class before a member in `nameof`, an attribute's name, an `extern` target, a `match` or `is` type pattern of three or more parts (`is_at_dotted_type_pattern` in `pattern.rs`; two parts stay a case value, as `Status.Open`), and an `on` clause's events. `Identifier::Dotted` already exists. `On.events` holds `Identifier`s in place of `LocalIdentifier`s, so a dotted event is a name, and the analyzer refuses it before the bridge sees it.

The semantic checks pass the rest to the analyzer. `is_slice_bound` accepts a dotted bound, as `<T : App.Shop.Shape>`. `check_instantiation` leaves a `new` without arguments that a member chain reads through, as `new App.Shop.Order()`, which parses as the named constructor `Shop` of `App` and a call of `Order` on it. Only the codebase tells it from a member, so `report_full_name` refuses it, as a full name or as "This `new` without arguments is not supported yet in PHP#."

### G5. Every class directly in `Sharp` is a default name (N15)

The name resolver runs before the codebase exists, so it can't read the library when it runs. The list is generated at build time instead, per Q2:

- `sharp/mago/scripts/regen-sharp-classes.php <library>` reads every public class-like the library's `Sharp/*.sharp` files declare directly in `Sharp`, and the prelude's `namespace Sharp` block, and writes `crates/names/src/sharp_classes.rs`. The Justfile recipe `regen-sharp-classes` runs it.
- `default_classes()` in `crates/names/src/lib.rs` is `NAMED_EFFECTS` plus `SHARP_CLASSES`, and every default name is shadowable but `Error`, which `fixed_default_class` from the Result Slice always names `Sharp\Error`. `NAMED_EFFECTS` moves from the analyzer's `effects/mod.rs` to `mago_names`, its one owner.
- `the_default_class_likes_are_generated_from_the_standard_library_and_the_prelude` in `names/tests/binder.rs` runs the script and fails when its output differs from the file.

The prelude's `ListMethods`, `MapMethods`, `SetMethods` and `IterableMethods` move to `namespace Sharp\Internal`, with every reference in the analyzer, codex, the bridge and the tests. The engine names none of them. `Listening` stays in `Sharp`, so it is a default name. The `feat/result` Slice adds `Result` and `Error` to the library, and the script adds them on its next run.

### G6. `import X uses Y;` is one error that says why (N13)

The parser's import reader in `crates/syntax/src/parser/internal/use.rs` reads `uses` and the names after it, and reports a new `ParseError` variant, "An import never carries `uses`: declare the library's effect once, with `extern`.", in the shape of `PhpLambdaInSharp` and `QualifiedNameInSharp`. It is a public enum variant, so it is listed under Architecture.

### G7. A plain PHP class named like a PHP# namespace refuses the PHP# file (N21)

`report_namespace_named_as_a_plain_php_class_like` in `crates/analyzer/src/statement/class_like/mod.rs` runs beside `report_class_like_named_as_a_namespace`: a PHP# class-like whose namespace, or a namespace above it, is a plain PHP class gets "`App.Tenant.Store` is both a plain PHP class and a namespace: rename the class or the folder." `mago compile` then refuses it. The warm path in `incremental_analysis.rs` also re-analyzes a file whose class-like lies in the namespace a class-like scanned again names, so `a_compile_after_the_folder_named_like_a_class_gains_or_loses_a_class_matches_a_clean_compile` gains three rows and matches a clean compile with and without `diff`.

### G8. The two errors that don't say what the spec says (N12, N17)

- **Not imported:** `report_not_imported` in `resolver/class_name.rs`, reached through `report_unknown_sharp_class` (G4): a short name that names exactly one class-like of the codebase gets "`Math` is not imported.", with the import to add as help from `sharp_missing_imports`. A class of `Sharp.Internal` gets no import outside the standard library, which alone imports it, so a bare `ListMethods` keeps "Cannot find type". `is_sharp_internal` in `utils/names.rs` is the one test, which `check_internal_import` uses too.
- **Bare library constant:** in `expression/constant_access.rs`, a bare uppercase name that names no constant of the code, while a namespace the file imports holds one of that name, gets "Read the library's constant through its namespace: write `Pdf.DEFAULT_DPI`."

### What doesn't change

- **Bridge:** imports are dropped and names written in full (`Lowering::file_statement`), which is the meaning of `use … as`. Only the collection classes' new namespace and G1's predicate change.
- **Engine:** no change. The new engine test pins N7, N11, N16, N18 and K3 at runtime. Its plain PHP library is `harness/Names.inc`, where `mago compile` reads plain PHP. PHP's preloading keeps no constant, so the preload test defines the library's constant in the request, as a Composer `files` entry would.
- **Composer plugin:** `autoload.php` and `Plugin::declaredClasses` found `App\Return`, `App\Shop\Money` and `App\Probe` through PSR-4 and through the optimized class map. No change.
- **`sharp/bin/spec` and the harness:** no change.
- **Plain PHP:** every change runs only in a `.sharp` file, except G7's PHP# side, which reads a plain PHP class and reports on the PHP# file.

## Files

```text
docs/architecture/slices/23-names-imports.md          new: this note
sharp/baseline/spec/§2/keyword-case                   deleted: the example passes (G1)
sharp/mago/
├── Justfile                                           the regen-sharp-classes recipe (G5)
├── scripts/regen-sharp-classes.php                    new: writes the default class list from the library (G5)
├── scripts/regen-analyzer-issue-codes.php             the two new issue codes (G3)
└── crates/
    ├── syntax/src/
    │   ├── error.rs                                   new variant for `uses` on an import (G6)
    │   ├── cst/cst/class_like/method.rs, cst/node.rs,
    │   │   walker/mod.rs                              `On.events` holds names (G4)
    │   └── parser/internal/
    │       ├── common.rs, class_like/mod.rs           Case, a file-level modifier and Struct read as keywords (G2)
    │       ├── pattern.rs                             IS and As read as is and as (G2), a long dotted type pattern (G4)
    │       ├── use.rs                                 `uses` after an import is one named error (G6)
    │       └── type_hint.rs, local_declaration.rs,
    │           name_of.rs, attribute.rs, extern.rs,
    │           class_like/method.rs                   a dotted name parses where it stopped (G4)
    ├── hir/src/lower/error/mod.rs                     lowers the new variant (G6)
    ├── semantics/src/internal/mod.rs                  checks_slice runs past a miscased keyword (G1)
    ├── semantics/src/internal/checker/sharp.rs        a dotted bound and a `new` a chain reads through pass on (G4)
    ├── sharp-bridge/src/lower/
    │   ├── checked.rs                                 refuses with every error past a miscased keyword (G1)
    │   └── types.rs                                   the collection classes in Sharp\Internal (G5)
    ├── names/src/
    │   ├── lib.rs                                     default_classes and NAMED_EFFECTS (G5)
    │   ├── sharp_classes.rs                           new, generated: the classes directly in Sharp (G5)
    │   └── internal/walker.rs                         resolves through default_classes (G5), dotted events (G4)
    ├── prelude/assets/extensions/sharp.php            the collection classes move to Sharp\Internal (G5)
    ├── codex/src/metadata/mod.rs                      the collection classes in Sharp\Internal (G5)
    ├── analyzer/Cargo.toml                            mago-semantics as a dev-dependency, so a test runs both checks
    ├── analyzer/src/
    │   ├── resolver/class_name.rs                     full class names, miscased keywords, not imported (G2, G4, G8)
    │   ├── expression/access/mod.rs, call/mod.rs,
    │   │   instantiation.rs, call/function_call.rs    member chains, `new` and bare calls (G2, G4)
    │   ├── expression/binary/mod.rs, type_of.rs       a test or `typeof` of no class has the error type (G4)
    │   ├── statement/function_like/mod.rs, try.rs,
    │   │   attributes/mod.rs, resolver/method.rs,
    │   │   class_like/enum_case.rs, listener.rs       a missing class names its import (G4, G8)
    │   ├── statement/mod.rs, use_statement.rs         every PHP# import is checked (G3)
    │   ├── code.rs                                    constant-import and class-namespace-import (G3)
    │   ├── expression/constant_access.rs              a bare library constant names Pdf.DEFAULT_DPI (G8)
    │   ├── statement/class_like/mod.rs                the class-and-namespace rule from the PHP# side (G7)
    │   ├── effects/                                   NAMED_EFFECTS from mago_names; a long extern target (G4, G5)
    │   └── utils/names.rs, invocation/
    │       return_type_fetcher.rs                     the collection classes in Sharp\Internal (G5), is_sharp_internal (G8)
    ├── orchestrator/src/service/
    │   └── incremental_analysis.rs                    the warm path re-analyzes a namespace's files (G7)
    ├── syntax/tests/sharp.rs, semantics/tests/sharp.rs, names/tests/binder.rs, analyzer/tests/sharp.rs,
    │   sharp-bridge/tests/lower.rs, orchestrator/…/compile.rs   one test per rule, forms as rows
    └── ../tests/compile.rs                            a structure rule's imports name classes the project declares
Zend/tests/sharp/
├── NamesImports.sharp                                 new: renamed imports, shadowed defaults, keyword-named classes,
│                                                      a library's function and constant
├── harness/Names.inc                                  new: the plain PHP library
├── names_imports.inc                                  new: the driver both tests run
├── names_imports.phpt                                 new: runs in every mode sharp/bin/test runs
└── names_imports_preload.phpt                         new: the same under opcache.preload
```

## Architecture team-lead approves

1. **One new `ParseError` variant for `uses` on an import** (G6). Names, each in its call `ParseError::…(span)`:
   - `UsesOnImportInSharp`: what is written where, as `QualifiedNameInSharp` is. Recommended.
   - `ImportUsesInSharp`: the same, shorter, but reads as "imports uses".
   - `ImportEffectInSharp`: names the meaning, but the line never names an effect class unless it does.
2. **The four engine test files** under `Zend/tests/sharp/`, and the plain PHP library `harness/Names.inc`.
3. **The generated default names** (G5, Q2): `scripts/regen-sharp-classes.php`, `crates/names/src/sharp_classes.rs`, the Justfile recipe, and `mago_names::NAMED_EFFECTS`, which replaces the analyzer's own constant.

Everything else changes function bodies and `pub(crate)` items.

## Questions for Jordan

Each one decides what users write. The note recommends and decides none.

1. **Can a PHP# file import a Laravel facade by its alias, `import Log;`?** The alias is a class only while the app runs: Laravel's `AliasLoader` calls `class_alias` on first use. The checker sees no class `Log`. Recommended: no. An import names a class the checker sees, so the file writes `import Illuminate.Support.Facades.Log;`, and `import Log;` is "Imported class `Log` does not exist." This matches section 29: "PHP# ships declarations for no framework". The alternative reads `config/app.php`'s `aliases` and `Facade::defaultAliases()`, which is code for one framework.
2. **Are the prelude's helper classes default names?** The prelude declares `ListMethods`, `MapMethods`, `SetMethods` and `IterableMethods` directly in `Sharp`, and line 2834 imports all of `Sharp`. Today a bare `ListMethods` is "Cannot find type". Recommended: they stay unnamed in PHP# code, as the engine's implementation of the collection methods, and G5's guard covers `.sharp` library files only. The alternative makes them default names, so `ListMethods.map(…)` compiles.

## Existing work

Every open and closed PR of php-sharp and of `heyJordanParker/mago-sharp` was listed (`gh pr list --state all`), and php-sharp's commits since 2026-10-01 were searched by message on every branch for imports, default names, keywords, full names and namespaces.

- **mago-sharp #179, `feat/same-namespace-names`, merged.** `shadowable_default_classes`, `default_class`, `NamespaceClasses` and the second scan pass. G5 completes its list.
- **mago-sharp #78, `feat/sharp-import-alias`, and #108, `fix/sharp-import-help-alias`, merged.** `import … as`, its checks in `check_declarations`, and the help that writes an aliased import. G8 reuses the help.
- **mago-sharp #95 and #90, capitalized namespaces and types, merged.** `check_capitalized`, N2.
- **mago-sharp commit `76bc6c25c8`, "a keyword in another case is one error that writes it in lowercase", merged.** `MiscasedKeywordInSharp`, `miscased_construct_follows`, and `TRUE` in `check_constant_access`. G1 and G2 build on it.
- **php-sharp #124, `feat/plain-php-calls`, merged.** Calls and constants through a namespace, `check_sharp_import`'s function refusal, `bare-function-call`. N7 to N12.
- **mago-sharp #177, `fix/effect-names`, merged.** An imported class named like an effect. No change.
- **php-sharp #141, `feat/built-in-types`, merged.** The naming warnings, which cover namespace parts.
- **`feat/result`, in progress (slot 2).** Its note, `docs/architecture/slices/31-result.md` on that branch, makes `Error` a default name nothing shadows and refuses an import whose last part is `Error`, and adds `Result` to `shadowable_default_classes`. This Slice leaves both to it and merges the list.
- **php-sharp #142, `feat/generics-rest`, open.** Its failing `generic-method-inference` is N21 at work: the example declares `App.Cache` beside the harness folder `app/Cache/`. Section 11 owns the fix.
- `origin/experimental/namespaces` is php-src's own 2000s branch, not PHP# work.

## How the states were observed

- **mago:** release, built from master `0b98cd4a46` with `CARGO_PROFILE_RELEASE_LTO=off`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 1.
- **Engine:** `sharp/bin/build` at `0b98cd4a46`, PHP 8.5.11 NTS DEBUG, without opcache.
- **Spec run:** `sharp/bin/spec` with that pair: "Checked 201 examples, ignored 1, and 16 fail", equal to `sharp/baseline/spec`. Section 23's six examples pass. Section 2 fails only `keyword-case`:

  ```text
  FAIL §2 keyword-case (docs/spec.md:89)
    docs/spec.md:99 expects a compile error, and the checker accepts it
  ```

- **Probes:** each project lays out `sharp/harness/` and the standard library as `sharp/bin/spec` does, and `mago compile` reported with `MAGO_REPORTING_FORMAT=emacs`. Each rule's forms sit in files of their own, since a parse error in a file hides that file's PHP# errors (G1). The crossings ran in a Composer project that installs `sharp/composer` from its path, with a plain PHP library under `Vendor\Pdf` and a stub `Illuminate\Support\Facades\Log` imported by its full name, through PSR-4 and through `composer dump-autoload --optimize`. C4's `import Log;` was probed with the checker only, since it doesn't compile. Its run printed:

  ```text
  ["250","2","true","invoice","pdf(x)","300","true"]
  production
  7
  ["logged"]
  a class named Return
  ```
