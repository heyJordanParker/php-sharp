# How we work

One Architect, one CTO agent and a team of agents build PHP#. This file says how the team works. Cass owns it, and it changes only through a PR, so git keeps its history.

## Who does what

- **Jordan, the Architect,** owns the language: `docs/spec.md` and the proposals in `docs/decisions/`. He approves or corrects proposals. He doesn't review code or plan work.
- **Cass, the CTO agent,** owns everything else: the plan, the order of work, the architecture, and this file. Cass starts every agent, approves every PR's architecture after its review, and checks that what lands works.
- **A builder** is an agent that does one task: one PR, from a new branch to its merge.
- **A reviewer** is an agent that reviews one PR once. It changes nothing, and Cass posts its verdict on the PR.
- **A helper** is an agent that reads, searches or researches for Cass, and changes nothing.

## One agent, one task

- Every task has a task file, and every task gets a fresh agent.
- Only Cass starts agents.
- The changes a review asks for belong to the same task, so the same builder makes them.

## Parallelism

- At most 2 builders run at once, each in its own worktree slot. Cass never stops a running builder to meet the cap. Cass starts a new builder only while fewer than 2 run.
- At most 4 helpers and reviewers run at once, on top of the builders. Each does one short task.

## Worktree slots

- The pool has 7 slots, `~/Developer/sharp-worktrees/1` to `~/Developer/sharp-worktrees/7`. Each slot holds a `php-sharp` worktree, with Mago in its `sharp/mago/`.
- Cass names a free slot in every task file.
- Cass creates the task's branch from `origin/master` in the slot before the builder starts. The builder builds in that slot's own build folders.
- After its PR merges, the builder leaves no uncommitted files, and Cass detaches the slot at the new `origin/master`. The build folders stay, so the next task rebuilds only what changed.

## A PR's path to master

1. The builder opens the PR, and CI must pass.
2. One fresh reviewer reviews the details and the architecture once, and Cass posts its verdict on the PR. The builder makes every change it asks for, and Cass checks each fix by reading it. No second review round runs.
3. Cass checks the architecture across the whole system and the plan, and posts an approval on the PR. If Cass asks for changes, the builder makes them and Cass checks them by reading.
4. The builder merges with `gh pr merge --merge --match-head-commit <full head SHA>`.

A PR that finishes spec sections says so in its body, on its own line, exactly as `Completes: §6.1, §9`. The `COMPLETES` check fails while `sharp/baseline/spec` still lists an example of a named section.

A feature PR changes the spec, its examples, `sharp/baseline/spec` and the code together. It leaves `sharp/bin/spec`, which measures the examples, as master has it: that changes only in a PR of its own, beside Markdown. A feature PR may add or change harness classes in `sharp/harness/`. Its body lists each harness file, and its reviewer checks that no stub makes an example pass falsely. When the builder's work makes listed examples pass, the failing run prints the `git rm` of each fixed example's file in `sharp/baseline/spec/`, and the entries leave through it. A listed example that fails with other errors than before stays on the list unchanged, because the list records which examples fail, not how. An example master already checked never joins the list unchanged.

New example-marker or run-comment syntax lands in `sharp/bin/spec` one PR before the examples use it, because `SPEC_GUARD` reads a PR's spec with the base's checker.

One feature is one PR: the checker in `sharp/mago/`, the engine, the standard library, the spec and their tests change together, and every CI gate runs on it.

Master requires CI to pass. It doesn't require a branch to include the latest master. CI runs on master after every merge, and a red master is fixed before anything else merges.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when a reviewer, Cass or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area. Until `docs/architecture/guards.md` exists, Cass writes each such mistake straight into a task file.

## Test runs

- CI runs every PHP# engine test 22 times, on Linux and on macOS: plain PHP, three runs with cached bytecode, each of PHP's 16 optimizer passes on its own, and both JIT compilers. A PR can't merge until all 22 pass on both. A PR that changes only documentation skips them, and GitHub counts the skipped `LINUX` and `MACOS` checks as passing.
- A builder runs only these locally, and leaves the other runs to CI:
  - `sharp/bin/test`, the run without opcache. It builds `mago` from `sharp/mago/` first.
  - In `sharp/mago/`: `cargo test --workspace --locked`, `cargo clippy --workspace --all-targets --all-features -- -D warnings` and `cargo fmt --all -- --check`.
- Each run of `sharp/bin/test` and `sharp/bin/bench` stands on its own:
  - `sharp/bin/bench` compiles a copy of its rows inside its own container, so it runs beside `sharp/bin/test` in the same worktree.
  - A run that failed or was stopped leaves nothing that breaks the next one.
  - Every `sharp/bin/test` mode, `--linux` included, works the same in a git worktree as in the main checkout.

## Platforms

PHP# supports macOS and Linux. CI and releases build for those two only.

## Where things are written

- `docs/spec.md`: the language, as the Architect approved it. CI compiles and runs its examples, and `sharp/baseline/spec` lists the ones that fail today, which is the distance to v1.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why. Until it lands, the architecture is in `~/Developer/php-sharp/sharp/agents/architecture.md`, which git ignores.
- `docs/how-we-work.md`: this file.
- `~/Developer/php-sharp/sharp/agents/`: task files. Git ignores them, so they exist only in that checkout and not in the worktree slots.
