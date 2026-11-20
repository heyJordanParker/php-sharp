---
name: implement
description: Build one PHP# issue from its agent brief, failing test first, and report each acceptance criterion with the command and its output. TRIGGER when a builder's task is `/implement #<issue>`. DO NOT TRIGGER for the maintainer's own work.
---

# Implement

Build the work one issue's agent brief describes. The brief is the contract: its decided design, its acceptance criteria and its scope.

## 1. Read the issue

Run `gh issue view <number> --repo heyJordanParker/php-sharp --comments`. State its title. The latest `## Agent Brief` comment is the contract, and each later comment that opens with `Brief update:` changes it. Read the issue again on every `/implement`, a resume included, since a `Brief update:` posted after your last report is work you haven't done yet. A `/implement` for the issue you are already working on means exactly that: the maintainer posted a `Brief update:`, so read it before your next step.
Never: "this is the same task already underway" as the reason to skip the read.

Name a public member the brief left unnamed with `/naming`. A private helper and a test fixture keep the plain name you first wrote, with no naming review.

A design call the brief left open is the maintainer's. Report it as a question in your final reply and stop, rather than deciding it. Post every finding, question and report on the issue itself, and never file anything to an inbox or another tracker.

## 2. Build it test first

Write the test the first acceptance criterion names, and run it to watch it fail for the reason the brief gives. Write tests only for the behavior the brief changes. A test asserts what a user sees or relies on: the result, the refusal, and the words of a message a user reads. It never asserts an internal message's wording, a private step's output, or how the code reaches its result, so a change from working code to working code leaves every test green. Never write a test to cover lines, or for a problem you found that no project hit: report that problem instead. Then write the change that makes it pass. Use `/tdd` for the cadence.

A speed change is tried, not designed first: build it, measure before and after on the brief's scenario, keep it with a gain, and revert it without one. A profile picks what to try next.

Finish each change to a commit, or revert it, before you start another: nothing stays staged, set aside or saved as a patch.

Build the final version: no stopgap, no code marked "until X lands", and no narrowing of the problem to make the test pass. Write the code, then fix it.

## 3. Check only what the change reaches

Run the tests the acceptance criteria name and the tests of each crate you changed, plus `cargo clippy -- -D warnings` and `cargo fmt --check` on those crates, and `mago fmt --check`, `mago lint` and `mago analyze` on each PHP file you changed under `sharp/mago`. Run `sharp/bin/test` on each phpt file that covers code you changed, and `sharp/bin/spec` when you changed `docs/spec.md` or the standard library. CI runs everything else, so never run the phpt suite or `sharp/bin/test --mago`.

Every cargo command runs as `rustup run <rust-version> cargo …`, with the `rust-version` `sharp/mago/Cargo.toml` names, because the `cargo` on `PATH` is an older Rust that refuses the workspace. It runs under `nice -n 15`, with `CARGO_TARGET_DIR=sharp/build/<platform>/mago` and at most the job count `sharp/bin/jobs` prints, so the machine keeps its cores and compiles one copy of the workspace.

Wait on a long run with one `Monitor` that ends when the run ends, never by ending your turn to wait, since each turn you end sends the maintainer a notification.

## 4. Work in the shared checkout

Work and test in `/Users/jordan/Developer/php-sharp` on the checked-out branch. Other builders edit the same tree:
- Change only the files the brief lists. A change that needs another file is a question for the maintainer.
- Never edit `docs/spec.md` beyond the spec text the brief quotes. The spec is the language owner's: a rule it seems to need is a question for the maintainer.
- Never switch branch, and never make a second copy of the repository: no worktree, branch, clone, export, archive, or copy on another machine. A brief that names a worktree, as the speed and CI lanes' briefs do, is the one exception.
- Never start a subagent.
- Read each file before you edit it.
- Never use `git reset`, `git restore`, `git clean` or `git checkout` to undo work, nor `git read-tree --reset` or `git update-ref`, which do the same. A worktree moves forward with `git merge --ff-only next`.

## 5. Commit, push, and own CI until it is green

You own the change until CI passes on it. Commit each batch the moment its acceptance criteria pass:
1. Build your commit in a private index, never the shared one, because every lane works in one checkout and a commit of the shared index takes another lane's staged lines with it. Run `export GIT_INDEX_FILE=<scratchpad>/index-<issue>` and `git read-tree HEAD`, then `git add -- <files only you changed>`, and for a file that also holds another builder's uncommitted lines, such as `sharp/CHANGELOG.md`, `git apply --cached <your-hunk>.diff` with its context lines, never `--unidiff-zero`, which can place a hunk at the wrong line. Right before the commit, read the index from HEAD again and add your files, then check that `git diff-index --cached --name-only HEAD` lists only them, since other lanes commit in between. Commit with `GIT_INDEX_FILE=<index> git commit -F <message-file>` as a call of its own, the message ending in `Closes #<issue>`: the commit rule lets this repository commit without `/commit` only when the call is that one command, and refuses a `git commit` inside `export … && …`. Then `unset GIT_INDEX_FILE` and run `git ls-tree HEAD -- <every file in your commit> | git update-index --index-info` from the repository root, since `ls-tree` prints paths relative to the folder it runs in, so the shared index matches what you committed.
2. Check that the commit holds every line its code needs: `git diff -- <your files>` prints nothing once you've committed, apart from other lanes' lines. Then `git push` to `next`, the one branch every lane commits to. A push also sends other lanes' commits, which are green too.
3. Watch `next`'s pull request run with `gh pr checks <pr> --watch`; each green run merges into `master` by itself. A red check your commit caused is yours: read its log, fix it in your files, commit, and push again.

The repository is open source, so commits, the CHANGELOG and docs never name a consumer project, the people behind PHP#, or another programming language as precedent.

## 6. Report

The final reply is the report, sent once CI is green on your last commit. It names each commit and the green run, lists each acceptance criterion with the command you ran and the output you observed, then every Problem you found outside the brief, each with the Solution you would ship.
