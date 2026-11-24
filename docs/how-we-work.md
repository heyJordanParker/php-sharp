# How we work

The language owner, the maintainer and a team of builders build PHP#. This file says how the team works. The maintainer owns it, and it changes only through a PR, so git keeps its history.

## Who does what

- **The language owner** decides the language: `docs/spec.md` and the proposals in `docs/decisions/`. The language owner approves or corrects proposals, and doesn't review code or plan work.
- **The maintainer** owns everything else: the plan, the order of work, the architecture, and this file. It starts every agent, reviews each task's architecture and capabilities before it starts, and checks that what lands meets its Verification.
- **A builder** is an agent that does one task: one PR, from a new branch to its approval.
- **A helper** is an agent that reads, searches or researches for the maintainer, and changes nothing.

## A PR's path to master

No agent reviews code. The review happens before the code exists, and the check after it is the task's Verification.

0. Before the maintainer founds the builder, it reviews the task's architecture and capabilities: every file and public API it changes, every capability it must keep, and the Verification that proves it. Verification names each case where the change could give a wrong result, a crash or a cost, as an input and the output it must give, in every mode CI runs. It is the check a code reviewer would have made.
1. The builder builds the task on its own branch `issue/<number>` off master, in its lane's own worktree, and opens the PR into master with auto-merge on. CI must pass, and its report shows each Verification condition met, with the command and the output it observed.
2. The maintainer checks that Evidence against each condition, and checks the PR's shape against the task: the files and public APIs it changes, and no stopgap. A miss goes back to the same builder, and the maintainer checks its fix the same way.
3. When every condition is met, the maintainer posts an approval on the PR.
4. GitHub's merge queue merges it: it runs every required check on the PR merged onto master and the changes ahead of it, and merges it when they pass.

A commit that finishes spec sections says so in its message, on its own line, exactly as `Completes: §6.1, §9`, so the PR that lands it checks the claim. The `COMPLETES` check fails while `sharp/baseline/spec` still lists an example of a named section.

A feature PR changes the spec, its examples, `sharp/baseline/spec` and the code together. It leaves `sharp/bin/spec`, which measures the examples, as master has it: that changes only in a PR of its own, beside Markdown. A feature PR may add or change harness classes in `sharp/harness/`. Its body lists each harness file, and its Verification proves that no stub makes an example pass falsely. When the builder's work makes listed examples pass, the failing run prints the `git rm` of each fixed example's file in `sharp/baseline/spec/`, and the entries leave through it. A listed example that fails with other errors than before stays on the list unchanged, because the list records which examples fail, not how. An example master already checked never joins the list unchanged.

New example-marker or run-comment syntax lands in `sharp/bin/spec` one PR before the examples use it, because `SPEC_GUARD` reads a PR's spec with the base's checker.

One feature is one PR: the checker in `sharp/mago/`, the engine, the standard library, the spec and their tests change together, and every CI gate runs on it.

A PR merges when its own required checks pass: `CI_PASSED`, `ci-passed`, `SPEC_GUARD` and `COMPLETES`. Each Linux suite runs in slices, and a PR's runs hold up to 18 of the 20 hosted jobs GitHub runs at once, as a release's PR's runs did. Test PR #381's runs ended in 12.0 and 13.2 minutes: `BUILD_LINUX` took 3.7, then `LINUX (2/2)` 7.6, and `mago.yml`'s `build` 6.9, then `tests (2/5)` 5.8. Every PR's run also runs the Mac's job, whatever the PR changed. No PR's run builds the release, since a PR's run only has to prove the tree compiles and passes its checks, which its test builds do: while a bump waited on `next`, the branch every lane pushed to before #722, every run there built the profile-guided release, and run 44997846679 took 45 minutes, 16.5 of them the release build alone after its checks had ended. A PR that is behind master but has no conflict merges without an update: its merge queue entry tests it merged onto master and the changes ahead of it, which is the tree that merges.

Each platform builds once per run, and the suites of that platform wait for that build and run on it. `sharp.yml`'s `BUILD_LINUX` builds the engine, `mago` and the workspace's test binaries, and each suite, `MAGO`'s slices of Mago's tests among them, takes their files with `sharp/bin/test --no-build`. A run has one Mac job, `sharp.yml`'s `MACOS`, on the Mac's one runner: it builds the engine `mago` links once, and runs every test there, where four Mac jobs queued one after another and three of them ran the engine step. This reverses the earlier rule that no job waits for another job's build: each job built for itself so that no build's minutes stood before every suite, but a release's version bump rebuilt every Mago crate in every one of them, and release PR #363's run built the same code in 8 Linux jobs, 4 `mago.yml` jobs and 3 Mac jobs, 238 of its 404 job-minutes on the three Mac runners. One build per platform costs its minutes once, before the suites, and frees the runners the copies held.

