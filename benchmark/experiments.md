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
