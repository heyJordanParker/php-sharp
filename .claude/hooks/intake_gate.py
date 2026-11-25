"""Refuses a brief or a ready-for-agent label for an issue whose intake entry has a box left unchecked."""

import json
import re
import shlex
import subprocess
import sys
from pathlib import Path

INTAKE = Path.home() / ".claude/orchestrate/php-sharp/intake.md"
# Reruns the `## Facts` a design rests on: two landing designs failed on facts nobody ran on 11-23 (GitHub's merge
# queue on a user-owned repository, and next and master holding one tree).
FACTS = INTAKE.parent / "facts.py"
LEDGER = Path.home() / ".claude/orchestrate/php-sharp/sop-reads.json"
# precedent and consistent (11-25, the language owner): every planned change names its system's one precedent from
# precedents.md and the same-kind names, commands, flags or outputs it matches, so no new API acts or reads unlike its
# neighbors; the first outline command took one client's JSON shape for want of both.
REQUIRED = ("issue", "verified", "root cause", "outcome", "design", "precedent", "consistent")
# A contract a builder reads as a history: on 11-24 the tracker held 626 Brief updates on 154 issues, 53 on #359 and 52
# on #516, so the sixth update since an issue's last Agent Brief is refused and a whole new brief replaces them.
UPDATES = 5


def deny(reason: str) -> None:
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }
    }))
    sys.exit(0)


def body_of(command: str) -> str:
    text = command
    try:
        words = shlex.split(command, posix=True)
    except ValueError:
        return text
    for flag, path in zip(words, words[1:]):
        if flag in ("--body-file", "-F") and Path(path).is_file():
            text += "\n" + Path(path).read_text(errors="replace")
    return text


def entry_for(issue: str) -> str | None:
    if not INTAKE.is_file():
        return None
    for section in INTAKE.read_text().split("\n## ")[1:]:
        if re.search(rf"^\s*- \[[ x]\] issue: #{issue}\b", section, re.MULTILINE):
            return section
    return None


def require_read(event: dict, name: str, action: str) -> None:
    """Refuses `action` unless this session read the maintain Skill's `name` since its last compaction (sop_reads.py)."""
    try:
        read = json.loads(LEDGER.read_text()).get(event.get("session_id", ""), [])
    except (OSError, ValueError):
        read = []
    if name not in read:
        deny(f"{action} follows .claude/skills/maintain/{name}, which this session has not read since its last compaction. Read it, then {action[0].lower() + action[1:]}.")


def in_progress(text: str) -> int:
    return len(re.findall(r"^## .*\(in progress\)", text, re.MULTILINE))


def require_one_in_progress(event: dict) -> None:
    """Refuses an intake edit that leaves several entries in progress without lowering their count: on 11-24 three
    were in progress at once, and the language owner's correction that morning was one entry at a time (TRIAGE.md)."""
    tool, given = event.get("tool_name"), event.get("tool_input", {})
    before = INTAKE.read_text() if INTAKE.is_file() else ""
    if tool == "Write":
        after = given.get("content", "")
    elif tool == "Edit":
        count = -1 if given.get("replace_all") else 1
        after = before.replace(given.get("old_string", ""), given.get("new_string", ""), count)
    else:
        return
    if in_progress(after) > 1 and in_progress(after) >= in_progress(before):
        deny(f"This edit leaves {in_progress(after)} intake entries marked (in progress). One entry is worked at a time: "
             "mark the others (waiting on …) or (paused at …) with where each stopped (TRIAGE.md).")
    if proposed_red_checks(after) > proposed_red_checks(before):
        deny("A red check is PHP#'s own build, CI or process, which the maintainer decides: it takes no proposal box. "
             "Only a change to what a PHP# program says or means goes to the language owner (TRIAGE.md).")


def proposed_red_checks(text: str) -> int:
    """Red-check entries that carry a proposal box. On 11-24 the maintainer filed the Mac runner's cost and the lanes'
    split as proposals for the language owner, who owns the specimen, while CI and the process are the maintainer's."""
    entries = re.split(r"^(?=## )", text, flags=re.MULTILINE)
    return sum(1 for entry in entries if "(red check)" in entry.split("\n", 1)[0] and re.search(r"^- \[.\] proposal:", entry, re.MULTILINE))


