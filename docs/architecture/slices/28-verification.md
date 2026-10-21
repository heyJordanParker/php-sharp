# Slice 28: Verification

Spec section 28 and 28.1, with the parts of section 27 that section 28 reaches. This note is the design only. team-lead approves it before anyone builds.

**Superseded by [decision 057](../../decisions/057-laws-and-lean-files.md):** a law's proof is its body, in ordinary PHP# in the `.sharp` file, and no `.lean` file of the project is ever read. Every `Money.lean`, gap, missing-proof and deleted-law path this note describes is gone. Structure rules in Lean, which this Slice first designed, are gone too: a module's `imports` line (spec section 32) limits what its namespace imports, and the checker checks it with no Lean. This note keeps only the design that still stands: module laws, the law checker on a user's machine, and the editor.

## The answer first

Class laws work on master. Everything else section 28 promises does not:

- **Module laws are a parse error.** `module { law … }` in `Module.sharp` gives 13 "Parse error encountered during parsing" errors.
- **The editor shows nothing.** No editor code exists on master. `mago --help` lists no language server.

The Slice builds both in mago-sharp PRs and 1 php-sharp PR. It reuses master's `sharp-lean` crate for every Lean step, and ports the protocol layer of `upstream/feat/language-server`. It adds no crate and no command besides `mago language-server`.

## Decisions team-lead approves

Each one is drawn in full further down. These are the choices a builder cannot make alone.

1. **A module is a class-like named `<Namespace>\Module` in codex, flagged as a module.** The spec already names a module law `App.Tenant.Store.Module.receiptMatchesRefunds`, the name a law of a class `App.Tenant.Store.Module` gets. So every class-law path on master serves module laws unchanged: the scanner, the analyzer's purity check, `Reach`, `translate`, the proof file and the editor lens.
4. **A broken module law refuses `Module.sharp`.** That follows the class-law precedent: the law's own file is refused. Until section 32 gives `Module.sharp` a job at startup, nothing loads it, so a broken module law fails `mago compile` and every deploy, but a request in development still runs `Receipts.sharp`.
5. **`mago` installs Lean on first use.** When `lake` is not on the `PATH`, Mago downloads the pinned elan release into its cache folder and runs Lake through it, as the Composer wrapper downloads the pinned `mago` release on first use.
6. **The editor display ports upstream's language server layer** onto master's `mago-server`, and adds one lens. A VS Code client is a separate question for the Architect.

## Rulings

team-lead approved decisions 1, 4, 5 and 6 as written, with these rulings:

