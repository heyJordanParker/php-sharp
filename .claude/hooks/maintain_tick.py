#!/usr/bin/env python3
import glob
import json
import os
import re
import subprocess
import sys
import time

TICK = "Maintainer tick"
STATE = os.path.expanduser("~/.claude/orchestrate/php-sharp/state.md")
SUBAGENTS = os.path.expanduser("~/.claude/projects/-Users-jordan-Developer-php-sharp")
IDLE = 10


def container_mounts():
    """Every running process's command line and every running container's mounted folders, so a lane whose script or
    container runs in its worktree counts as working."""
    commands = subprocess.run(["ps", "-axo", "command="], capture_output=True, text=True).stdout
    running = subprocess.run(["docker", "ps", "-q"], capture_output=True, text=True).stdout.split()
    if not running:
        return commands
    return commands + subprocess.run(["docker", "inspect", "--format", "{{range .Mounts}}{{.Source}} {{end}}", *running],
                                     capture_output=True, text=True).stdout


def free_gib():
    return int(subprocess.run(["df", "-g", "/System/Volumes/Data"], capture_output=True, text=True).stdout.split("\n")[1].split()[3])


def unrunnable_steps():
    """Each `sharp/bin/` script a maintain or implement SOP names that does not exist: SHIP.md named sharp/bin/ship
    from 11-24 10:53 while no such script existed, so its blocking-fix and red-stack steps could not run."""
    project = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    missing = []
    for sop in sorted(glob.glob(os.path.join(project, ".claude", "skills", "*", "*.md"))):
        with open(sop, encoding="utf-8") as fh:
            named = set(re.findall(r"`(sharp/bin/[\w-]+)", fh.read()))
        missing += [f"{os.path.relpath(sop, project)} names {script}, which does not exist"
                    for script in sorted(named) if not os.path.exists(os.path.join(project, script))]
    return missing


