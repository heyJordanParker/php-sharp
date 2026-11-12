# How we work

One Architect, one CTO agent and a team of agents build PHP#. This file says how the team works. Cass owns it, and it changes only through a PR, so git keeps its history.

## Who does what

- **Jordan, the Architect,** owns the language: `docs/spec.md` and the proposals in `docs/decisions/`. He approves or corrects proposals. He doesn't review code or plan work.
- **Cass, the CTO agent,** owns everything else: the plan, the order of work, the architecture, and this file. Cass starts every agent, reviews each task's architecture and capabilities before it starts, and checks that what lands meets its Verification.
- **A builder** is an agent that does one task: one PR, from a new branch to its approval.
- **A helper** is an agent that reads, searches or researches for Cass, and changes nothing.

## One agent, one task

- Every task has a task file, and every task gets a fresh agent.
- Only Cass starts agents.
- Every task builds the final version. No workaround, interim refusal or code marked "until X lands" ships for a later task to remove. When the final version needs a ruling or another task's work, the task waits for it or takes that work in.
- A fix Cass asks for belongs to the same task, so the same builder makes it.
- Cass resumes a builder that dies on an API or network error in the same turn its failure notice arrives.

## Parallelism

- Each builder runs in its own worktree slot. At most 2 builders run at once, or 3 while the third builder's disk gate holds. Cass never stops a running builder to meet the cap. Cass starts a new builder only while fewer builders run than the cap allows.
- Fix rounds go first. A builder that resumes to fix its own open PR runs before any new task gets a builder.
- The third builder is an experiment. Its disk gate holds while free disk is at least 20 GiB, plus 8 GiB for each slot with no `sharp/build/<os>-<arch>/mago/debug/` yet, plus 8 GiB for the new slot. Mago's dev build held 4.4 GiB fresh and 7.2 GiB once the builder had also run `cargo test -p` on two crates and clippy, and the engine and the tests' files held 0.4 GiB. A slot grows past that: one held 16 GiB, with 14 copies of `mago-codex` in its `deps`. The cause isn't proven yet. The hypothesis is that each package selection, such as another `cargo test -p` or `cargo clippy -p`, builds its own copies of the crates they share. The experiment ends after 5 merges if the median time from founding to merge doesn't fall, or if agent-hours per merge rise. Then the cap returns to 2.
- At most 4 helpers run at once, on top of the builders. Each does one short task.

## Worktree slots

- Cass creates a slot for each task, a `php-sharp` worktree under `~/Developer/sharp-worktrees/`, with Mago in its `sharp/mago/`, and names it in the task file.
- Cass creates the task's branch from `origin/master` in the slot before the builder starts. The builder builds in that slot's own build folders.
- A slot holds one build of Mago, in `sharp/build/<os>-<arch>/mago/debug/`: `sharp/bin/build-mago`, `sharp/bin/test` and the `cargo test -p` below share it, so a run after another compiles only what changed. A fresh build holds about 4.4 GiB. `cargo test -p` resolves the dependencies' features for the crates it names alone, so its first run in a slot compiles their dependencies a second time: `-p mago-codex` compiled 63 crates in 33 s, and the build grew by 0.4 GiB.
- Before its PR merges, the builder leaves no uncommitted files. After the merge, Cass removes the slot. No idle slot is kept, because a slot's build fills the disk.

## A PR's path to master

No agent reviews code. The review happens before the code exists, and the check after it is the task's Verification.

0. Before Cass founds the builder, Cass reviews the task's architecture and capabilities: every file and public API it changes, every capability it must keep, and the Verification that proves it. Verification names each case where the change could give a wrong result, a crash or a cost, as an input and the output it must give, in every mode CI runs. It is the check a code reviewer would have made.
1. The builder opens the PR, CI must pass, and its report shows each Verification condition met, with the command and the output it observed.
2. Cass checks that Evidence against each condition, and checks the PR's shape against the task: the files and public APIs it changes, and no stopgap. A miss goes back to the same builder, and Cass checks its fix the same way.
3. When every condition is met, Cass posts an approval on the PR.
4. Cass merges with `gh pr merge --merge --match-head-commit <full head SHA>`, then removes the slot.

