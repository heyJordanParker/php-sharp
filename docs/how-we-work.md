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
- The third builder is an experiment. Its disk gate holds while free disk is at least 20 GiB, plus, for every Mago folder in every builder slot, 15.6 GiB minus what that folder holds now, and never less than 0, plus 15.6 GiB for the new slot. A Mago folder is a slot's `sharp/build/<os>-<arch>/mago/`, and a slot can hold two: `darwin-arm64/mago/`, and `linux-<arch>/mago/`, which `sharp/bin/test --linux` and `sharp/bin/bench` build. A Mago folder grows because Cargo never deletes a copy of a crate it no longer uses. Each distinct `cargo test -p` or `cargo clippy -p` list compiles its own copy of the shared crates, and so does each version bump or `Cargo.lock` change a merge of master brings in. A fresh build of Mago held 5.9 GiB, 7 lists took one slot from 5.85 to 7.37 GiB, and live slots reached 16 GiB. So in a builder's slot, `sharp/bin/build-mago` deletes the folder above 10 GiB and builds it from nothing. It checks only before it builds, so a folder grows past the bound until the next build, and 15.6 GiB is the most one reached: in slot 13, a folder at 9.89 GiB held 14.60 GiB after a build of a version bump, 15.39 GiB after `cargo test -p mago-codex -p mago-syntax`, and 15.54 GiB after their `cargo clippy`. CI keeps the folder its own way, with `.github/actions/clean-kept-build` on the self-hosted Macs and `.github/actions/mago-cache` on GitHub's runners, so there `sharp/bin/build-mago` leaves it alone. The experiment ends after 5 merges if the median time from founding to merge doesn't fall, or if agent-hours per merge rise. Then the cap returns to 2.
- At most 4 helpers run at once, on top of the builders. Each does one short task.

## Worktree slots

- Cass creates a slot for each task, a `php-sharp` worktree under `~/Developer/sharp-worktrees/`, with Mago in its `sharp/mago/`, and names it in the task file.
- Cass creates the task's branch from `origin/master` in the slot before the builder starts. The builder builds in that slot's own build folders.
- A slot holds one build of Mago, in `sharp/build/<os>-<arch>/mago/debug/`: `sharp/bin/build-mago`, `sharp/bin/test` and the `cargo test -p` below share it, so a run after another compiles only what changed. It keeps no incremental state, and `sharp/bin/build-mago` bounds its folder, `sharp/build/<os>-<arch>/mago/`, at 10 GiB, as "Parallelism" explains. `cargo test -p` resolves the dependencies' features for the crates it names alone, so its first run in a slot compiles their dependencies a second time: `-p mago-codex` compiled 63 crates in 33 s, and the build grew by 0.4 GiB.
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

A PR merges when its own required checks pass: `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES`. They end under 10 minutes, because each Linux suite runs in slices on jobs of under 8 minutes, and a PR's runs hold 14 jobs at most, so none waits for a runner. PR #344's runs ended in 8.2 and 6.7 minutes, and its longest job took 7.9. A release's PR also runs the Mac's jobs and builds the release, whose new version rebuilds Mago in every job, so it takes longer: test PR #353's runs took 68 and 66 minutes, because its 10 Mac jobs queue for the three runners on one Mac. A PR that is behind master but has no conflict merges without an update: its run tested the PR merged with master, which is the merge commit's tree when master hasn't moved since that run started, and master's push run tests the merged tree otherwise.

Only a release's PR runs the jobs on the Architect's Mac: `MACOS` and `LINUX_ARM64` in `sharp.yml`, each in three slices, one per Mac runner, and `tests (macOS)`, `release-build` and `plain-php (macos-arm64-local)` in `mago.yml`. It also builds the runtime image's `IMAGE` jobs and the release's assets. Master runs one push at a time, and a newer push replaces the one still waiting. A push runs no check when a PR's run passed `CI_PASSED` and `ci-passed` on the same tree, which it has when master hasn't moved since that run started. A push whose tree no PR's run passed runs every check, the Mac's among them, on every change since master's last push run that wasn't cancelled. `ASAN` runs nightly. So between releases, seven kinds of defect land on master, and the next release's PR finds them:

- one on a disk that ignores case
- one that only arm64 Linux shows
- one in Mago code that only macOS runs, such as the watch mode's FSEvents
- one that only macOS shows in Mago's tests: `sharp/bin/check` and the PR test the whole workspace on Linux alone
- a runtime image that stops building, fails its CVE scan, serves no page or loads no extension of the Laravel set
- a warning only one architecture's `-O2` build of the image gives
- a memory error only AddressSanitizer finds, in the nightly run

A red push run opens one issue, or comments on the one still open, with the range of commits it tests, and a PR fixes it forward. No one reverts first: a merge commit can't land again cleanly once reverted, a revert hides a PR that merged on top of it, and it would revert healthy merges that failed only on the Mac's load. A red master gets a fix PR first, and other merges continue. A release publishes only a tree a full run passed, as the release's path explains.

## A release's path

A release is one PR. Its run tests and builds everything the release ships, and its merge publishes those same files. Nobody tags by hand.

