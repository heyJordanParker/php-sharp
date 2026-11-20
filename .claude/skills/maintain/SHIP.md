# Ship a release

`docs/how-we-work.md`'s "A release's path" says what CI does with a release. These are the maintainer's steps.

Every lane commits and pushes to one branch, `next`, for good. Each tick runs `sharp/bin/land`, which opens a pull request from `land/<sha>` with auto-merge for the newest commit of `next` whose `PHP#` and `Mago` runs passed, and `master`'s ruleset admits nothing that hasn't passed. A change to `sharp/bin/spec` reaches `master` in a pull request of its own first, and `next` takes it from `master`.

A release is cut the moment `next` holds a green fix that is `blocking`, a speed gain, or a change consumers must rewrite code for, and it takes everything committed by then. Work found later goes in the next release, and no builder ever stops: a release is one commit on `next`, the version bump, and it publishes when that commit lands on `master`. When `master`'s own CI is red, its fix lands before anything else.

A change to `sharp/bin/spec` goes in a PR that carries nothing else but Markdown, which `SPEC_GUARD` requires. Open it from a branch created through the GitHub API, so the shared checkout never switches branch, and merge it first.

## 1. Bump the version on `next`

These files change, and nothing else:
- what `php sharp/mago/scripts/set-version.php <version>` writes: the root package's `version` in `sharp/mago/Cargo.toml`, the one place the release version lives, its line in `sharp/mago/Cargo.lock`, and the versioned docs
- the engine's version, which `RELEASE_ASSETS` refuses unless it equals `mago`'s: `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`, and the `Version =>` line of `Zend/tests/sharp/ri_sharp.phpt`
- in `sharp/CHANGELOG.md`, the `## [<version>] - <YYYY-MM-DD>` heading over the notes, the `[Unreleased]` link from `v<version>`, and a `[<version>]` link from the last tag

Builders' uncommitted work shares the tree, so build the bump from HEAD's copies of those files in the scratchpad, `set-version.php` included, and commit it from a private index as `/implement` step 5 does, as `chore: release <version>`. Then write the same edits into the working tree, keeping every builder's uncommitted line under `## [Unreleased]`. Push it, and write the pull request's body with `gh pr edit --body-file <file>`: what users can do now, the fixes with the issues they close, and how to upgrade past a break.

## 2. Watch the run that lands the bump

Watch `gh pr checks` with a Monitor that prints each failed check and exits when none is pending. A failed check gets fixed on `next` at its root, with a test that fails first, never rerun blindly. The maintainer reads every failure and sends each cause to the lane whose commit caused it in the same tick, and that fix takes the lane ahead of every other issue. The maintainer never edits code or runs cargo.

## 3. Publish

The merge commit's push builds the release from the tree the run passed, and publishes it, and nobody tags by hand. Wait until its `PHP#` run passes: `RELEASE_ASSETS` and `IMAGE` build the assets and the image, `IMAGE_ARM64` runs a compiled class on the arm64 image, `RELEASE` publishes the GitHub release, which tags the merge commit `v<version>`, and `COMPOSER` tags both Composer packages. A failed build there is fixed forward on `next`, and the next landing publishes, since the version still has no tag.

## 4. Verify what users install

These hold:
- the GitHub release `v<version>` is not a draft and has every asset: `mago` for aarch64-apple-darwin, x86_64-unknown-linux-gnu and aarch64-unknown-linux-gnu, the SHA-256 sums and the source archives
- `docker buildx imagetools inspect ghcr.io/heyjordanparker/php-sharp:<version>` and `:<version>-dev` each show amd64 and arm64
- `heyJordanParker/mago-sharp` and `heyJordanParker/php-sharp-composer` carry tag `v<version>`

Then tell each consumer session the version and what changed for it, in one message, and ask the reporter of each closed issue to time it on their project. The answer goes on the issue. Close each issue the release shipped.
