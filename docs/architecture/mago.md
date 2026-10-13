# Mago

`sharp/mago/` holds PHP#'s checker and compiler, a fork of [Mago](https://github.com/carthage-software/mago). It used to be its own repository, `heyJordanParker/mago-sharp`, and php-sharp pinned one of its commits. Now one feature is one pull request: the checker, the engine, the standard library and the spec change together, and every gate runs on that pull request.

## History

`git subtree add` brought mago-sharp into `sharp/mago/` with its whole history, at mago-sharp commit `625c278f8`, the commit php-sharp's engine ran then. So `git log -- sharp/mago` and `git blame` reach every mago-sharp and upstream Mago commit. The fork left upstream at Mago `1.51.0`. The mago-sharp commits merged after `625c278f8` come in through the subtree pulls below.

`heyJordanParker/mago-sharp` is now a read-only mirror. On every push to `master` and every `v*` tag, the `COMPOSER` job in `.github/workflows/sharp.yml` splits `sharp/mago/` with `splitsh-lite` and pushes it there with the `MAGO_SHARP_DEPLOY_KEY` deploy key, as it splits `sharp/composer/` into `heyJordanParker/php-sharp-composer`. Packagist reads `heyjordanparker/mago-sharp` from the mirror. The push never forces. The split starts at the commit that added `sharp/mago/`, so it shares no history with the mirror's commits from before the move, and the job skips the push of `master` with a warning until that branch is rewritten once. A tag pushes, because it is new. The mirror's `archive` branch keeps the old `master`, so every pinned commit stays fetchable.

## What builds from it

- `sharp/bin/test` builds `mago` from `sharp/mago/` into `sharp/build/<os>-<arch>/mago/`, and every test and spec run uses that binary. cargo rebuilds only what changed.
- `ext/sharp/sharp_unit.h` is the C header that `sharp/mago/crates/sharp-bridge/build.rs` generates. The engine and the `mago` built from the same tree always agree on it, so the header names no Mago commit.
- `.github/workflows/mago.yml` runs Mago's gates in `sharp/mago/`, and `.github/workflows/mutants.yml` runs `cargo mutants` on `master`.
- A `v*` tag runs `.github/workflows/release.yml`. It publishes `mago` for every platform as assets of php-sharp's release, at the version PHP# has. `install.sh`, `mago self-update` and the Composer package `heyjordanparker/mago-sharp` download from those releases.

`sharp/mago/.gitattributes` marks Mago's sources `export-ignore`, so `git archive` of php-sharp leaves them out, and the runtime image never builds Mago.

## Taking an upstream Mago release

Pull an upstream tag into `sharp/mago/` on a branch of its own, then open one pull request for it:

```sh
git subtree pull --prefix=sharp/mago https://github.com/carthage-software/mago.git 1.56.3 -m "Merge Mago 1.56.3 into sharp/mago"
```

The fork shares upstream's history, so git merges from the last upstream commit the fork took and conflicts only where the fork changed the same lines. A file the fork deleted and upstream changed, such as `SPONSORS.md` or a workflow under `sharp/mago/.github/`, stays deleted: resolve it with `git rm`. A change to upstream's `ci.yml` that PHP# needs goes into `.github/workflows/mago.yml` by hand. Nothing is filed upstream.

## Moving an open engine pull request off its pin

An open php-sharp pull request that pins a mago-sharp commit merges `origin/master`, and replaces its re-pin with one pull of its pinned commit. Its `ext/sharp/sharp_unit.h` then becomes the header the pulled bridge writes, as the last section says.

```sh
git subtree pull --prefix=sharp/mago https://github.com/heyJordanParker/mago-sharp.git <its pinned commit> -m "Merge mago-sharp <its pinned commit> into sharp/mago"
```

The pull reports a modify/delete conflict on `sharp/mago/.github/actions/setup-lean/action.yml` and `sharp/mago/.github/workflows/ci.yml`. `.github/workflows/mago.yml` and `.github/actions/setup-lean/` already carry mago-sharp `master`'s versions, so `git rm` both files.

They land in this order, because each pinned commit holds the ones before it on mago-sharp `master`:

1. #114, `feat/collection-engine`, pulls `164ef442d79a01bd34d9e3b5d86bd9be6b7e0ac8`, the merge of mago-sharp #176, past its pin `a9f18829a`. Its tests of a change after `?.` on a `List` index need #176. It holds the pins of #110 and #113.
2. #110, `feat/slice-constructors`, and #113, `feat/slice-verification`, pin `c3a2b5af8` and `b9ffc3c5e`, which #114's pull already brought. They only merge `origin/master`.
3. #115, #102 and #118 pin commits of mago-sharp branches that never merged, `feat/extensions`, `feat/slice-generics` and `feat/foreign-classes`. They move over by subtree merge of that branch, as the next section says.

Then one pull request pulls the rest of mago-sharp's old `master`, which the mirror's `archive` branch keeps, and the open mago-sharp pull requests move over by subtree merge:

```sh
git subtree pull --prefix=sharp/mago https://github.com/heyJordanParker/mago-sharp.git archive -m "Merge mago-sharp's last master into sharp/mago"
```

## Moving an open mago-sharp branch

A mago-sharp pull request moves to php-sharp as a php-sharp branch that merges the mago-sharp branch into `sharp/mago/`. Its owner runs, in their slot's php-sharp worktree:

```sh
git fetch origin
git switch -c <branch> origin/master
git fetch https://github.com/heyJordanParker/mago-sharp.git <branch>
git subtree merge --prefix=sharp/mago FETCH_HEAD -m "Merge mago-sharp <branch> into sharp/mago"
git push -u origin <branch>
```

When the feature already has a php-sharp branch that pins a Mago commit, the owner merges `origin/master` into that branch instead of creating one, runs the same `git fetch` and `git subtree merge`, and resolves the conflict in `ext/sharp/sharp_unit.h` with the header `sharp/mago/crates/sharp-bridge/build.rs` writes to cargo's `OUT_DIR` when `sharp/bin/test` builds `mago`. The `LINUX` job fails while the two differ. A change the branch made to mago-sharp's `.github/workflows/ci.yml` shows as a modify/delete conflict: `git rm` the file, and make the change in `.github/workflows/mago.yml`. Then the owner opens the php-sharp pull request and closes the mago-sharp one with a link to it.