1. The PR's last commit is the bump. It holds what `php sharp/mago/scripts/set-version.php <version>` writes, `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`, the version `Zend/tests/sharp/ri_sharp.phpt` expects, and in `sharp/CHANGELOG.md` the `## [<version>] - <YYYY-MM-DD>` heading over the release's notes, the `[Unreleased]` compare link moved to start at `v<version>`, and a `[<version>]` compare link from the last release's tag to `v<version>`.
2. A PR is a release's PR while origin has no tag for `sharp/mago/Cargo.toml`'s version, as `sharp/bin/check --changes` prints it. Its run runs the checks every PR runs and the Mac's jobs, and builds every release asset beside them: the three `mago` binaries with their attestations, Mago's source archives, their SHA-256s, the release's notes, and the runtime image for amd64 and arm64. It merges once its required checks pass.
3. The push of its merge commit finds the PR's run that passed `CI_PASSED` on the same tree, and runs no suite and builds nothing. `sharp.yml`'s `RELEASE` waits for `mago.yml`'s run of the same push to pass, and checks each asset against its SHA-256, its attestation and the tree it was built from. It publishes the GitHub release, which creates tag `v<version>` on the merge commit, and tags the image `ghcr.io/heyjordanparker/php-sharp:<version>`. `COMPOSER` then pushes tag `v<version>` to `heyJordanParker/php-sharp-composer`, and to `heyJordanParker/mago-sharp` with the release's SHA-256s.
4. When master moved after the PR's run started, no PR's run passed the merge's tree. That push runs every check, builds the assets itself, and publishes them once both workflows pass. A push run that a later push replaced, or that failed, publishes nothing. The version still has no tag, so the next PR is a release's PR too, and its merge publishes the release.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when Cass or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area. Until `docs/architecture/guards.md` exists, Cass writes each such mistake straight into a task file.

## Test runs

- A PR runs each PHP# engine mode once, on the one engine PHP# ships, the thread-safe one FrankenPHP runs, in the configurations deployments run. Linux runs plain PHP, opcache shared memory twice per process and the tracing JIT, 3 modes. A release's PR also runs those 3 modes on arm64 Linux, and the first 2 on macOS, where Apple silicon has no thread-safe JIT. Every night, master runs the 3 modes on Linux under AddressSanitizer. A PR can't merge until its runs pass. A PR that changes only documentation skips them, and GitHub counts the skipped checks as passing.
- A builder's end gate is `sharp/bin/check`, before the PR opens and after each fix: it runs the checks the PR's required jobs run, on this machine, each gate the change since the merge base with `origin/master` needs, as `sharp/README.md` lists them, and ends with each gate's time. A gate CI runs in the `sharp/docker/` image runs there, and a gate CI runs on its runner runs on the Mac, so a check fails here when it would fail in CI. It needs `typos`, and for a change to Mago what `sharp/bin/coverage` names: `cargo install --locked typos-cli` and `cargo install --locked cargo-llvm-cov@<the version mago.yml installs>` once per Mac.
- While a builder iterates, it runs the part its change reaches: `sharp/bin/test --linux` for the PHP# suite in the three modes CI runs, `sharp/bin/test --mago` for Mago's workspace tests, or one gate, as `sharp/bin/check mago-checks`. Every one of them runs the same checks the end gate runs, so a pass while iterating holds at the end. A plain `cargo` in `sharp/mago/` runs the machine's default toolchain, 1.92.0 on the builders' Mac, which is older than the `rust-version` Mago needs, so the scripts run the toolchain `ext/sharp/rust-toolchain.toml` pins, and Mago builds in the folder `sharp/bin/test` builds it in, so a slot keeps every Mago build in one folder.
- Every build and test runs at most as many jobs as `sharp/bin/jobs` prints, 6 on the builders' Mac, which has 12 cores and 64 GiB and runs other builders' work beside them. `CARGO_BUILD_JOBS` sets another number.
- `sharp/bin/coverage` is the one coverage measurement. CI's `patch-coverage` job runs it on the PR's merge commit, and the end gate against the merge base with `origin/master`, so merge the latest `origin/master` before the end gate.
- One `sharp/bin/test` runs per worktree at a time, and each run of it and of `sharp/bin/bench` stands on its own:
  - `sharp/bin/bench` compiles a copy of its rows inside its own container, so it runs beside `sharp/bin/test` in the same worktree. The exception is `sharp/bin/test --linux`: it and `sharp/bin/bench` build `mago` in the same `sharp/build/linux-<arch>/mago/`, where either one's `sharp/bin/build-mago` can delete the `mago` the other runs, so they don't run at once.
  - A run that failed or was stopped leaves nothing that breaks the next one.
  - Every `sharp/bin/test` mode, `--linux` included, works the same in a git worktree as in the main checkout.

## Platforms

PHP# supports macOS on Apple silicon, for development, and glibc Linux on arm64 and x86_64: the Linux VMs on the Mac and the Hetzner servers. CI and releases build for those only.

## Where things are written

- `docs/spec.md`: the language, as the Architect approved it. CI compiles and runs its examples, and `sharp/baseline/spec` lists the ones that fail today, which is the distance to v1. Git keeps no empty folder, so when none fails the folder is missing, and `sharp/bin/spec` reads a missing folder as an empty list.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why, once the change lands on master.
- `~/Developer/php-sharp/sharp/agents/architecture.md`: the architecture of the change in flight, which Cass writes for builders. Git ignores it, and it moves into `docs/architecture/` with the PR.
- `docs/how-we-work.md`: this file.
- `~/Developer/php-sharp/sharp/agents/`: task files. Git ignores them, so they exist only in that checkout and not in the worktree slots.
