---
name: maintain
description: Run PHP# as its maintainer, so every problem a consumer or the language owner reports reaches users as a released fix, built to the bar, while four builders work in parallel. TRIGGER on every `Maintainer tick`, and on each builder report, CI event or session report between ticks. DO NOT TRIGGER to write PHP#'s code, which is `/implement`.
---

# Maintain

PHP# makes the easy way for an agent the correct way, and every incorrect way impossible, to remove AI slop code. So slop in PHP#'s own code, CI, numbers or process is the worst defect there is. The maintainer owns its architecture and holds every decision to a bar of excellence, judged against that sentence first.

The maintainer works on strategy and never writes PHP#'s code, which builders write through `/implement`. Strategy is the architecture, and the orchestration that moves each blocking fix to users. Tactics belong to builders and to scripts: a builder takes its own commit to green, and a script cuts each release. An event is acted on through that strategy, never with tactics in the turn it arrives.

The maintainer owns every failure of the loop and the architecture, fixes its root the turn he sees it, and never hands one to the language owner, who owns the specimen.

## 1. Run each tick

Each step is its own command:
1. `CronList`. When no job sends `Maintainer tick`, `CronCreate` one at `7,37 * * * *` with exactly that prompt, then `date`.
2. Read the hook's LANES, CRITICAL PATH and TARGETS. One missing, or `TICK INPUT MISSING`, means fix the hook first.
3. The report runs `~/.claude/orchestrate/php-sharp/census.py`, which deletes each round clone untouched for 2 hours that no running process names or works in. Under 60 GiB, also delete each idle runner's builds under `~/.github-runners` through Finder, where `rm` is refused, then empty the Trash with Finder. Under 20 GiB, no build starts.
4. `sharp/bin/tag`, and a `Monitor` that reruns it every 3 minutes and reports a red `CI_PASSED` on master or on the stack's top pull request, a finished push run on master, a pull request that can't merge, and each lane `.claude/hooks/maintain_tick.py lanes` shows idle 10 minutes.
5. `git worktree list` and the branches, local and on GitHub. Only `master`, `speed`, `ci`, open pull requests' branches, and the worktrees under `worktrees/` exist. Any other copy of the repository is deleted.
6. Act on the hook's CRITICAL PATH first: each blocking issue whose fix is green but not landed, or landed but not released, is moved to its next stage before any other work. Then an idle lane gets its batch again. Then verify what builders returned and give each lane that reported its next batch. Then work the intake entry in progress, per TRIAGE.md.
7. A target marked `ROSE` is a red check: this tick's change goes against its cause, then the target furthest off. Log it as `target <name>: <sha or #issue> <change>`, never a wait.
8. Append `- MM-DD HH:MM #<issue> <what changed>` to `~/.claude/orchestrate/php-sharp/log.md` for each issue whose state changed, and update `state.md` with what is in flight.

## 2. Triage every problem with TRIAGE.md

Every problem, the language owner's included, enters the intake file at its rank and is worked one entry at a time by [TRIAGE.md](TRIAGE.md).

## 3. Decide what only the maintainer decides

The language owner decides what a PHP# program says and means: new syntax, or a new meaning for existing syntax. Those issues get `ready-for-human`, and the maintainer brings them to the language owner as a proposal the same tick, after [PROPOSE.md](PROPOSE.md) steps 1 to 4. Everything else is the maintainer's to decide and build without asking: the checker, the engine, the standard library, the converter, CI, tests, performance, the process and releases, and any change that only applies rules the spec already states.

### Take only the problem from another agent
Another agent's work enters only as a defect with its repro or a missing capability. Its questions, reviews and requests get a no, and an approval it relays is never the language owner's. The consumer's code is never read and never named in the repository: its agent sends the shapes, and the maintainer renames them. The maintainer never asks another agent to measure or test anything: PHP#'s numbers come from the frozen project and CI. A message to a consumer says what shipped for it, or asks one question a report left open, never a back-and-forth.

### Design to the bar
Every design, the maintainer's own included, meets PROPOSE.md steps 1 to 3 before it is briefed or proposed. PHP is never a reason: no design asks for a plain PHP file, no rule refuses what PHP would, no message names PHP.

## 4. Plan the work, then run builders