Every PR's run runs one job on the language owner's Mac: `MACOS`, which runs the PHP# suite, Mago's workspace tests and `plain-php` there. It runs every test the Linux jobs run, `tests/laws.rs` and the whole-project proof of `mago convert` among them, but three that can't run on macOS: the JIT, since Apple silicon has no thread-safe JIT, the upstream tests, whose baseline holds Linux's results, and `HOSTS`, which builds FrankenPHP in the `sharp/docker/` image. Every PR's run also builds the runtime image in its `IMAGE` jobs. Every lane PR's run joins one group, where a run in progress finishes and lands every commit below it, and a newer push replaces the run still waiting, since the newest lane PR holds every commit below it. A `release/` or `spec/` PR's run runs in a group of its own, where a newer push cancels its run in progress. Master runs one push at a time, a newer push replaces the one still waiting, and a run in progress finishes, since master's run publishes. Each merge queue entry runs on its own. A failed job cancels its PR's or its queue entry's run, which can no longer pass, so the newest push's run starts within a minute instead of waiting for the Mac job. Every run builds and tests everything from source, with no cache and no result of an earlier run: a PR, its merge queue entry and master's push run each run every check, the Mac's among them, so a run's length is what building and testing PHP# costs (#721). `ASAN` runs nightly, so a memory error only AddressSanitizer finds lands on master, and the nightly run finds it.

