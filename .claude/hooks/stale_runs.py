#!/usr/bin/env python3
"""After an edit, names each build or test run on the edited file's tree that started before it.

Such a run builds the tree as it was, so its result covers none of the edit: on 11-25 a builder applied a ruling while
its test run went on, then reported that run's pass as if it checked the ruling. sharp/bin/record runs every build,
test and measurement, so each one is a `record` process whose command names its tree's root.
"""
import json
import os
import subprocess
import sys

event = json.load(sys.stdin)
path = (event.get("tool_input") or {}).get("file_path") or ""
if not path or "/.claude/" in path:
    sys.exit(0)
folder = os.path.dirname(path)
if not os.path.isdir(folder):
    sys.exit(0)
root = subprocess.run(["git", "-C", folder, "rev-parse", "--show-toplevel"], capture_output=True, text=True).stdout.strip()
if not root:
    sys.exit(0)
record = f"{root}/sharp/bin/record "
runs = {}
for line in subprocess.run(["ps", "-axo", "pid=,etime=,command="], capture_output=True, text=True).stdout.splitlines():
    pid, elapsed, command = line.strip().split(None, 2)
    if record in command and " -- " in command:
        name = command.split(record, 1)[1].split(" -- ", 1)[0]
        runs.setdefault(name, f"{name} (PID {pid}, running {elapsed})")
if runs:
    print(f"These runs build {root} as it was before this edit, so their results don't cover it: "
          f"{'; '.join(runs.values())}. Stop each one you started, and run it again after your last edit.", file=sys.stderr)
    sys.exit(2)
