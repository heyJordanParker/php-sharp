#!/usr/bin/env python3
import calendar
import concurrent.futures
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


def waits_on_its_run(log):
    """Whether a builder started a background run its transcript has no end notice for. A builder waiting on its own
    suite writes nothing to its transcript, so its idle minutes grow: on 11-25 the laws and engine lanes were flagged
    idle 20 minutes and re-sent their batch while their test suites ran."""
    # The transcript's last 32 MB, about four hours of the busiest builder's, holds every run still going: a builder's
    # transcript reached 227 MB on 11-26, and reading all of it on every report cost seconds per lane.
    with open(log, "rb") as fh:
        fh.seek(max(0, os.path.getsize(log) - (32 << 20)))
        text = fh.read().decode("utf-8", errors="replace")
    started = set(re.findall(r'backgroundTaskId":"(\w+)"', text))
    ended = set(re.findall(r"task-id>(\w+)</task-id>(?:(?!task-id>).){0,600}?<status>(?:completed|failed|killed|stopped)",
                           text, re.S)) | set(re.findall(r"Successfully stopped task: (\w+)", text))
    # A run whose process died without its notice holds no output file open, so only an open one counts.
    # Each unended task's path, found from its own `/tasks/<id>.output` anchor: a pattern that starts at every `/` of a
    # 55 MB transcript backtracked for 13 s per lane on 11-26, and the report took over 2 minutes.
    outputs = set()
    for task in started - ended:
        found = re.search(rf"/tasks/{task}\.output", text)
        if found:
            outputs.add(re.search(r"[^\s\"'\\]*$", text[max(0, found.start() - 400):found.start()]).group(0) + found.group(0))
    if outputs and subprocess.run(["lsof", "-t", *outputs], capture_output=True, text=True).stdout.strip():
        return True
    # A Monitor writes no output file: it watches until its event, its expiry or its stop.
    for started in re.finditer(r"Monitor started \(task (\w+), expires in (\d+)m", text):
        line = text[text.rfind("\n", 0, started.start()) + 1:text.find("\n", started.end())]
        at = re.search(r'"timestamp":"([^"]+)"', line)
        if (at and f"task-id>{started.group(1)}</task-id>" not in text and f"stopped task: {started.group(1)}" not in text
                and calendar.timegm(time.strptime(at.group(1)[:19], "%Y-%m-%dT%H:%M:%S"))
                + int(started.group(2)) * 60 > time.time()):
            return True
    return False


def mods_off(session):
    """A line when this session's cmod mods are off. Claude Code turns off every hooks-worker mod for the rest of the
    process after its hooks worker freezes three times with no mod to blame, so rulebook's rules and tracer stop
    checking this session and every builder in it: on 11-25 they were off from 00:43 to the restart, and nothing said so.
    cmod answers that no plugin of the session reaches it."""
    if not session:
        return ""
    answer = subprocess.run(["cmod", "ui", "list", "widgets", session], capture_output=True, text=True, timeout=10)
    if "reaches cmod serve" not in answer.stdout + answer.stderr:
        return ""
    # The language owner, 11-25: a session's mods being off is cmod's to hear, never his.
    # The language owner, 11-25 13:5x: the session is never restarted for this, and the cmod session already holds it.
    return (f"FACT: this session's cmod mods are off for the rest of its process, so no rulebook rule or tracer check runs "
            f"on it or its builders. The cmod session owns the fix; never report it to the language owner, and never ask "
            f"him to restart.\n\n")


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
    mounts = container_mounts()
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
        # A lane whose batch landed and waits only for its release is marked `| done:` in state.md, so its idle
        # builder holds no batch to send again: on 11-25 the tracer lane was flagged idle after #817 merged.
        done = "| done:" in line
        running = f"worktrees/{lane.lower()}" in mounts or waits_on_its_run(logs[0])
        rows.append(f"  {lane:<9} {agent} {kind:<17} idle {idle} min" + (", done" if done else ", its run is going" if running else ""))
        if idle >= IDLE and not done and not running:
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


