# Ship a release

`docs/how-we-work.md`'s "A release's path" says what CI does with a release. These are the maintainer's steps.

Every change reaches `master` through a pull request whose runs passed on its head: an `issue/<n>` branch's, which a lane pushes from the checkout's local `master`, or a `release/` or `spec/` branch's. Each carries auto-merge, so it merges the moment its checks pass, and `master`'s ruleset admits nothing that hasn't passed. A pull request that can't merge into `master` runs no check, so a conflict is a red check, fixed first by the lane whose commit caused it.

One release is in flight at a time, since a second `release/` pull request cancels the first one's run. The moment one publishes, the next is cut from `master`'s tip if `master` holds a commit no release holds, so a landed fix waits at most one release's run. A fix for a `blocking` issue is the next release, alone, the moment its commit is green: `sharp/bin/ship <commit>…` builds `release/<version>` through the GitHub API from `origin/master` plus only those commits' changes and the bump, so no other lane's commit can hold it. Its notes quote only numbers measured on the frozen project or on CI's own runs, never on a generated project.

The stack lands whole when its top pull request's run is green. When that run is red, the maintainer names the lane whose commit failed the check, and that lane reverts the commit with one `git revert` on top of the stack the same tick, so the next run lands every other lane's commits. The failing lane's fix goes on top of the next stack. A commit that needs a measurement before it lands is reverted the same way. When `master`'s own CI is red, its fix lands before anything else.

A change to `sharp/bin/spec` goes in a PR that carries nothing else but Markdown, which `SPEC_GUARD` requires. Open it from a branch `spec/<sha>` off `master`, created through the GitHub API, so the shared checkout never switches branch, and merge it first: until it merges, every pull request that carries the change fails `SPEC_GUARD`. `SPEC_GUARD` compares the head's files with `master`'s tip, so the `spec/` branch carries the checkout's file byte for byte, and an `origin/master` copy that differs from the checkout's goes onto the local `master` from a private index the moment it merges.

A CI commit, touching only `.github/` and `sharp/bin/`, runs on every pull request, so it lands alone the same way, before any lane runs it: the CI lane commits it in `worktrees/ci` after `git merge --ff-only origin/master`, pushes `HEAD:refs/heads/ci/<issue>`, opens its pull request into `master` with auto-merge, and once it merges runs `git merge --no-edit origin/master` in the shared checkout.

## 1. Bump the version

These files change, and nothing else:
- what `php sharp/mago/scripts/set-version.php <version>` writes: the root package's `version` in `sharp/mago/Cargo.toml`, the one place the release version lives and the one the engine's build reads, its line in `sharp/mago/Cargo.lock`, and the versioned docs
- in `sharp/CHANGELOG.md`, the `## [<version>] - <YYYY-MM-DD>` heading over the notes, the `[Unreleased]` link from `v<version>`, and a `[<version>]` link from the last tag
- `sharp/baseline/speed.json`, the `speed` artifact of the green `Mago` run on the commit the bump releases, `gh run download <run> --name speed`, so the next commits are counted against what this release proves and compiles in

Builders' uncommitted work shares the tree, so build the bump from HEAD's copies of those files in the scratchpad, `set-version.php` included, and commit it from a private index as `/implement` step 5 does, as `chore: release <version>`. Then write the same edits into the working tree, keeping every builder's uncommitted line under `## [Unreleased]`. Push it, and write the pull request's body with `gh pr edit --body-file <file>`: what users can do now, the fixes with the issues they close, and how to upgrade past a break.

## 2. Watch the run that lands the bump

Watch `gh pr checks` with a Monitor that prints each failed check and exits when none is pending. A failed check gets fixed at its root, with a test that fails first, never rerun blindly. The maintainer reads every failure and posts each cause the same tick as a `Brief update:` on the issue whose commit caused it, and that fix goes first in its lane's next batch. The maintainer never edits code or runs cargo.

## 3. Publish

The merge commit's push builds the release from the tree the run passed, and publishes it. A workflow's token can't create a tag on a commit that changes `.github/workflows`, so `sharp/bin/tag` tags `v<version>` at the merge commit, with the owner's token, the moment `master` holds an untagged version. Wait until its `PHP#` run passes: `RELEASE_ASSETS` and `IMAGE` build the assets and the image, `IMAGE_ARM64` runs a compiled class on the arm64 image, `RELEASE` publishes the GitHub release, which tags the merge commit `v<version>`, and `COMPOSER` tags both Composer packages. A failed build there is fixed forward, and the next merge publishes, since the version still has no tag.

## 4. Verify what users install

These hold:
- the GitHub release `v<version>` is not a draft and has every asset: `mago` for aarch64-apple-darwin, x86_64-unknown-linux-gnu and aarch64-unknown-linux-gnu, the SHA-256 sums and the source archives
- `docker buildx imagetools inspect ghcr.io/heyjordanparker/php-sharp:<version>` and `:<version>-dev` each show amd64 and arm64
- `heyJordanParker/mago-sharp` and `heyJordanParker/php-sharp-composer` carry tag `v<version>`

Then tell each consumer session the version and what changed for it, in one message, and close each issue the release shipped.
