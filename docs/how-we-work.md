# How we work

One Architect, one CTO agent and a team of agents build PHP#. This file says how the team works. Cass owns it, and it changes only through a PR, so git keeps its history.

## Who does what

- **Jordan, the Architect,** owns the language: `docs/spec.md` and the proposals in `docs/decisions/`. He approves or corrects proposals. He doesn't review code or plan work.
- **Cass, the CTO agent,** owns everything else: the plan, the order of work, the architecture, and this file. Cass starts every agent, posts every PR's one verdict and its approval, and checks that what lands works.
- **A builder** is an agent that does one task: one PR, from a new branch to its approval.
- **A reviewer** is an agent that reviews one task file or one PR once. It changes nothing, and Cass writes its findings into the task file or the PR's one verdict.
- **A helper** is an agent that reads, searches or researches for Cass, and changes nothing.

## One agent, one task

- Every task has a task file, and every task gets a fresh agent.
- Only Cass starts agents.
- The changes a review asks for belong to the same task, so the same builder makes them.
- Cass resumes a builder that dies on an API or network error in the same turn its failure notice arrives.

## Parallelism

- Each builder runs in its own worktree slot. At most 2 builders run at once, or 3 while the third builder's disk gate holds. Cass never stops a running builder to meet the cap. Cass starts a new builder only while fewer builders run than the cap allows.
- Fix rounds go first. A builder that resumes to fix its own open PR runs before any new task gets a builder.
- The third builder is an experiment. Its disk gate holds while free disk is at least 20 GiB, plus 28 GiB for each slot whose `sharp/build/<os>-<arch>/mago/debug/` is still under 19 GB, plus 28 GiB for the new slot. The experiment ends after 5 merges if the median time from founding to merge doesn't fall, or if agent-hours per merge rise. Then the cap returns to 2.
- At most 4 helpers and reviewers run at once, on top of the builders. Each does one short task.

## Worktree slots

- The pool has 8 slots, `~/Developer/sharp-worktrees/2` to `~/Developer/sharp-worktrees/9`. Each slot holds a `php-sharp` worktree, with Mago in its `sharp/mago/`.
- Cass names a free slot in every task file.
- Cass creates the task's branch from `origin/master` in the slot before the builder starts. The builder builds in that slot's own build folders.
- Before its PR merges, the builder leaves no uncommitted files. After the merge, Cass detaches the slot at the new `origin/master` and deletes the slot's `debug/` and `llvm-cov-target/` in `sharp/build/<os>-<arch>/mago/` to free disk. The other build folders stay, so the next task rebuilds only what changed.

## A PR's path to master

0. Before Cass founds the builder for a task with design decisions, Cass starts one fresh reviewer, and it attacks the task file and its research against the code. Cass writes each finding into the task file's Business or Architecture section, where it belongs, not into a list at the end. A mechanical task skips this step: a rename, a version bump or a docs edit.
1. The builder opens the PR, and CI must pass.
2. One fresh reviewer reviews the details and the architecture once, while Cass reads the PR against the whole system and the plan. Cass sends a PR that misses its Goal back to its builder at once. Otherwise Cass posts one verdict on the PR that holds the review's findings, Cass's own and any coverage gap. The builder makes every change it asks for, and Cass checks each fix by reading it. No second review round runs.
3. When every fix is read, Cass posts an approval on the PR.
4. Cass merges with `gh pr merge --merge --match-head-commit <full head SHA>`, then detaches the slot.

A PR that finishes spec sections says so in its body, on its own line, exactly as `Completes: §6.1, §9`. The `COMPLETES` check fails while `sharp/baseline/spec` still lists an example of a named section.

A feature PR changes the spec, its examples, `sharp/baseline/spec` and the code together. It leaves `sharp/bin/spec`, which measures the examples, as master has it: that changes only in a PR of its own, beside Markdown. A feature PR may add or change harness classes in `sharp/harness/`. Its body lists each harness file, and its reviewer checks that no stub makes an example pass falsely. When the builder's work makes listed examples pass, the failing run prints the `git rm` of each fixed example's file in `sharp/baseline/spec/`, and the entries leave through it. A listed example that fails with other errors than before stays on the list unchanged, because the list records which examples fail, not how. An example master already checked never joins the list unchanged.

New example-marker or run-comment syntax lands in `sharp/bin/spec` one PR before the examples use it, because `SPEC_GUARD` reads a PR's spec with the base's checker.

One feature is one PR: the checker in `sharp/mago/`, the engine, the standard library, the spec and their tests change together, and every CI gate runs on it.

Master requires CI to pass, and a branch must include the latest master before it merges, so master holds only trees CI tested. That costs no second run: a push to master finds the pull request run that passed the same tree, skips the suites and links that run. A red master is fixed before anything else merges.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when a reviewer, Cass or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area. Until `docs/architecture/guards.md` exists, Cass writes each such mistake straight into a task file.

## Test runs

- A PR runs each PHP# engine mode once, on the build it is about. Linux runs plain PHP, the three runs with cached bytecode and both JIT compilers, 6 modes. The thread-safe build runs plain PHP, the shared-memory run and both JIT compilers, 4 modes. macOS runs plain PHP and both JIT compilers, 3 modes, the only arm64 JIT runs a PR has. Each of PHP's 16 optimizer passes also runs alone on Linux, unless the PR changes only Mago, the Composer plugin, the test fixtures, tests or documentation. Every night, and before each release, master runs all 22 modes on every build. A PR can't merge until its runs pass. A PR that changes only documentation skips them, and GitHub counts the skipped checks as passing.
- A builder runs only these locally, and leaves the other runs to CI:
  - `sharp/bin/test`, the run without opcache. It builds `mago` from `sharp/mago/` first, in the `test-run` profile, which `sharp/mago/Cargo.toml` explains.
  - When the change is under `sharp/mago/`, in `sharp/mago/`: `cargo test --workspace --locked --target-dir ../build/<os>-<arch>/mago`, `cargo clippy --workspace --all-targets --all-features --target-dir ../build/<os>-<arch>/mago -- -D warnings` and `cargo fmt --all -- --check`. The target folder is the one `sharp/bin/test` builds `mago` in, so a slot keeps every Mago build in one folder, where `cargo test` without it wrote a second folder of 49 GB.
  - When the change is under `sharp/mago/`, after those three: `sharp/bin/coverage`, before each push. It is the run CI's `patch-coverage` job makes, on the same lines with the same bar, and it names each line the change adds that no test runs.
- One `sharp/bin/test` runs per worktree at a time, and each run of it and of `sharp/bin/bench` stands on its own:
  - `sharp/bin/bench` compiles a copy of its rows inside its own container, so it runs beside `sharp/bin/test` in the same worktree.
  - A run that failed or was stopped leaves nothing that breaks the next one, and no run loads the opcache file cache another run primed.
  - Every `sharp/bin/test` mode, `--linux` included, works the same in a git worktree as in the main checkout.

## Platforms

PHP# supports macOS and Linux. CI and releases build for those two only.

## Where things are written

- `docs/spec.md`: the language, as the Architect approved it. CI compiles and runs its examples, and `sharp/baseline/spec` lists the ones that fail today, which is the distance to v1.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why. Until it lands, the architecture is in `~/Developer/php-sharp/sharp/agents/architecture.md`, which git ignores.
- `docs/how-we-work.md`: this file.
- `~/Developer/php-sharp/sharp/agents/`: task files. Git ignores them, so they exist only in that checkout and not in the worktree slots.