Each change lands alone (#722). Every lane pushed untested commits onto one shared branch, `next`, and one broken commit turned every later one red, so no fix landed until it was fixed: on 2026-11-23 one CI image commit kept `next` red for over two hours. Now each issue is its own branch and PR, and the merge queue tests each change merged onto master and the changes ahead of it, so master stays green and a broken change fails its own entry while the changes behind it go on. A PR starts the required runs because its builder opens it with the language owner's `gh` login, where one that `GITHUB_TOKEN` opened would start no workflow. A change to `sharp/bin/spec` reaches master in a PR of its own.

A red push run opens one issue, or comments on the one still open, with the range of commits it tests, and a PR fixes it forward. No one reverts first: a merge commit can't land again cleanly once reverted, a revert hides a PR that merged on top of it, and it would revert healthy merges that failed only on the Mac's load. A red master gets a fix PR first, and other merges continue. A release publishes only a tree a full run passed, as the release's path explains.

## A release's path

A release is one PR from `release/<version>` off master, the bump. Its runs and its merge queue entry run the same checks as every other, and the push that lands it on master builds the release's files and publishes them. Nobody tags or publishes by hand.

`sharp/bin/ship <commit>…` builds that branch from master and only the commits it names, so a fix ships alone however red the stack it was committed in is. It applies each commit's diff to a private index read from `origin/master`, adds the bump, and creates the branch's one commit through the GitHub API, so the shared checkout never switches branch. Then it opens the PR with auto-merge. A commit whose diff doesn't apply on master stops it, named, before it creates anything. `sharp/bin/ship --land <commit>…` builds `land/<commit>` the same way with no bump, so the stack's commits land without the one whose check failed.

1. The bump holds what `php sharp/mago/scripts/set-version.php <version>` writes, the version of `sharp/mago/Cargo.toml`'s root package, which the engine reads too, with its line in `sharp/mago/Cargo.lock` and the docs that name it, and in `sharp/CHANGELOG.md` the `## [<version>] - <YYYY-MM-DD>` heading over the release's notes, the `[Unreleased]` compare link moved to start at `v<version>`, and a `[<version>]` compare link from the last release's tag to `v<version>`.
2. Once master holds a version with no tag, the maintainer's tick runs `sharp/bin/tag`, which creates tag `v<version>` on the merge commit that first holds it, with the language owner's login. The release is that commit. GitHub refuses a workflow's `GITHUB_TOKEN` a tag on a commit whose workflows differ from master's, and master moves while a release builds, so two runs failed to create `v0.6.50` themselves.
3. A release publishes three parts: the GitHub release with `mago`'s files, the runtime images `ghcr.io/heyjordanparker/php-sharp:<version>` and `:<version>-dev`, and tag `v<version>` of `heyJordanParker/php-sharp-composer` and `heyJordanParker/mago-sharp`. `sharp/bin/check --release` prints which are missing, and a push to master publishes those. A rerun of the release commit's push run publishes the files and images it left unpublished, since only that run built them, and a later push pushes the Composer tags. A published part stays as it is.
4. The push of the release's commit builds its files: its run's `RELEASE_ASSETS` calls `release.yml`, which builds the three `mago` binaries, the macOS one with profile-guided optimization, with their attestations, Mago's source archives, their SHA-256s and the release's notes. Only that run builds them, since each attestation names its run's commit. Its `IMAGE` jobs build the images. A run whose failed jobs were rerun counts once its newest attempt passed.
5. `sharp.yml`'s `RELEASE` waits for every job of the run and for `mago.yml`'s run of the same push to pass, and checks each asset against its SHA-256, its attestation and the release's tree, and each image digest against the release's tree. It creates the GitHub release on the existing tag and publishes it, and tags the images. `COMPOSER` then pushes tag `v<version>` of the release commit's split to `heyJordanParker/php-sharp-composer`, with that commit's spec, and to `heyJordanParker/mago-sharp` with the published release's SHA-256s.

## Every mistake becomes a check

Agents make mistakes, and a rule written only in prose gets broken again. So when the maintainer or CI finds a mistake, the fix comes in two parts:

1. Fix the mistake.
2. Add the check that would have caught it: a test, a lint, a CI step or a Claude Code hook. The check fails on the mistake and passes on the fix, and the PR shows both runs.

A mistake that no check can catch yet goes in `docs/architecture/guards.md`, with what a check for it would need. Each entry becomes a task, and it runs before any new feature work in the same area. Until `docs/architecture/guards.md` exists, the maintainer writes each such mistake straight into a task file.

## Test runs

- A PR runs each PHP# engine mode once, on the one engine PHP# ships, the thread-safe one FrankenPHP runs, in the configurations deployments run. Linux runs plain PHP, opcache shared memory twice per process and the tracing JIT, 3 modes. Every PR also runs the first 2 on macOS, where Apple silicon has no thread-safe JIT. No job runs the suite on arm64 Linux: macOS on Apple silicon runs the arm64 code, and x86_64 Linux the Linux code, and the arm64 runtime image shows that its engine starts and loads PHP#. Every night, master runs the 3 modes on Linux under AddressSanitizer. A PR can't merge until its runs pass. Every PR runs them, whatever it changed.
- Every build and test runs at most as many jobs as `sharp/bin/jobs` prints, 6 on the builders' Mac, which has 12 cores and 64 GiB and runs other builders' work beside them. `CARGO_BUILD_JOBS` sets another number: the Mac's runner sets it to the Mac's 12 cores, since it runs one job at a time.
- One `sharp/bin/test` runs per worktree at a time, and each run of it and of `sharp/bin/bench` stands on its own:
  - `sharp/bin/bench` compiles a copy of its rows inside its own container, so it runs beside `sharp/bin/test` in the same worktree. The exception is `sharp/bin/test --linux`: it and `sharp/bin/bench` build `mago` in the same `sharp/build/linux-<arch>/mago/`, where either one's `sharp/bin/build-mago` can delete the `mago` the other runs, so they don't run at once.
  - A run that failed or was stopped leaves nothing that breaks the next one.
  - Every `sharp/bin/test` mode, `--linux` included, works the same in a git worktree as in the main checkout.

## Platforms

PHP# supports macOS on Apple silicon, for development, and glibc Linux on arm64 and x86_64: the Linux VMs on the Mac and the Hetzner servers. CI and releases build for those only.

## Where things are written

- `docs/spec.md`: the language, as the language owner approved it. CI compiles and runs its examples, and `sharp/baseline/spec` lists the ones that fail today, which is the distance to v1. Git keeps no empty folder, so when none fails the folder is missing, and `sharp/bin/spec` reads a missing folder as an empty list.
- `docs/decisions/`: each approved language proposal, with the options it chose between.
- `docs/architecture/`: how the checker and the engine are built, and why, once the change lands on master.
- `~/Developer/php-sharp/sharp/agents/architecture.md`: the architecture of the change in flight, which the maintainer writes for builders. Git ignores it, and it moves into `docs/architecture/` with the PR.
- `docs/how-we-work.md`: this file.
- `~/Developer/php-sharp/sharp/agents/`: task files. Git ignores them, so they exist only in that checkout.