A PR that finishes spec sections says so in its body, on its own line, exactly as `Completes: §6.1, §9`. The `COMPLETES` check fails while `sharp/baseline/spec` still lists an example of a named section.

A feature PR changes the spec, its examples, `sharp/baseline/spec` and the code together. It leaves `sharp/bin/spec`, which measures the examples, as master has it: that changes only in a PR of its own, beside Markdown. A feature PR may add or change harness classes in `sharp/harness/`. Its body lists each harness file, and its Verification proves that no stub makes an example pass falsely. When the builder's work makes listed examples pass, the failing run prints the `git rm` of each fixed example's file in `sharp/baseline/spec/`, and the entries leave through it. A listed example that fails with other errors than before stays on the list unchanged, because the list records which examples fail, not how. An example master already checked never joins the list unchanged.

New example-marker or run-comment syntax lands in `sharp/bin/spec` one PR before the examples use it, because `SPEC_GUARD` reads a PR's spec with the base's checker.

One feature is one PR: the checker in `sharp/mago/`, the engine, the standard library, the spec and their tests change together, and every CI gate runs on it.

A PR merges when its own required checks pass: `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES`. A PR that is behind master but has no conflict merges without an update, and master's push run tests the merged tree. The one exception is a release's bump PR, which is updated with master right before it merges, as the release's path says.

A PR's CI skips the jobs that run on the Architect's Mac, so a merge never waits for it: `MACOS` in `sharp.yml`, and `tests (macos-latest)`, `release-build` and `plain-php (macos-latest)` in `mago.yml`. It skips `LINUX_PASSES` too, which never failed without `LINUX` in the runs sampled, and `UBUNTU_WERROR` and `ASAN`, which build the engine a second time. Only a release's bump PR still runs them, because its full runs are the ones the tag checks. Master runs one push at a time, and a newer push replaces the one still waiting. A push run runs every job after the merge but `ASAN`, on every change since master's last push run that wasn't cancelled, and `ASAN` runs nightly. The push skips the Linux jobs a PR's run already passed on the same tree, which it has when the PR held the latest master, and runs the Linux suites again otherwise. So six kinds of defect now land on master first:

- one on a disk that ignores case
- one in the arm64 JIT
- one in Mago code that only macOS runs, such as the watch mode's FSEvents
- one that only macOS shows, in a crate the builder didn't edit: the builder's Mac tests only the crates it edits, and the PR tests the whole workspace on Linux alone
- a compiler warning only Ubuntu's gcc 13 gives
- a memory error only AddressSanitizer finds

A red push run opens one issue, or comments on the one still open, with the range of commits it tests, and a PR fixes it forward. No one reverts first: a merge commit can't land again cleanly once reverted, a revert hides a PR that merged on top of it, and it would revert healthy merges that failed only on the Mac's load. A red master gets a fix PR first, and other merges continue. A release tags only while master's newest finished push run is green. The bump's own push run may be cancelled by a later merge, and its tag doesn't need it: the tag's gates accept the bump PR's full run, as the release path explains.

## A release's path

A release tests and builds once, on the bump's PR, and the tag publishes those same files:

1. The bump's PR has a title that starts with `chore: release`. It changes only what `php sharp/mago/scripts/set-version.php <version>` writes, `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`, the version `Zend/tests/sharp/ri_sharp.phpt` expects, and the release's section of `sharp/CHANGELOG.md`. Its CI checks that. The title makes its run the full run, which runs all 22 modes on every build and every Mago job, the macOS ones included, and the run builds every release asset beside the suites: the six `mago` binaries with their attestations, Mago's source archives, their SHA-256s and both runtime images.
2. The bump merges once `CI_PASSED` passes, which needs the jobs that build the assets too. Update the bump's PR with master right before merging it, so its own full run covers the merge commit.
3. The bump's merge commit is tagged `v<version>`, and the tag is pushed. A tag on any other commit fails.
4. The tag publishes the assets the bump's PR built, and builds none. If no run of the PR passed, or its assets expired or were built from another tree, the tag builds them itself, and its run warns so. A bump from a fork builds no assets, so its tag always builds them.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when Cass or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area. Until `docs/architecture/guards.md` exists, Cass writes each such mistake straight into a task file.

