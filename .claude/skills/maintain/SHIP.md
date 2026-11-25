# Ship a release

`docs/how-we-work.md`'s "A release's path" says what CI does with a release. These are the maintainer's steps.

Every change reaches `master` through a pull request whose runs passed on its head: an `issue/<n>` branch's, which a lane pushes from the checkout's local `master`, a `blocking/` branch's, or a `spec/` branch's. Each carries auto-merge, so it merges the moment its checks pass, and `master`'s ruleset admits nothing that hasn't passed. A pull request that can't merge into `master` runs no check, so a conflict is a red check, fixed first by the lane whose commit caused it.

Every push to `master` publishes the next version from its own commit, with no step by hand and no version in the tree: once every check of `master`'s push run passes, its `RELEASE` job tags the commit with the deploy key in `PHP_SHARP_DEPLOY_KEY`, one patch past the newest tag, or one minor when the merged commit's subject has `!`. `master`'s push runs run one at a time, keeping only the newest waiting, so one release is in flight and the next carries every commit merged before it starts. The Mac release binary builds in the same `MACOS` job as the tests, so no other job takes the runner between them. A red push run makes no tag. The notes are each merged pull request's `## Changelog` section, which quotes only numbers measured on the frozen project or on CI's own runs.

A fix for a `blocking` issue that is green goes onto `master` in a pull request of its own, from branch `blocking/<issue>` built off `origin/master` with only its commits, so no other lane's commit can hold it, no lane push replaces its run, and its merge publishes it. Once it merges, the lane that built it runs `git merge origin/master` in the checkout before its next push, so the checkout's `master`, which every lane's pull request stacks on, carries the landed commit and never conflicts with it.

The stack lands whole when its top pull request's run is green. When that run is red, the maintainer names the lane whose commit failed the check, and that lane fixes or reverts it on top of the stack the same tick, so the next run lands every other lane's commits. When `master`'s own CI is red, its fix lands before anything else.

A change to `sharp/bin/spec` goes in a PR that carries nothing else but Markdown, which `SPEC_GUARD` requires. Open it from a branch `spec/<sha>` off `master`, created through the GitHub API, so the shared checkout never switches branch, and merge it first: until it merges, every pull request that carries the change fails `SPEC_GUARD`.

## 1. Watch the push run

A failed check of `master`'s push run gets fixed at its root, with a test that fails first, never rerun. The maintainer reads every failure and posts each cause the same tick as a `Brief update:` on the issue whose commit caused it, and that fix goes first in its lane's next batch. The maintainer never edits code or runs cargo.

## 2. Verify what users install

These hold:
- the GitHub release `v<version>` is not a draft and has every asset: `mago` for aarch64-apple-darwin, x86_64-unknown-linux-gnu and aarch64-unknown-linux-gnu, the SHA-256 sums and the source archives
- `docker buildx imagetools inspect ghcr.io/heyjordanparker/php-sharp:<version>` and `:<version>-dev` each show amd64 and arm64
- `heyJordanParker/mago-sharp` and `heyJordanParker/php-sharp-composer` carry tag `v<version>`

What a release shipped is the merged pull requests between its tag and the tag before it, read with `gh api repos/heyJordanParker/php-sharp/compare/v<previous>...v<version> --jq '.commits[]|select(.parents|length==2)|.commit.message|split("\n")[0]'` that turn, never from memory of what merged nearby: several pushes publish in a row, each its own version. Then tell each consumer session the version and what changed for it, in one message, and close each issue the release shipped.
