---
name: implement
description: Build one PHP# issue from its agent brief, failing test first, and report each acceptance criterion with the command and its output. TRIGGER when a builder's task is `/implement #<issue>`. DO NOT TRIGGER for the maintainer's own work.
---

# Implement

Build the work one issue's agent brief describes. The brief is the contract: its decided design, its acceptance criteria and its scope.

## 1. Read the issue

Run `gh issue view <number> --repo heyJordanParker/php-sharp --comments`. State its title. The latest `## Agent Brief` comment is the contract, and each later comment that opens with `Brief update:` changes it.

A design call the brief left open is the maintainer's. Report it as a question and stop, rather than deciding it.

## 2. Build it test first

Write the test the first acceptance criterion names, and run it to watch it fail for the reason the brief gives. Then write the change that makes it pass. Use `/tdd` for the cadence.

Build the final version: no stopgap, no code marked "until X lands", and no narrowing of the problem to make the test pass. Write the code, then fix it.

## 3. Check only what the change reaches

Run the tests the acceptance criteria name and the tests of each crate you changed, plus `cargo clippy -- -D warnings` and `cargo fmt --check` on those crates. The PR's CI runs everything else once, so never run `sharp/bin/coverage`, `sharp/bin/spec`, the phpt suite or `sharp/bin/test --mago`.

Every cargo command runs under `nice -n 15`, with `CARGO_TARGET_DIR=sharp/build/<platform>/mago` and at most the job count `sharp/bin/jobs` prints, so the machine keeps its cores and compiles one copy of the workspace.

## 4. Work in the shared checkout

Work in `/Users/jordan/Developer/php-sharp` on the checked-out branch. Other builders edit the same tree:
- Never switch branch, and never create a worktree or a branch.
- Read each file before you edit it.
- Never use `git reset`, `git restore`, `git clean` or `git checkout` to undo work.

## 5. Hand the commit to the maintainer

Write `sharp/agents/<issue>/commit-<n>.txt`, the commit message ending in `Closes #<issue>`, and `sharp/agents/<issue>/files-<n>.txt`, one path per line. The maintainer commits. The repository is open source, so commits, the CHANGELOG and docs never name a consumer project, the people behind PHP#, or another programming language as precedent.

## 6. Report

The final reply is the report. It lists each acceptance criterion with the command you ran and the output you observed, then every Problem you found outside the brief, each with the Solution you would ship.