def main() -> None:
    event = json.load(sys.stdin)
    path = event.get("tool_input", {}).get("file_path", "")
    if path == str(INTAKE):
        require_one_in_progress(event)
        return
    if "/orchestrate/php-sharp/proposals/" in path:
        require_read(event, "PROPOSE.md", "Writing a proposal")
        return
    command = event.get("tool_input", {}).get("command", "")
    if re.search(r"\bgh pr create\b", command) and re.search(r"--head\s+release/|chore: release", command):
        require_read(event, "SHIP.md", "Opening a release pull request")
    for match in re.finditer(r"\bgh issue (create|comment|edit)\b(?:\s+(\d+))?", command):
        invocation = re.split(r"\s(?:&&|\|\||;)\s|\n(?!\s)", command[match.start():], maxsplit=1)[0]
        check(event, match, body_of(invocation))


def require_few_updates(issue: str) -> None:
    """Refuses a Brief update once the issue holds UPDATES of them since its last Agent Brief (AGENT-BRIEF.md: a changed
    goal gets a whole new brief)."""
    out = subprocess.run(["gh", "issue", "view", issue, "--repo", "heyJordanParker/php-sharp", "--json", "comments"],
                         capture_output=True, text=True, timeout=30)
    comments = json.loads(out.stdout or "{}").get("comments", [])
    last = max((at for at, comment in enumerate(comments) if "## Agent Brief" in comment["body"]), default=-1)
    updates = sum("Brief update:" in comment["body"] for comment in comments[last + 1:])
    if updates >= UPDATES:
        deny(f"#{issue} holds {updates} Brief updates since its last Agent Brief. Post a whole new `## Agent Brief` that "
             "states the contract as it is now, instead of another update (AGENT-BRIEF.md).")


def check(event: dict, match: re.Match, text: str) -> None:
    """Refuses one `gh issue` call that posts a brief or a ready-for-agent label before its intake entry is ready."""
    if match.group(1) == "comment" and match.group(2) and "Brief update:" in text and "## Agent Brief" not in text:
        require_few_updates(match.group(2))
    briefs = "## Agent Brief" in text or "## Research brief" in text or "ready-for-agent" in text
    if not briefs:
        return
    require_read(event, "AGENT-BRIEF.md", "Posting a brief")
    if "## Research brief" in text and "## Agent Brief" not in text and "ready-for-agent" not in text:
        if entry_for(match.group(2) or "") is None:
            deny(f"#{match.group(2)} has no entry in ~/.claude/orchestrate/php-sharp/intake.md with its `issue:` box. Write it first (TRIAGE.md).")
        return
    if match.group(1) == "create":
        deny("A new issue is filed needs-triage. Brief it once its intake entry in ~/.claude/orchestrate/php-sharp/intake.md has every box checked (TRIAGE.md).")
    issue = match.group(2)
    entry = entry_for(issue)
    if entry is None:
        deny(f"#{issue} has no entry in ~/.claude/orchestrate/php-sharp/intake.md. Write it first, and brief it once every box is checked (TRIAGE.md).")
    lines = [line.strip() for line in entry.splitlines()]
    checked = {key for key in REQUIRED if any(line.startswith(f"- [x] {key}") for line in lines)}
    if any(line.startswith("- [x] proposal: `proposals/") for line in lines):
        checked.add("design")
    unchecked = [line for line in lines if line.startswith("- [ ]") and line[6:].startswith(REQUIRED)]
    missing = [key for key in REQUIRED if key not in checked]
    if missing or unchecked:
        deny(f"#{issue}'s intake entry is not ready: " + "; ".join(missing + unchecked) + ". Every box is checked with what it asks for before the brief (TRIAGE.md).")
    for design in re.findall(r"- \[x\] (?:design|proposal): `(proposals/[^`]+\.md)`", entry):
        run = subprocess.run([sys.executable, str(FACTS), str(INTAKE.parent / design)], capture_output=True, text=True, timeout=110)
        if run.returncode:
            deny(f"#{issue}'s design rests on a fact that fails today:\n{run.stdout.strip()}")


main()
