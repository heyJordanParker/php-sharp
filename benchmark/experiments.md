# Speed experiments

Every speed experiment, kept or dropped, one line each, newest last, so none is tried twice. Each count is one `benchmark/speed.php` counts, or a CI job's measured time. Before a speed change is designed, this file is searched for the same change, and one that repeats a dropped line needs a new reason.

`- MM-DD <target>: <change> (<issue>, <sha>): <count before> -> <count after>; kept | dropped: <why>`

- 11-20 CI test CPU: run the suite with nextest (#516, 49f44beb778): 336 -> 2,403 CPU-s; dropped: 7x the CPU, reverted in 8d77f9044bb
- 11-20 cold compile: stamp change (#359): 74.0 G -> 71.4 G instructions; kept
- 11-20 edit compile: read folder listings in one call (#359): 1.03 of 38.5 CPU-s at stake; dropped: the gain is small and a stale listing gives a wrong compile
- 11-20 Mac release build: profile-guided optimization from a stored profile (#516): dropped: a stored profile is a cache
- 11-21 edit compile: key the scan by file and settings (#359, d44834d86df): 46.10 G -> 45.79 G instructions; dropped: no gain
- 11-22 edit compile: encoded scans (#359): -47 MiB peak, +0.19 CPU-s, against a 0.14 CPU-s limit; dropped: more CPU
- 11-22 edit peak memory: drop the lowering inputs after use (#359, 4a39627efa0): -10.5 MiB, 16 of 16 pairs identical; kept
- 11-22 edit compile: record the analysis for replay (#359): +21.6 MiB peak, +0.31 CPU-s; dropped: costs more than it saves
- 11-23 law proving: `ite_self` goal shape (#359, 351532ae157): one law 0.229x, corpus 1.036x, one law 1.128x; dropped: the corpus grew, superseded by #712
- 11-24 edit compile: an extension host's reach parses only the files it reaches (#763, 1c7325326d4): the SPEED gate's +7.4% back to within 1% of master; kept
- 11-24 law proving: build a match's equations once (#703, cdbcf5c744c): no theorem raises a panic that costs more than its proof; kept
- 11-24 lane builds: background priority (`taskpolicy -b`) against utility (`taskpolicy -c utility`), one fixed loop at a load of 18: 99.6 s and 111.3 s wall, 21 to 23 CPU-s -> 6.3 s and 6.3 CPU-s; utility kept: background held every lane on the 4 efficiency cores
- 11-24 Mac test step: the spec and README examples in 36 slices of 10 weighed by their seconds, and the fixture compile on every slot (#784, 732e0bb80f3): test step 1,090 s -> 898 s, longest spec unit 765.9 s -> 202.6 s, last spec unit's end 543 s into the step (run 45389050052 -> 45470354190); kept
- 11-24 no-change compile after an unproven law: an unproven law is a refused file's error the compile's record keeps, its special case deleted (#359, PR #794): 62.56 G -> 3.41 G instructions, 1,187.7 -> 33.0 MiB; edit compile 66.79 G -> 66.84 G, 1,188.0 -> 1,041.6 MiB; kept
- 11-24 law proving: `auto` runs its `simp_all` once per goal (#782): thousand heartbeats on one thread, `backoffNeverShrinks` 121,071 -> 91,265 proved and 158,589 -> 108,687 unproven, `grossIsNets` 112,742 -> 74,100, `totalJoins` 72,473 -> 59,376, `totalJoinsFirst` 21,411 -> 19,351, `laterIsAfter` 201,426 -> 201,425, SPEED corpus unchanged within its spread of 5 (318,437 to 318,442 before, 318,440 to 318,441 after), no outcome changed; kept
- 11-24 lane builds: the engine's build ID names its sources' diff, not the clock, and only the root package reads mago-build-id (#783): one analyzer line 550 to 1,102 -> 468 CPU-s, with mago-engine, mago-extension, mago-syntax and mago-codex fresh, and a rebuild with no edit 36 CPU-s with no unit compiled; kept