def lanes():
    """Each lane state.md names, with its builder's type and minutes since its transcript last changed."""
    try:
        lines = [line for line in open(STATE, encoding="utf-8") if line.startswith("- ") and ": " in line]
    except OSError as error:
        return f"LANES: {STATE} could not be read: {error}."
    rows, stalled, owners = [], [], {}
    for line in lines:
        lane = line[2:].split(":", 1)[0]
        listed = re.search(r"files: ([^|]+)", line)
        for path in (listed.group(1).split(",") if listed else []):
            owners.setdefault(path.strip(), []).append(lane)
        match = re.search(r"agent (\w+) (\S+)", line)
        if not match:
            if line.split(":", 1)[1].strip().startswith("no builder"):
                rows.append(f"  {lane:<9} no builder")
                stalled.append(f"{lane} has no builder")
            continue
        agent, kind = match.group(1), match.group(2).rstrip(",|")
        logs = glob.glob(os.path.join(SUBAGENTS, "*", "subagents", f"agent-{agent}.jsonl"))
        if not logs:
            rows.append(f"  {lane:<9} {agent} {kind}: no transcript found")
            stalled.append(f"{lane}'s builder has no transcript")
            continue
        idle = int((time.time() - os.path.getmtime(logs[0])) // 60)
        rows.append(f"  {lane:<9} {agent} {kind:<17} idle {idle} min")
        if idle >= IDLE and f"worktrees/{lane.lower()}" not in container_mounts():
            stalled.append(f"{lane} idle {idle} min")
        if kind != "claude":
            stalled.append(f"{lane} runs {kind}, not claude")
    stalled += [f"{' and '.join(held)} both hold {path}" for path, held in owners.items() if len(held) > 1]
    # The disk check lives here as well as in SKILL.md step 3: that step was deleted on 11-23 00:48, and on 11-24 the
    # disk sat at 0.5 to 4 GiB from 06:56 to 10:14 with the lanes stopped (log.md 11-24 10:14).
    free = free_gib()
    if free < 60:
        stalled.append(f"disk {free} GiB free: SKILL.md step 3" + (", and no build starts" if free < 20 else ""))
    stalled += unrunnable_steps()
    head = "LANES:"
    act = ("ACT FIRST: " + "; ".join(stalled) + ".") if stalled else "Every builder worked in the last 30 minutes."
    return "\n".join([head, *rows, act])


def targets():
    """The orchestration and speed targets, each measured now, from targets.py."""
    script = os.path.expanduser("~/.claude/orchestrate/php-sharp/targets.py")
    try:
        out = subprocess.run([sys.executable, script], capture_output=True, text=True, timeout=30)
    except subprocess.TimeoutExpired:
        return f"TICK INPUT MISSING: {script} took over 30 s. Fix that before any other step of this tick."
    if out.returncode != 0 or not out.stdout.strip():
        return f"TICK INPUT MISSING: {script} exited {out.returncode}: {out.stderr.strip()[-400:]}. Fix that first."
    return out.stdout.strip()


def archive_finished():
    """Moves each intake entry that left intake to intake-archive.md, so intake holds only the queue: on 11-24 it held
    150 entries, 89 of them finished (TRIAGE.md: an entry leaves intake when every box that applies is checked). An
    entry has left when its heading says `(left intake`, or every box it carries is checked and its heading marks no
    entry still being worked. Only finished entries move, so the queue's order and its one entry in progress stay."""
    intake = os.path.expanduser("~/.claude/orchestrate/php-sharp/intake.md")
    with open(intake, encoding="utf-8") as fh:
        head, *entries = re.split(r"(?m)^(?=## )", fh.read())
    working = ("(in progress)", "(waiting", "(paused")

    def finished(entry):
        heading, boxes = entry.split("\n", 1)[0], re.findall(r"(?m)^\s*- \[([ x])\]", entry)
        return "(left intake" in heading or (bool(boxes) and " " not in boxes and not any(w in heading for w in working))

    done = [entry for entry in entries if finished(entry)]
    if not done:
        return
    with open(os.path.expanduser("~/.claude/orchestrate/php-sharp/intake-archive.md"), "a", encoding="utf-8") as fh:
        fh.write("".join(done))
    with open(intake, "w", encoding="utf-8") as fh:
        fh.write(head + "".join(entry for entry in entries if not finished(entry)))


def report():
    """The status box and block every reply opens with, whole: disk, both bars, each target and each lane."""
    # The disk is managed in the background on every report: census.py deletes what the loop made and nobody uses.
    subprocess.Popen([sys.executable, os.path.expanduser("~/.claude/orchestrate/php-sharp/census.py")],
                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
    archive_finished()
    out = targets()
    if out.startswith("TICK INPUT MISSING"):
        return out
    lines = out.splitlines()
    # The box headlines the next release's estimate, the one date the language owner asks for first.
    eta = next((re.match(r"\s*release ETA: (\S+) (\d\d-\d\d \d\d:\d\d)", line) for line in lines
                if line.strip().startswith("release ETA: ")), None)
    box = f"{eta.group(1)} ships {eta.group(2)}" if eta else "No release in flight"
    block = [f"Disk  {free_gib()} GiB free", "", "Progress"]
    for line in lines:
        found = re.match(r"\s+(ALL|\S+) (\[[^\]]+\])\s+(.*)", line)
        if found and not line.strip().startswith("#"):
            label = "All issues" if found.group(1) == "ALL" else found.group(1)
            note = re.sub(r"(\d+) of (\d+) issues closed, ETA", r"\1 of \2 closed, done by", found.group(3))
            note = re.sub(r"\b1 issues", "1 issue", note)
            block.append(f"  {label:<11} {found.group(2)}  {note}")
    start = next(i for i, line in enumerate(lines) if line.startswith("TARGETS"))
    # Each target's value 24 hours ago, from the TREND lines, so a target that rose shows beside its number.
    trend = {m.group(1): (m.group(2), m.group(3)) for m in
             (re.match(r"\s+(.+?): ([\d.]+) -> [\d.]+ (ROSE|fell|held)$", line) for line in lines) if m}
    goals = []
    for line in lines[start + 1:]:
        found = re.match(r"\s+(.+?): ([\d.]+)( ?[A-Za-z]*) / ([\d.]+)( ?[A-Za-z]*) (MET|x([\d.]+))(?:, run \d+ (.+))?$", line)
        if found:
            name = re.sub(r",? (mean|last green)$", "", found.group(1)).replace("->", " to ")
            off = 0.0 if found.group(6) == "MET" else float(found.group(7))
            when = f"  ({found.group(8)})" if found.group(8) else ""
            before, moved = trend.get(found.group(1), ("", ""))
            day = f"{float(before):.1f}{found.group(3)}" if before else "none"
            mark = "  ROSE" if moved == "ROSE" else ""
            goals.append((off, f"  {name:<36} {found.group(2) + found.group(3):>12}  {day:>12}  {found.group(4) + found.group(5):>8}  "
                                f"{'met' if off == 0 else f'{off:.1f}x off':>9}{mark}{when}"))
    block += ["", f"  {'Targets, worst first':<36} {'now':>12}  {'24 h ago':>12}  {'goal':>8}  {'':>9}", *[row for _, row in sorted(goals, reverse=True)]]
    block += [f"  commit to shipped {line.split('by release: ', 1)[1]}" for line in lines if line.strip().startswith("by release: ")]
    block += [f"  {line.strip()}" for line in lines if line.strip().startswith("release ETA: ")]
    mounts = container_mounts()
    block += ["", "Lanes"]
    for row in lanes().splitlines()[1:-1]:
        parts = row.split()
        lane = parts[0]
        if "no builder" in row:
            state = "no builder"
        elif f"worktrees/{lane.lower()}" in mounts:
            state = "working, its run is going"
        elif "idle" in parts:
            minutes = int(parts[parts.index("idle") + 1])
            state = "working" if minutes < IDLE else f"idle {minutes} min"
        else:
            state = " ".join(parts[2:])
        block.append(f"  {lane:<8} {state}")
    width = len(box) + 2
    return "\n".join(["```text", "╔" + "═" * width + "╗", f"║ {box} ║", "╚" + "═" * width + "╝", "```", "",
                      "```text", *block, "", "Now   ", "Next  ", "```"])


def main():
    # `lanes` prints the lane lines alone, for the maintainer's watcher between ticks, and `report` the status block.
    if sys.argv[1:] == ["lanes"]:
        print(lanes())
        return 0
    if sys.argv[1:] == ["report"]:
        print(report())
        return 0
    try:
        event = json.load(sys.stdin)
    except ValueError:
        return 0
    if event.get("prompt", "").strip() != TICK:
        return 0
    # Two runs of this hook, `status` and `sop`, each stay under the size Claude Code shows inline.
    if sys.argv[1:] == ["status"]:
        try:
            with open(os.path.expanduser("~/.claude/orchestrate/php-sharp/bench.md"), encoding="utf-8") as fh:
                bench = fh.read().strip()
        except OSError as error:
            bench = f"SPEED NUMBERS: ~/.claude/orchestrate/php-sharp/bench.md could not be read: {error}."
        context = f"{lanes()}\n\n{targets()}\n\n{bench}"
    else:
        # `sop <file>` injects one file of the maintain Skill that every tick follows, SKILL.md unless another is named,
        # so a tick never runs from memory of it.
        name = sys.argv[2] if len(sys.argv) > 2 else "SKILL.md"
        project = os.environ.get("CLAUDE_PROJECT_DIR") or event.get("cwd", "")
        path = os.path.join(project, ".claude", "skills", "maintain", name)
        try:
            with open(path, encoding="utf-8") as fh:
                text = fh.read()
        except OSError as error:
            text = f"The SOP at {path} could not be read: {error}. Read it before anything else."
        if text.startswith("---\n"):
            text = text.split("\n---\n", 1)[-1]
        context = f"Run this tick by .claude/skills/maintain/{name}:\n{text}"
    json.dump({"hookSpecificOutput": {"hookEventName": "UserPromptSubmit",
                                      "additionalContext": context}}, sys.stdout)
    return 0


if __name__ == "__main__":
    sys.exit(main())
