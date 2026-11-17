# Ship a release

`docs/how-we-work.md`'s "A release's path" says what CI does with a release. These are the maintainer's steps.

A release takes what is committed when its bump starts. Work found later goes in the next release, so a release never waits on a growing list. When master's own CI is red, its fix ships before anything else merges.

A change to `sharp/bin/spec` goes in a PR that carries nothing else but Markdown, which `SPEC_GUARD` requires. Open it from a branch created through the GitHub API, so the shared checkout never switches branch, and merge it before the release PR.

## 1. Bump the version as the batch's last commit

These files change, and nothing else:
- what `php sharp/mago/scripts/set-version.php <version>` writes
- `PHP_SHARP_VERSION` in `ext/sharp/php_sharp.h`
- the version `Zend/tests/sharp/ri_sharp.phpt` expects
- in `sharp/CHANGELOG.md`, the `## [<version>] - <YYYY-MM-DD>` heading over the notes, the `[Unreleased]` link from `v<version>`, and a `[<version>]` link from the last tag

Stage those files by name, never with `git add -A`, because builders' work in progress shares the tree. Commit with `chore: release <version>`.

## 2. Open the PR and watch its checks

Push the branch, then run `gh pr create --body-file <file>`. The body says what users can do now, the fixes with the issues they close, and how to upgrade past a break.

Watch `gh pr checks` with a Monitor that prints each failed check and exits when none is pending. A failed check gets fixed on the branch at its root, with a test that fails first, never rerun blindly. A builder fixes it, the one whose issue it came from when it's still running, else a fresh one, and it takes the next free slot ahead of every other issue. The maintainer never edits code or runs cargo.

## 3. Merge only the head the checks covered

```bash
gh pr merge <number> --repo heyJordanParker/php-sharp --merge --match-head-commit <40-character head sha>
```

## 4. Publish

The merge commit's push publishes, and nobody tags by hand. Wait until its `PHP#` run passes: its `RELEASE` job publishes the GitHub release, which tags the merge commit `v<version>`, and `COMPOSER` tags both Composer packages. If a later merge replaced that push run, the next PR is a release's PR too, and its merge publishes.

## 5. Verify what users install

These hold:
- the GitHub release `v<version>` is not a draft and has every asset: `mago` for aarch64-apple-darwin, x86_64-unknown-linux-gnu and aarch64-unknown-linux-gnu, the SHA-256 sums and the source archives
- `docker buildx imagetools inspect ghcr.io/heyjordanparker/php-sharp:<version>` shows amd64 and arm64
- `heyJordanParker/mago-sharp` and `heyJordanParker/php-sharp-composer` carry tag `v<version>`

Then tell each consumer session the version and what changed for it, in one message.

## 6. Start the next batch

Run `git switch -c fix/batch-<next> origin/master`, which carries the builders' uncommitted work. Delete the merged local branch and the shipped task folders.
