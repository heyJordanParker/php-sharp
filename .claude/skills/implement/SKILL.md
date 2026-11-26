---
name: implement
description: Build one PHP# issue from its agent brief, failing test first, and report each acceptance criterion with the command and its output. TRIGGER when a builder's task is `/implement #<issue>`. DO NOT TRIGGER for the maintainer's own work.
---

# Implement

Build the work one issue's agent brief describes. The brief is the contract: its decided design, its acceptance criteria and its scope.

## 1. Read the issues

The task is a batch of one or more issues. Build them in the order named, commit and push each as its criteria pass, then report once for the batch. Before you start, run `gh issue view <number> --repo heyJordanParker/php-sharp --comments` for each, and state its title and newest comment's time, which matches the dispatch's. The latest `## Agent Brief` comment is the contract, and each later comment that opens with `Brief update:` changes it. A batch can name an issue you built before, because a `Brief update:` on it is work you haven't done yet. An issue with no `## Agent Brief` or `## Research brief` comment has no contract yet: report that and build nothing for it.
Never: skipping the read of an issue because you built it before.
A `/implement` that names the issue you're already building means a new comment arrived on it: run that `gh issue view` again the moment it arrives, stop each run the comment rules out, and follow it.

Name a public member the brief left unnamed with `/naming`. A private helper and a test fixture keep the plain name you first wrote, with no naming review.

A design call the brief left open is the maintainer's. Report it as a question in your final reply and stop, rather than deciding it. Post every finding, question and report on the issue itself, and never file anything to an inbox or another tracker.

## 2. Build it test first

Write the test the first acceptance criterion names, and run it to watch it fail for the reason the brief gives. Write tests only for the behavior the brief changes. A test asserts what a user sees or relies on: the result, the refusal, and the words of a message a user reads. It never asserts an internal message's wording, a private step's output, or how the code reaches its result, so a change from working code to working code leaves every test green. Never write a test to cover lines, or for a problem you found that no project hit: report that problem instead. Then write the change that makes it pass. Use `/tdd` for the cadence. Each loop you run ends within 5 minutes; report a longer one as a speed problem.

A speed change is tried, not designed first: build it, measure before and after on the brief's scenario in `benchmark/speed.php`'s counts the moment both are built, keep it with a gain, and revert it without one. A profile picks what to try next. A speed change, to the compile or to CI, makes the work itself cheaper.
Never: a cache, a skipped or reused check, a test taken off a platform, a generated or swapped benchmark, a fallback attempt, fewer threads, or work moved to another machine.

The frozen project a brief names is run, never read: a run reads only mago's output and the timings, and each round's clone and cache folder are deleted the moment that round's measures are logged, then the Trash is emptied with `osascript -e 'tell application "Finder" to empty trash'`, since the shell's `rm` moves files to the Trash and frees no space until it is emptied. No file, test, commit or comment names the consumer project.

Finish each change to a commit, or revert it, before you start another: nothing stays staged, set aside or saved as a patch.

Build the final version: no stopgap, no code marked "until X lands", and no narrowing of the problem to make the test pass. Write the code, then fix it.

IF the change reads time or waits on a deadline:
### Give the code its clock, and test it on a fake clock
The code takes its wall and CPU clocks as inputs, as a PHP# program takes the `Clock` effect. Its test advances a fake clock by hand and asserts the result just before, at and after the deadline. A test that waits on another process with no callback polls it with a ceiling. One test per real interrupt path runs on the real clocks and asserts the error and the CPU used.
Never: a sleep that waits for something to happen, or an assertion on how much wall time passed.

## 3. Check only what the change reaches

Before you push, run only what CI can't tell you sooner: the tests the acceptance criteria name, red before the change and green after it, the tests of each module you changed and the integration test file that covers it, run from the test binaries `sharp/bin/build-mago` builds and its `build.json` names, filtered to the module (`cargo test -p <crate>` resolves the crate's features apart from the workspace and compiles units the build already has again), `cargo clippy --keep-going -- -D warnings` on the crates you changed, so one run reports every crate's findings, `cargo fmt --check` once in `sharp/mago`, `mago fmt --check`, `mago lint` and `mago analyze` on each PHP file you changed under `sharp/mago`, and `~/.cargo/bin/typos` on every file you changed. A diff that changes the text of an error, warning or help line runs `git grep -l` for the old text under every `tests` folder and `Zend/tests/sharp`, and runs each test it names: on 11-24 and 11-25, six CI runs failed on a test in another file that still expected the old message. CI runs every other check on the tree that lands, on the Mac you build on, so never run another crate's tests, a phpt file, `sharp/bin/spec`, the laws suite or an image build here unless a criterion names it. A red check CI finds in your commit is the first thing you fix, per step 5.

