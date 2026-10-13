# Mago

`sharp/mago/` holds PHP#'s checker and compiler, a fork of [Mago](https://github.com/carthage-software/mago). It used to be its own repository, `heyJordanParker/mago-sharp`, and php-sharp pinned one of its commits. Now one feature is one pull request: the checker, the engine, the standard library and the spec change together, and every gate runs on that pull request.

## History

`git subtree add` brought mago-sharp's `master` into `sharp/mago/` with its whole history, at mago-sharp commit `9339e1f7e`. So `git log -- sharp/mago` and `git blame` reach every mago-sharp and upstream Mago commit. The fork left upstream at Mago `1.51.0`.

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
