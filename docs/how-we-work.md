# How we work

One Architect, one CTO agent and a team of agents build PHP#. This file says how the team works. It changes only through a PR the Architect approves, so git keeps its history.

## Who does what

- **Jordan, the Architect,** owns the language: `docs/spec.md` and the proposals in `docs/decisions/`. He approves or corrects proposals. He doesn't review code or plan work.
- **Cass, the CTO agent,** owns everything else: the plan, the order of work, the architecture in `docs/architecture/`, and this file. Cass starts every agent, approves every PR's architecture after its review, and checks that what lands works.
- **A builder** is an agent that does one task: one PR, from a new branch to its merge.
- **A reviewer** is an agent that reviews one PR and posts its verdict on that PR.
- **A helper** is an agent that reads, searches or researches for Cass, and changes nothing.

## One agent, one task

- Every task has a task file, and every task gets a fresh agent.
- No agent starts another agent. Only Cass does.
- The changes a review asks for belong to the same task, so the same builder makes them.

## Parallelism

- At most 7 builders run at once, one per worktree slot.
- At most 4 helpers and reviewers run at once, on top of the builders.

## Worktree slots

- The pool has 7 slots, `~/Developer/sharp-worktrees/1` to `~/Developer/sharp-worktrees/7`. Each slot holds a `mago-sharp` worktree and a `php-sharp` worktree.
- Cass names a free slot in every task file.
- Cass creates the task's branch from `origin/master` in the slot before the builder starts. The builder builds in that slot's own build folders.
- After its PR merges, the builder leaves no uncommitted files, and Cass detaches the slot at the new `origin/master`. The build folders stay, so the next task rebuilds only what changed.

## A PR's path to master

1. The builder opens the PR, and CI must pass.
2. A fresh reviewer reviews the details and the architecture, and posts its verdict on the PR. The builder makes the changes it asks for, until it approves.
3. Cass checks the architecture across the whole system and the plan, and posts an approval on the PR. If Cass asks for changes, the builder makes them and the reviewer reviews again.
4. The builder merges with `gh pr merge --merge --match-head-commit <full head SHA>`.

A Mago PR merges before the php-sharp PR that pins it. The php-sharp PR is re-pinned once, to the Mago merge commit.

Master requires CI to pass. It doesn't require a branch to include the latest master. CI runs on master after every merge, and a red master is fixed before anything else merges.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when a reviewer, Cass or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area.

## Test runs

- CI runs every PHP# engine test 22 times, on Linux and on macOS: plain PHP, three runs with cached bytecode, each of PHP's 16 optimizer passes on its own, and both JIT compilers. A PR can't merge until all 22 pass on both.
- A builder runs only the plain run locally, plus the Mago workspace suite, clippy and fmt.

## Platforms

PHP# supports macOS and Linux. CI and releases build for those two only.

## Where things are written

- `docs/spec.md`: the language, as the Architect approved it.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why.
- `docs/how-we-work.md`: this file.
- `sharp/agents/`: task files. Git ignores them.