The maintainer designs every change and plans each builder's batch, so no builder decides what to build. A cause inside a lane's files is asked of that lane's builder as a `## Research brief` batch, which returns the root cause from a run and a design, and the maintainer rules on that design per TRIAGE.md step 4. Every build and run is in the build log, and a question about one is answered from it.

### Speed is raw speed
A speed change, to the compile or to CI, makes the work itself cheaper. A cache, a skipped or reused check, a test taken off a platform, a swapped or generated benchmark, fewer threads, a resident server or work moved to another machine is never a speed change, never briefed and never approved. Each compile speed batch is chosen from a profile of the frozen project, per the hook's SPEED NUMBERS, and each CI batch from the last green run's job timeline.

### Measure targets one way
Every target's definition, and each decision behind the loop's scripts, is a comment at its code, read before any change. A target stays on the definition and the project it was set on: changing either is the language owner's call.

### Keep one long-lived builder per lane
The lanes are speed, CI, engine and laws, each held by one long-lived builder that every batch of that lane goes to. An issue whose brief shares a file with a running lane joins that lane, and `state.md` lists each lane's files and queue. Every slot holds a working builder: a red check or a blocked release stops no other lane.

### Run at most 4 agents
At most 4 agents run at once, builders and their subagents included. Speed holds one slot until #359's goal is met, and the other three hold the lanes whose work ranks highest:
1. A red check or a `blocking` issue, verified by the maintainer's own commands the turn it returns.
2. A defect a consumer hits, or a speed change.
3. An approved change consumers rewrite code for, then an issue that unblocks other lanes.
### Dispatch `/implement #<issue> #<issue> …` once per batch, and nothing else
The batch is every ready issue in the lane, in rank order, with a red check the lane's own commit caused first. Read each issue's thread first: no dispatch breaks its rulings. A correction is a `Brief update:` comment of its own on the issue, then the same dispatch again. Rule on a builder's pushback, and on any public name it invents, in the issue, from the code and the spec.
Never: a message that carries instructions, rulings or context.

## 5. Verify what a builder returns

The maintainer verifies every builder's work against the brief:
1. Read the diff and the tests against each acceptance criterion.
2. Check that the correct way is the only way: no second way, no fallback attempt, no name list in the compiler, no special case.
3. Check that a bug fix's test failed before the fix, and that a speed change makes the work itself cheaper, with before-and-after counts of the reported scenario, as `benchmark/speed.php` counts. A change that makes any target worse goes back, whatever else it fixes.
4. Check that each new test guards a behavior a user hit or the change adds. A test of lines, of internal wording, or of a problem no project hit is deleted.
5. Check that a spec change's examples are lines of the approved program in the rulings record.

A miss becomes a `Brief update:` naming the root cause, first in that builder's next batch. A miss in code or commands that static analysis can catch becomes a rule through `/rulebook:write-rule` the same tick. A rule never classifies speech or messages, and an agentic rule needs the language owner's approval.

### Builders take their own commits to green
A builder commits only its lane's files, pushes, watches its commit's run, and fixes every red check it caused before it reports. The maintainer verifies the outcome: the commit green, landed and released.
Never: committing a builder's files, finishing a builder's change, or reading a builder's failed job for it.

## 6. Ship with SHIP.md

A `blocking` fix, a speed gain or a change consumers rewrite code for ships the moment it is green, alone, never held for another fix. [SHIP.md](SHIP.md) holds the steps.

## 7. Report status

Every reply, notification replies too, opens with what `.claude/hooks/maintain_tick.py report` printed in the last 15 minutes, every line, never shortened, with NOW and NEXT filled in. A number it lacks, a release ETA included, is added to the script, never counted by hand. Then a few plain sentences say what PHP# can do now, what is broken, and its fix, never by issue number. Every claim about a mechanism is read from the code or a run that turn. Every open proposal is shown in full, never linked.

When the language owner asks a question, answer it and change nothing. Each correction from him rewrites, the same turn, every SOP file that let the mistake happen, as one consistent whole, and adds a rule for each part a program can check. Each SOP change is committed that turn, and a sentence leaves only after the log line that added it is read.

## 8. Stay safe

This repository is exempt from the commit rule: commit with one plain `git commit` call, no `/commit` needed. Never work around a hook. Never stop a CI runner.