# The speed targets, by the name targets.py prints. The language owner set speed as a standing priority on 11-24,
# triaged like any other problem: each one off its goal is an intake entry headed `## Speed: <name>` (TRIAGE.md).
SPEED_TARGETS = ("edit CPU", "edit peak memory", "CI run, pull request", "CI run, staging",
                 "longest local loop, last 24 h")


def speed_entries(measured):
    """Each speed target that `measured`, targets.py's output, shows off its goal with no intake entry for it."""
    with open(os.path.expanduser("~/.claude/orchestrate/php-sharp/intake.md"), encoding="utf-8") as fh:
        entries = fh.read().split("\n## ")
    speed = [entry for entry in entries if entry.startswith("Speed: ")]
    headings = ["## " + entry.split("\n", 1)[0] for entry in speed]
    # A speed design is checked against the ledger of experiments first, so none is tried twice (TRIAGE.md).
    unsearched = [entry.split("\n", 1)[0] for entry in speed
                  if "- [x] design:" in entry and "experiments.md" not in entry]
    missing = [
        name for name in SPEED_TARGETS
        if re.search(rf"^  {re.escape(name)}: .* x\d", measured, re.M)
        and not any(heading.startswith(f"## Speed: {name}") for heading in headings)
    ]
    lines = []
    if missing:
        lines.append("SPEED, ACT FIRST: " + "; ".join(missing) + " off its goal with no intake entry. Enter each as "
                     "`## Speed: <target>` at a consumer defect's rank (TRIAGE.md).")
    if unsearched:
        lines.append("SPEED, ACT FIRST: " + "; ".join(unsearched) + " checks its design box without searching "
                     "benchmark/experiments.md: name the lines it searched there (TRIAGE.md).")
    return "\n".join(lines) or "SPEED: every speed target off its goal has its intake entry."