- **Open question 2 is closed: decision 4 stands.** A broken module law refuses `Module.sharp`, as a broken class law refuses its own file. That meets "a compile error that names it", and section 32 makes `Module.sharp` load at startup.
- **Lean and Mago own one list of module roots** (team-lead, round 4 of #171): `Init`, `Lean`, `Std`, `Lake`, `Code` and `Sharp`. No PSR-4 folder under them gets a link into the package. A law whose proof file sits in such a folder is not proven, with "`Code` is a name Lean or Mago keeps, so Lean files under the `Code\Evil\` root, such as code-evil/Names.lean, can't be built. Move them under one of your own namespaces."

## How the states were observed

Every state below comes from a `mago` built from #131's branch, `/Users/jordan/Developer/sharp-worktrees/5/mago-sharp/target/debug/mago`, run on scratch projects under `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-verification/`.

```sh
MAGO=/Users/jordan/Developer/sharp-worktrees/5/mago-sharp/target/debug/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-verification
compile() { env XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=emacs MAGO_LOG=error \
  $MAGO --no-version-check --colors never --threads 2 --workspace $P/$1 compile; }
```

Each project has `mago.toml` with `paths = ["app"]`, a `composer.json` mapping `App\` to `app/`, and these files:

- `p1`: spec section 28's `app/Shared/Money.sharp`, and the `Status` enum with its `…` filled in as `tests/compile.rs` fills it, beside an enum `Payment`.
- `p1-gap`: `p1` with a second law `addKeepsAmount` that does not hold, and the `refundedIsFinal` law deleted while `Status.lean` keeps its proof.
- `p2`: spec section 28's `app/Tenant/Store/Module.sharp` and `Module.lean`, with `Receipts.sharp` and `Refunds.sharp`.
- `p5`: a class `Clock` whose law reaches `time()`.
- `p6`: section 32's `module { singleton Mailer = SmtpMailer; }`, `Module.Production.sharp` with `module : App.Shop { }`, and `app/Other/Module.sharp` declaring `class Module`.

**The binary differs from master only where section 28 does not reach.** It was built at 19:17 from #131's branch, whose head `4df706a39` is master's merge `ffebf4cca`'s second parent. `git diff --stat 8c6cda9d8 ffebf4cca -- crates/sharp-lean crates/syntax crates/semantics crates/sharp-bridge crates/names crates/orchestrator crates/server src` shows only `crates/names/src/lib.rs` and `crates/sharp-bridge/src/lower.rs`, both collection-method changes. `git diff --stat ffebf4cca 4df706a39` shows 29 files in `crates/analyzer` and `crates/codex`, the collection and function-type effect work of #116 and #126. Master keeps the law purity check that `p5` hits: `crates/analyzer/src/effects/check.rs` `laws_must_be_pure` still prints "Laws hold only over pure code". mago-sharp's `master` has since moved to `9e99ae688`, and `git diff --stat ffebf4cca 9e99ae688` touches only pattern and `as` checks.

The engine part of each state is read from `Zend/tests/sharp/compile_command.phpt`, because no PHP# engine binary is built in any slot (`find /Users/jordan/Developer/sharp-worktrees -maxdepth 4 -path "*/sapi/cli/php"` finds none). That test shows the engine printing "`<file>` has N errors:" with one `line N: <message>` per error the checker reported, and "`<file>` isn't compiled. Run vendor/bin/mago compile." for a refused file. A law needs no engine change to reach the same refusal: it is one more error in the checker's report.

# Every rule of section 28, its state and its mechanism

The states are **works**, **refused** (the code is rejected, with the wrong or a generic message), **silently accepted** (code the spec refuses compiles), and **missing** (no code path exists at all).

## The opening of section 28

**"The checker verifies two kinds of facts before code runs."**

- **Structure: what code may depend on and do.** A module's `imports` line, spec section 32, which the checker checks.
- **Visibility and effects belong to the language.** Works. `compile p5` printed `app/Shared/Clock.sharp:7:28:error - impure-law: Law neverNegative reaches Clock.now, which calls time with no extern declaration. Laws hold only over pure code.`
  - Mechanism: none new. Reuses `crates/analyzer/src/effects/check.rs`.
- **Code-shape and style rules stay plugins in the checker.** Works. `src/commands/compile.rs` starts the configured analyzer plugins through `start_external_analyzer` before it checks any `.sharp` file.
  - Mechanism: none new.
- **Values: facts the code guarantees, written as laws with proofs.** Works for class laws. `compile p1` printed `Compiled 3 PHP# files into .sharp/.` and created `app/Shared/Money.lean` and `app/Shop/Status.lean`.
  - Mechanism: none new. Reuses `crates/sharp-lean`.

## A class states its laws in PHP#

"A class states its laws in PHP#, with `law`. A law's parameters range over every possible value. Its proofs go in a Lean file of the same name beside it, `Money.lean` beside `Money.sharp`, written in Lean 4 and checked by Lean."

```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;
}
```

Works. `compile p1` proved the law. The generated proof file is byte for byte the spec's 28.1 example without its comment line:

```lean
import Code.App.Shared.Money
open Sharp

theorem addKeepsCurrency : App.Shared.Money.addKeepsCurrency := by
  simp [App.Shared.Money.addKeepsCurrency, App.Shared.Money.add]
```

- Mechanism: none new. Parser `crates/syntax/src/parser/internal/class_like/law.rs`, translation `crates/sharp-lean/src/translate/mod.rs`, proof step `crates/sharp-lean/src/lean.rs`.

## A module states laws that span its classes

"A module states laws that span its classes in `Module.sharp` (section 32). Their proofs go in `Module.lean` in the same folder. Both files are optional."

```csharp
// app/Tenant/Store/Module.sharp
namespace App.Tenant.Store;

module
{
    law receiptMatchesRefunds(int paid, int refunded) => Receipts.refundable(paid, refunded) == Refunds.remaining(paid, refunded);
}
```

Refused with the wrong message. `compile p2` printed 13 lines of `app/Tenant/Store/Module.sharp:4:1:error - parse: Parse error encountered during parsing` and later columns, and never read `Module.lean`.

- Mechanism: parser, names, codex, analyzer, semantics, bridge, `sharp-lean` translation. Reuses every class-law path, because the module is scanned as the class-like `App\Tenant\Store\Module` (decision 1).
  - Parser: a contextual `module` keyword at statement level, after the `extern` precedent in `crates/syntax/src/parser/internal/statement.rs`. Its body holds `law` members, parsed by the existing `parse_law`.
  - Codex: `crates/codex/src/scanner/class_like.rs` scans the module as a class-like with the module flag, holding only laws.
  - Analyzer: analyzes each module law as `ClassLikeMember::Law` is analyzed in `crates/analyzer/src/statement/class_like/method.rs`, so `laws_must_be_pure` covers it.
  - Semantics: `crates/semantics/src/internal/checker/sharp.rs` (`check_slice`) allows the module only in `Module.sharp`.
  - Bridge: `crates/sharp-bridge/src/lower.rs` lowers the module to nothing, as it lowers a law today.
  - `sharp-lean`: `translate` handles the module statement, so the law becomes `@[law] def App.Tenant.Store.Module.receiptMatchesRefunds`, in the generated module `Code.App.Tenant.Store.Module`, and `proof_file` maps `Module.sharp` to `Module.lean`.

## Laws gate running

"A law without a proof, a gap, or a proof whose law was deleted is a compile error that names it (section 27)."

- **Laws gate running.** Works for class laws. `compile p1-gap` printed the two errors below, `Compiled 1 PHP# file into .sharp/. 2 PHP# files were refused`, and exit code 1. A refused file loses its `.sharpc`, which the engine refuses.
  - `app/Shared/Money.sharp:9:5:error - unproven-law: Law addKeepsAmount is not proven: app/Shared/Money.lean line 9 is a gap (sorry). Write the proof there, or change the law.`
  - `app/Shop/Status.sharp:3:13:error - non-existent-law: app/Shop/Status.lean line 4 proves App.Shop.Status.refundedIsFinal, which Status.sharp no longer states. Delete the proof, or restore the law.`
  - Module laws refuse `Module.sharp` (decision 4).

## Laws hold only over pure code

"Laws hold only over pure code (section 29). The checker translates pure PHP# code to Lean, and Lean's kernel checks the proofs."

Works. `compile p5` refused `Clock.sharp` with `impure-law`, and `compile p1` proved both laws with Lean.

- Mechanism: none new for classes. A module law gets the same check, because codex sees it as a class-like law.

## A state machine's laws

"A state machine gets its laws through a pure transition method on its status enum, so a law covers every state and event."

```csharp
public enum Status : string
{
    case Open = "open";
    case Paid = "paid";
    case Refunded = "refunded";

    public Status after(Payment e) => match (this) { … };                          // pure: the next status for every status and event
    law refundedIsFinal(Payment e) => Status.Refunded.after(e) == Status.Refunded;
}
```

Works, with the `…` filled in. `compile p1` proved `refundedIsFinal` and wrote `theorem refundedIsFinal : App.Shop.Status.refundedIsFinal := by` into `app/Shop/Status.lean`.

- Mechanism: none new. `tests/compile.rs` `compile_proves_spec_section_28_1_s_state_machine_law_over_an_enum_match` already covers it.

## 28.1 Lean files

"A class's proofs sit in a Lean file of the same name beside it. A namespace's module laws and their proofs sit in `Module.sharp` and `Module.lean` in its folder."

The tree, entry by entry:

```text
app/
├── Shared/
│   ├── Money.sharp              <- works (p1)
│   └── Money.lean               <- works: created by mago compile (p1)
└── Tenant/Store/
    ├── Module.sharp             <- refused: parse error (p2)
    ├── Module.lean              <- silently accepted: never read (p2)
    ├── Receipts.sharp           <- works as a plain class (p2)
    └── Refunds.sharp            <- works as a plain class (p2)
```

- Mechanism for `Module.lean`: the proof file is the Lean module master's `proof_module` already names, `App.Tenant.Store.Module` for `app/Tenant/Store/Module.lean`, inside the `Proofs` library that `.sharp/.lean/lakefile.toml` already links to each PSR-4 folder. `p1`'s generated lakefile shows that library with `globs = ["App.+"]`.

**"The checker created `Money.lean` and appended the proof `simp` found"** (the `Money.lean` block). Works (`compile p1`, file shown above).

**"`app/Tenant/Store/Module.lean` proves the module law. It imports `Code.App.Tenant.Store.Module` because `Module.sharp` states a law."**

```lean
-- app/Tenant/Store/Module.lean
import Code.App.Tenant.Store.Module
open Sharp

theorem receiptMatchesRefunds : App.Tenant.Store.Module.receiptMatchesRefunds := by
  simp [App.Tenant.Store.Module.receiptMatchesRefunds, App.Tenant.Store.Receipts.refundable, App.Tenant.Store.Refunds.remaining]
```

Silently accepted. `compile p2` reported only the parse errors of `Module.sharp`, and nothing about `Module.lean` importing a module that does not exist.

- Mechanism: module laws (above) generate `Code.App.Tenant.Store.Module`, importing `Sharp.Lean.Law`, `Sharp.Lean.String` and `Sharp.Lean.Interface`. So every Lean file beside PHP# code starts the same way, `import Code.<its own name>` and `open Sharp`.

**"The checker generates the Lean translation of pure code and the Lean statement of each law into `.sharp/`, never into the source tree."** Works. `p1` has `.sharp/.lean/Code/App/Shared/Money.lean`, and the source tree holds only the proof files.

**"A law's generated Lean statement is named by its class's full name and the law's name. A module law's is named by its namespace, `Module` and the law's name."** Works for classes (`@[law] def App.Shared.Money.addKeepsCurrency` in `p1`'s generated module). Refused for modules (`p2`).

- Mechanism: the module's class-like name is `<Namespace>\Module`, so `names::member` gives `App.Tenant.Store.Module.receiptMatchesRefunds` with no new code.

**"When a class or module has a law and no Lean file, the checker creates the file. For each new law, it appends a proof found by Lean's automatic steps (`simp`, `omega`, `decide`), or a marked gap when none is found. It never rewrites a proof that exists."** Works for classes. `compile p1-gap` appended this to the existing `Money.lean` and kept `addKeepsCurrency`'s proof:

```lean
-- PHP#: no automatic proof found
theorem addKeepsAmount : App.Shared.Money.addKeepsAmount := by
  sorry
```

Refused for modules (`p2`).

- Mechanism: none new. `Lean::check` in `crates/sharp-lean/src/lean.rs` serves the module once its translation exists.

**"A law without a proof, a gap, or a proof whose law was deleted is a compile error that names it."** Works for classes (`compile p1-gap`). Refused for modules (`p2`).

**"Names match exactly. Translated code keeps its PHP# names in Lean, with no prefix."** Works. `p1`'s generated module declares `structure App.Shared.Money`, `App.Shared.Money.__construct` and `App.Shared.Money.add`. Only the module carries `Code.`, and Lean code never names a module inside a declaration.

**"The editor shows, above each method, the laws that mention it."** Missing. `$MAGO --help` lists no language server, and `src/commands/` on master has none. The only editor code is on `upstream/feat/language-server`.

- Mechanism: editor. Ports upstream's protocol layer and adds a law lens (below).

## Section 27's bullets that section 28 reaches

- **"`mago compile` also creates a missing `.lean` file beside a class that has a law. It never rewrites an existing one."** Works (`compile p1`, `compile p1-gap`).
- **"A law without a proof, a gap, or a proof whose law was deleted stops the file from running."** Works (`compile p1-gap`).

# How the language server branch is reused

## `upstream/feat/language-server` (6 commits, 823 behind `origin/master`)

**The editor display builds on it.** Upstream splits the editor into a transport-free `mago-server` that answers in byte offsets, and a `tower-lsp-server` layer in `src/language_server/` that speaks LSP. Master's `crates/server` is that same `mago-server`, trimmed to the warm analysis core: its `lib.rs` keeps upstream's doc comment, and its `Server` has no editor methods. So the lens fits master's shape without a new crate.

**What it costs**, from `git diff --stat origin/master...upstream/feat/language-server`:

- The protocol layer `src/language_server/` is about 2,100 lines (backend 818, codec 403, state 305, capabilities 214, diagnostics 139, and the rest), with a 1,200-line test harness. The port keeps lifecycle, document sync, diagnostics and code lens, and drops rename.
- `crates/server/src/capabilities/` holds 2,600 lines of completion, hover, inlay hints and the rest. The port takes only `code_lens.rs` (38 lines) and the `CodeLensItem` domain value.
- Two new dependencies: `tower-lsp-server` 0.23 and `tokio` (`rt-multi-thread`, `io-std`).
- A port, not a merge: the branch is 823 commits behind and predates master's `mago-server`, so its `crates/server` conflicts file by file.
- Section 29's "the editor displays" each method's effects reuses the same layer later, with upstream's `hover.rs` or `inlay_hint.rs`.

The lens: `Server::get_lens(file)` returns one `CodeLensItem` above each method that at least one law mentions, listing those laws. "Mention" means the law's own expression references the method, read from the symbol references the analysis records under the law's symbol. Module laws appear the same way.

**An editor still needs a client.** Editors with a generic LSP client (Neovim, Helix, Zed) attach `vendor/bin/mago language-server` to `.sharp` files with a few lines of configuration. VS Code needs an extension that registers `.sharp` and starts the server. None exists. Shipping one is a new package and a Marketplace listing, so it is the Architect's call (open question 1).

# Lean on a user's machine

**Master today:** `crates/sharp-lean/src/package.rs` `Package::toolchain` writes `.sharp/.lean/lean-toolchain` and runs `lake --version`. With no `lake` on the `PATH`, Mago installs Lean itself (below), and when it cannot, every file that needs Lean is refused with `missing-lean`, which names what failed: "The law checker could not be installed into <Mago's cache>/elan: <reason>. Run vendor/bin/mago compile again to retry." Only CI installs elan (`.github/actions/setup-lean/action.yml`). Neither `heyjordanparker/mago-sharp`'s Composer package nor `sharp/docker/runtime/Dockerfile` ships Lean (`trace grep -i "elan|\blean\b" sharp .github` in php-sharp finds nothing).

**The precedent:** `composer/bin/mago` calls `ensure_binary` in `composer/src/internal.php`, which downloads the pinned release archive for the machine's target triple on first run, into a per-version folder, under a file lock. `crates/sharp-lean/src/library.rs` already builds the Lean runtime library once per Mago build in Mago's cache folder, under a lock. The `mago` binary already links `ureq`, `tar` and `flate2` for `mago self-update` (`src/updater/github.rs`, `src/updater/archive.rs`). `mago-sharp-lean` gains them as workspace dependencies. The engine never links `mago-sharp-lean`, since that crate depends on `mago-sharp-bridge` and not the reverse.

**The design:** Mago does the same for elan, from Rust, on first need.

- When `lake` is not on the `PATH`, `Package::toolchain` downloads `elan-<triple>.tar.gz` for the pinned elan version from `github.com/leanprover/elan/releases` into `<Mago's cache>/elan/`, under a lock, and runs `elan-init -y --no-modify-path --default-toolchain none` with `ELAN_HOME` set there. Lake then runs from `<Mago's cache>/elan/bin`, and elan fetches Lean 4.34.1 from the `lean-toolchain` file master already writes.
- A `lake` on the `PATH` wins, so CI's `setup-lean` and a user's own elan keep working.
- It runs only when a project has a law. A project without one downloads nothing, as `compile_needs_no_lean_for_a_project_with_no_law` requires.
- It serves every install path: Composer, the runtime image, and a binary installed by hand.

**What a user downloads:** `gh release view --repo leanprover/elan` lists `v4.2.4` with `elan-aarch64-apple-darwin.tar.gz` (2.2 MB), `elan-x86_64-apple-darwin.tar.gz` (2.3 MB), `elan-x86_64-unknown-linux-gnu.tar.gz` (5.0 MB) and `elan-aarch64-unknown-linux-gnu.tar.gz` (5.1 MB). `gh release view v4.34.1 --repo leanprover/lean4` lists the Lean archives elan then fetches at 562 to 582 MB per platform. That is a one-time download per machine, on the first compile that needs Lean.

**Platforms:** macOS on Apple silicon and Intel, and Linux on x86-64 and ARM64 with glibc. Lean has no musl build, and `elan-init.sh` refuses musl hosts, so Mago's musl build pins no elan. With no `lake` on the `PATH`, its `missing-lean` describes the build, never the host, and gives only advice true on every host it runs on, since the static musl binary runs both on glibc Linux, where Lean runs, and on Alpine, where neither Mago's glibc build nor Lean starts: "Laws need the law checker, and this musl build of the checker can't install it, because the law checker has no musl build. On glibc Linux, the checker's glibc build installs it itself." A source build for any other target with no pinned elan says "Laws need the law checker, and this <OS> <architecture> build of the checker can't install it." and nothing more, since Lean may have no build for that target either.

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
mago-sharp
├── crates/syntax/src/
│   ├── cst/cst/module.rs                 <- added: Module (keyword `module`, `{`, members, `}`); ModuleMember::Law
│   ├── cst/cst/statement.rs              <- Statement::Module added
│   └── parser/internal/module.rs         <- added: parse_module; refuses section 32 members as "not supported yet"
├── crates/codex/src/
│   ├── metadata/class_like.rs            <- a module flag on ClassLikeMetadata: the module is the class-like <Namespace>\Module
│   └── scanner/class_like.rs             <- scans `module { law … }` as that class-like, holding only laws
├── crates/semantics/src/internal/checker/sharp.rs
│                                         <- Module.sharp holds only its module; `module` elsewhere is an error
├── crates/sharp-bridge/src/lower.rs      <- lowers Statement::Module to nothing
├── crates/sharp-lean/src/
│   ├── package.rs                        <- OWNED_ROOTS, which linked_roots never links
│   ├── lean.rs                           <- Lean::prove renamed Lean::check(translations)
│   ├── library.rs                        <- installs the pinned elan into Mago's cache when lake is missing
│   └── translate/mod.rs                  <- translates Statement::Module
├── crates/server/src/
│   ├── capabilities/code_lens.rs         <- added, ported: Server::get_lens
│   └── domain.rs                         <- added, ported: CodeLensItem { offset, laws }
├── src/commands/language_server.rs       <- added, ported: `mago language-server`
└── src/language_server/                  <- added, ported from upstream: LSP over stdio, lifecycle, sync, codec, code lens

the user's project
├── app/<Namespace>/Module.sharp          <- spec: `module { law … }`, nothing else
├── app/<Namespace>/Module.lean           <- spec: module proofs; imports Code.<Namespace>.Module
└── .sharp/.lean/Code/<Namespace>/Module.lean
                                          <- generated for each folder whose Module.sharp states a law

Mago's cache folder (~/Library/Caches/mago, ~/.cache/mago, or $XDG_CACHE_HOME/mago)
└── elan/                                 <- added: ELAN_HOME of the elan Mago installs

php-sharp
└── Zend/tests/sharp/laws.phpt            <- added: a class whose law has a gap does not run
```

Not added: no second Lake package, no second runner executable.

# The PRs, in order

Each PR lands with tests on both sides where the engine is reached, and ends in something a developer runs.

1. **mago-sharp: module laws.** Parser, CST, codex module class-like, analyzer, `check_slice` rules for `Module.sharp`, bridge lowering, `translate` and proof files for modules, `NodeKind.php` regenerated with `scripts/regen-sdk-node-kinds.php`. Section 32 members refused as "not supported yet". Tests in `crates/syntax/tests/sharp.rs`, `crates/semantics/tests/sharp.rs`, `crates/sharp-bridge/tests/lower.rs` and `tests/compile.rs`.
   - Run: `mago compile` on spec section 28's `Module.sharp` creates `app/Tenant/Store/Module.lean` with the spec's proof and compiles. A module law that does not hold refuses `Module.sharp` with `unproven-law`.
2. **mago-sharp: Mago installs Lean.** `library.rs` downloads and initializes the pinned elan into Mago's cache when `lake` is missing. `lake_not_found`'s message names what failed instead of asking the user to install elan.
   - Run: `env PATH=/usr/bin:/bin mago compile` on `p1` installs elan, fetches Lean 4.34.1, and proves `addKeepsCurrency`.
3. **mago-sharp: the editor shows laws above methods.** The ported `src/language_server/`, `mago language-server`, `Server::get_lens` and `CodeLensItem`, with upstream's test harness for lifecycle and the lens.
   - Run: `mago language-server` attached to a `.sharp` file in an LSP client shows `law addKeepsCurrency` above `Money.add`, and `law receiptMatchesRefunds` above `Receipts.refundable` and `Refunds.remaining`.
4. **php-sharp: the engine refuses what the checker refuses.** Bump the pinned Mago commit, and add `Zend/tests/sharp/laws.phpt` through `sharp.compile_command`. Waits for PRs 1 and 2. The `MACOS` job has no `lake` on its `PATH`, so PR 2's Mago installs elan and Lean there on each run. CI caches neither, because restoring 3 GB is no faster than the download (spec-examples.md, "A law needs Lean"). Removing `elan` from the `sharp/docker/` image changes a guarded folder, so it lands in a pull request of its own.
   - Run: `sharp/bin/test Zend/tests/sharp/laws.phpt` shows the law's file refused with `CompileError`.

# What the Slice refuses until section 32 lands

The parser recognizes each form and reports it as "not supported yet", following `skip_via_clause` in `crates/syntax/src/parser/internal/class_like/property.rs`, which skips a `via` clause and pushes `ParseError::NotSupportedYetInSharp`. Today each is a generic parse error (`compile p6`: `app/Shop/Module.sharp:4:1:error - parse: Parse error encountered during parsing`).

- `singleton Mailer = SmtpMailer;`, `singleton Mailer for SendReceipt = QueueMailer;`, and the `scoped` and `transient` forms inside `module { }`: "A `singleton` service is not supported yet in PHP#.", and the same for `scoped` and `transient`.
- `module : App.Shop { … }`, in `Module.<Environment>.sharp` or in a test file: "A module that overrides another is not supported yet in PHP#."
- `module { }` in `Module.<Environment>.sharp`: "An environment module is not supported yet in PHP#."

The module block is a list of members. Section 32 adds its bindings as new `ModuleMember` cases beside `ModuleMember::Law`, and the override header as an optional parent after `module`, so nothing this Slice builds changes shape.

The Slice also refuses what giving `Module.sharp` a meaning requires, with errors, not "not supported yet":

- `module { }` in any file but `Module.sharp`: "A module is declared in its folder's Module.sharp."
- `Module.sharp` declaring a class, function or anything else besides its namespace, imports and one module: "Module.sharp holds only its folder's module." Today `compile p6` accepts `app/Other/Module.sharp` declaring `class Module` (`Compiled 1 PHP# file into .sharp/.`).

Section 32 rules that no syntax marks, so nothing exists to refuse until the container exists: a missing or mistyped service as a compile error, `Factory<T>`, PSR-11, scoped lifetimes, and `foreign` objects created in a module.

# Open questions for the Architect

1. **A VS Code client.** The language server serves any LSP editor. VS Code needs an extension that registers `.sharp` and starts `vendor/bin/mago language-server`, about a hundred lines, published to the Marketplace and Open VSX. Should it ship in this Slice, and in which repository?
2. **Module laws in development.** With decision 4, a broken module law fails `mago compile` and every deploy, but until section 32 a development request still runs `Receipts.sharp`. Refusing every file the law reaches instead would stop them now, but no class law does that today, and section 32 makes `Module.sharp` load at startup anyway.