## Test runs

- A PR runs each PHP# engine mode once, on the build it is about. Linux runs plain PHP, the three runs with cached bytecode and both JIT compilers, 6 modes. The thread-safe build runs plain PHP, the shared-memory run and both JIT compilers, 4 modes. After the merge, the push to master also runs macOS's plain PHP and both JIT compilers, 3 modes, the only arm64 JIT runs outside the full run, and each of PHP's 16 optimizer passes alone on Linux, unless the PR changes only Mago, the Composer plugin, the test fixtures, tests or documentation. Every night, master runs all 22 modes on every build, and so does a release's bump PR. A PR can't merge until its runs pass. A PR that changes only documentation skips them, and GitHub counts the skipped checks as passing.
- A builder runs only these locally, and leaves the other runs to CI:
  - `sharp/bin/test`, the run without opcache. It first builds `sharp/mago/` with `sharp/bin/build-mago`, as `cargo test --workspace` builds it, in the dev profile `sharp/mago/Cargo.toml` explains, and runs the `mago` that build makes.
  - When the change is under `sharp/mago/`, in `sharp/mago/`: `rustup run 1.97.0 cargo fmt --all -- --check`, and, with one `-p <package>` for each crate the change edits, `rustup run 1.97.0 cargo test -p <package> --locked --target-dir ../build/<os>-<arch>/mago` and `rustup run 1.97.0 cargo clippy -p <package> --all-targets --all-features --target-dir ../build/<os>-<arch>/mago -- -D warnings`. `<package>` is the `name` in the crate's `Cargo.toml`, such as `mago-codex` for `crates/codex/` and `mago` for `src/`. A file in `tests/support/` is compiled into the tests of each crate that names it in a `#[path]`, so a change to it edits those crates. The PR's Linux `tests` job runs the whole workspace's tests, and the push to master runs them on the Mac too. CI's `checks` job runs clippy on the whole workspace. So a builder leaves the crates it didn't edit to CI. After a one-line change to `mago-codex`, at 2 Cargo jobs, a round of its tests took 18 s and 24 CPU-s, and a round of the whole workspace's at 2 test threads took 943 s and 1,303 CPU-s. 1.97.0 is the toolchain `ext/sharp/rust-toolchain.toml` pins and `sharp/bin/build-mago` builds with. `sharp/mago/` has no toolchain file of its own, so a plain `cargo` there runs the machine's default, 1.92.0 on the builders' Mac, which is older than the `rust-version` Mago needs, and a toolchain other than the pin would compile every crate a second time. The target folder is the one `sharp/bin/test` builds `mago` in, so a slot keeps every Mago build in one folder, where `cargo test` without it wrote a second folder of 49 GB.
- `sharp/bin/coverage` is the one coverage measurement, and CI's `patch-coverage` job runs it. A builder runs it only to reproduce a `patch-coverage` failure, and first merges the latest `origin/master`, because CI measures the branch merged into master.
- One `sharp/bin/test` runs per worktree at a time, and each run of it and of `sharp/bin/bench` stands on its own:
  - `sharp/bin/bench` compiles a copy of its rows inside its own container, so it runs beside `sharp/bin/test` in the same worktree.
  - A run that failed or was stopped leaves nothing that breaks the next one, and no run loads the opcache file cache another run primed.
  - Every `sharp/bin/test` mode, `--linux` included, works the same in a git worktree as in the main checkout.

## Platforms

PHP# supports macOS and Linux. CI and releases build for those two only.

## Where things are written

- `docs/spec.md`: the language, as the Architect approved it. CI compiles and runs its examples, and `sharp/baseline/spec` lists the ones that fail today, which is the distance to v1. Git keeps no empty folder, so when none fails the folder is missing, and `sharp/bin/spec` reads a missing folder as an empty list.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why, once the change lands on master.
- `~/Developer/php-sharp/sharp/agents/architecture.md`: the architecture of the change in flight, which Cass writes for builders. Git ignores it, and it moves into `docs/architecture/` with the PR.
- `docs/how-we-work.md`: this file.
- `~/Developer/php-sharp/sharp/agents/`: task files. Git ignores them, so they exist only in that checkout and not in the worktree slots.