def upstream_engine():
    """The engine's php version against upstream's newest patch release of the same minor version.

    The engine takes upstream by merging each patch release's tag, for its security fixes (proposals/engine-8-5-13.md),
    so a newer tag is a problem the tick names until an intake entry merges it."""
    with open("main/php_version.h", encoding="utf-8") as fh:
        ours = re.search(r'#define PHP_VERSION "(\d+)\.(\d+)\.(\d+)"', fh.read())
    minor = f"{ours.group(1)}.{ours.group(2)}"
    listed = subprocess.run(["git", "ls-remote", "--tags", "https://github.com/php/php-src.git", f"php-{minor}.*"],
                            capture_output=True, text=True, timeout=30).stdout
    patches = [int(m.group(1)) for m in re.finditer(rf"refs/tags/php-{re.escape(minor)}\.(\d+)$", listed, re.M)]
    if not patches:
        return f"UPSTREAM ENGINE: no php-{minor} release tag read from php/php-src."
    newest = max(patches)
    if newest <= int(ours.group(3)):
        return f"UPSTREAM ENGINE: the engine is php {minor}.{ours.group(3)}, upstream's newest patch release."
    with open(os.path.expanduser("~/.claude/orchestrate/php-sharp/intake.md"), encoding="utf-8") as fh:
        entered = re.search(rf"^## .*\b{re.escape(minor)}\.{newest}\b", fh.read(), re.M)
    if entered:
        return f"UPSTREAM ENGINE: php {minor}.{newest} is released and in intake; the engine is {minor}.{ours.group(3)}."
    return (f"UPSTREAM ENGINE, ACT FIRST: upstream released php {minor}.{newest}; the engine is {minor}.{ours.group(3)}. "
            f"Enter an intake entry that merges its tag, as #785 merges 8.5.13.")


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
    began = time.time()
    archive_finished()
    # targets.py waits on GitHub and git, and lanes() on the disk, so they run at once.
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        lanes_f = pool.submit(lanes)
        out = targets()
        lane_rows = lanes_f.result()
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
    # A target with no value a day ago shows when it was first measured, never a bare "none".
    first = {m.group(1): m.group(2) for m in
             (re.match(r"\s+(.+?): first measured (\d\d-\d\d \d\d:\d\dZ|now)$", line) for line in lines) if m}
    goals = []
    for line in lines[start + 1:]:
        # A target the owner has set no goal for shows `/ unset`, and sorts after every target with a goal.
        found = re.match(r"\s+(.+?): ([\d.]+)( ?[A-Za-z-]*) / (?:([\d.]+)( ?[A-Za-z-]*) (MET|x([\d.]+))|(unset))(?:, (?:run \d+ )?(.+))?$", line)
        if found:
            name = re.sub(r",? (mean|last green)$", "", found.group(1)).replace("->", " to ")
            unset = found.group(8) is not None
            off = -1.0 if unset else 0.0 if found.group(6) == "MET" else float(found.group(7))
            when = f"  ({found.group(9)})" if found.group(9) else ""
            before, moved = trend.get(found.group(1), ("", ""))
            since = first.get(found.group(1))
            day = f"{float(before):.1f}{found.group(3)}" if before else f"new {since[6:]}" if since else "none"
            mark = "  ROSE" if moved == "ROSE" else ""
            goal = "unset" if unset else found.group(4) + found.group(5)
            verdict = "no goal" if unset else "met" if off == 0 else f"{off:.1f}x off"
            goals.append((off, f"  {name:<36} {found.group(2) + found.group(3):>12}  {day:>12}  {goal:>8}  {verdict:>9}{mark}{when}"))
    block += ["", f"  {'Targets, worst first':<36} {'now':>12}  {'24 h ago':>12}  {'goal':>8}  {'':>9}", *[row for _, row in sorted(goals, reverse=True)]]
    block += [f"  commit to shipped {line.split('by release: ', 1)[1]}" for line in lines if line.strip().startswith("by release: ")]
    block += [f"  {line.strip()}" for line in lines if line.strip().startswith("release ETA: ")]
    block += ["", "Lanes"]
    for row in lane_rows.splitlines()[1:-1]:
        parts = row.split()
        lane = parts[0]
        if "no builder" in row:
            state = "no builder"
        elif row.endswith(", done"):
            state = "done, waits for its release"
        elif row.endswith(", its run is going"):
            state = "working, its run is going"
        elif "idle" in parts:
            minutes = int(parts[parts.index("idle") + 1])
            state = "working" if minutes < IDLE else f"idle {minutes} min"
        else:
            state = " ".join(parts[2:])
        target = re.search(rf"^- {re.escape(lane)}:.*\| target: ([^|\n]+)", open(STATE, encoding="utf-8").read(), re.M)
        off = target and next((m.group(0) for _, r in goals if target.group(1).strip() in r
                               for m in [re.search(r"\d+\.\dx off|met", r)] if m), "not measured")
        block.append(f"  {lane:<8} {state}; moves " + (f"{target.group(1).strip()}, {off}" if target else "no target"))
    # The report times itself against 5 s, so its own cost shows on every reply: it grew to over 2 minutes on 11-26
    # with nothing measuring it.
    took = time.time() - began
    block += ["", f"Report  {took:.1f} s / 5 s" + ("" if took <= 5 else f"  {took / 5:.1f}x off")]
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
        measured = targets()
        context = f"{mods_off(event.get('session_id', ''))}{lanes()}\n\n{measured}\n\n{speed_entries(measured)}\n{upstream_engine()}\n\n{bench}"
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
        # The language owner, 11-25: every tick is told to triage everything, since work another agent sent went into a
        # brief without its own design.
        context = (f"TRIAGE EVERYTHING: every arrival, a builder's report, a review, another agent's request or proposed "
                   f"change and the language owner's feedback, enters intake and gets the maintainer's own design, its "
                   f"precedent and its consistency check before it reaches a brief, an update or a reply.\n\n"
                   f"Run this tick by .claude/skills/maintain/{name}:\n{text}")
    json.dump({"hookSpecificOutput": {"hookEventName": "UserPromptSubmit",
                                      "additionalContext": context}}, sys.stdout)
    return 0


if __name__ == "__main__":
    sys.exit(main())