Every cargo command runs as `rustup run <rust-version> cargo …`, with the `rust-version` `sharp/mago/Cargo.toml` names, because the `cargo` on `PATH` is an older Rust that refuses the workspace. It runs with `CARGO_TARGET_DIR` set to the absolute path of `sharp/build/<platform>/mago` in your lane's worktree, and at most the job count `sharp/bin/jobs` prints. Every build serves a criterion of an open issue in your batch: never build from an empty target folder, never build in the release profile unless a criterion or a speed measurement names it, and never build for a closed issue.

Wait on a long run with one `Monitor` that ends when the run ends, never by ending your turn to wait, since each turn you end sends the maintainer a notification. A `Monitor` expires after 30 minutes, so re-arm it at each expiry until the run ends, and stop every process you started before you report.

A run counts only for the tree it built. An edit, or a `Brief update:` that changes what you build, voids every run started before it, so stop that run and start it again after your last edit. The stale-run hook names each one after every edit.

## 4. Work in your lane's worktree

Work, build, test and commit in `/Users/jordan/Developer/php-sharp/worktrees/<lane>`, your lane's own worktree and build folder, so no lane's build waits on another's. The dispatch names your lane. Create it once with `git worktree add --detach worktrees/<lane> origin/master` from the checkout. Start each issue on its own branch from master as it is now: `git fetch origin`, then `git switch -c issue/<issue> origin/master`, with the issue before it committed:
- Change only the files the brief lists, plus any test file, fixture or `[dev-dependencies]` line a criterion's test needs. A change that needs any other file is a question for the maintainer.
- Never edit `docs/spec.md` beyond the spec text the brief quotes. The spec is the language owner's: a rule it seems to need is a question for the maintainer.
- Make no branch but `issue/<issue>` for the issue you build, and never make another copy of the repository: no clone or copy beyond your lane's worktree.
- Never build or test in the checkout or in another lane's worktree.
- Never start a subagent.
- Read each file before you edit it.
- Stop only a process you started, by the PID you recorded, never by a name or a pattern another lane's build can match.
- Never use `git reset`, `git restore`, `git clean` or `git checkout` to undo work, nor `git read-tree --reset`, which does the same. A branch takes master's newer commits with `git merge origin/master`, never a rebase.

## 5. Commit and push

Commit each issue the moment its acceptance criteria pass, and push it. Each issue's pull request holds only that issue's commits, on its branch made from `origin/master`, because the merge queue lands pull requests in batches and a commit one pull request carries for another lands or fails with it.
1. In your worktree, on `issue/<issue>`, run `git add -- <files only you changed>`, then commit with `git commit -F <message-file>` as a call of its own, the message ending in `Closes #<issue>`: the commit rule lets this repository commit without `/commit` only when the call is that one command, and refuses a `git commit` inside `export … && …`.
2. Check that `git status --short` lists none of your files, and that `git log --oneline origin/master..HEAD` lists only this issue's commits.
3. Push with `git push origin issue/<issue>`, then `gh pr create --base master --head issue/<issue> --fill` and `gh pr merge issue/<issue> --auto --merge`: the language owner approved each commit, push and pull request this skill names, so none waits on another approval. A fix to your pull request is a new commit on the same branch, pushed, never a rebase or a force-push. Then watch your pull request's `PHP#` and `Mago` runs with one `Monitor` while you build the next issue, read every job that fails, and fix each red check your commit caused before you report. An issue is done when its pull request merges.

The pull request's `## Changelog` section, which the release notes are made of, says what a user can do now. It compares with the newest published release, the tag `gh release view --json tagName` names, never the version `Cargo.toml` holds, which is the release in progress.

The repository is open source, so commits, pull requests and docs never name a consumer project, the people behind PHP#, or another programming language as precedent.

## 6. Report

The final reply is the report, sent once the batch's last issue is pushed. For each issue it names the commit, lists each acceptance criterion with the command you ran and the output you observed, then each failure outside the brief that a run of this batch hit, with its command and output.
