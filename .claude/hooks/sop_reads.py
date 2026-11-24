"""Records which files of the maintain Skill this session has read since its last compaction.

A Read of one of them is recorded under the session's id; a compaction clears the session's record, since what was read
before it is gone from the context. intake_gate.py refuses an action whose SOP file the record lacks."""

import json
import sys
from pathlib import Path

LEDGER = Path.home() / ".claude/orchestrate/php-sharp/sop-reads.json"
SOP = Path(".claude/skills/maintain")


def load() -> dict:
    try:
        return json.loads(LEDGER.read_text())
    except (OSError, ValueError):
        return {}


def main() -> None:
    event = json.load(sys.stdin)
    session = event.get("session_id", "")
    ledger = load()
    if event.get("hook_event_name") == "SessionStart":
        if event.get("source") == "compact":
            ledger.pop(session, None)
            LEDGER.write_text(json.dumps(ledger))
        return
    tool_input = event.get("tool_input", {})
    # A Read names one file; `trace read`, which the tracer hook routes reads through, names one or more.
    if "file_path" in tool_input:
        paths = [tool_input["file_path"]]
    elif str(tool_input.get("command", "")).lstrip().startswith("trace read "):
        paths = tool_input["command"].split()[2:]
    else:
        return
    names = {Path(p).name for p in paths if Path(p).parent.as_posix().endswith(SOP.as_posix()) and p.endswith(".md")}
    if names:
        ledger[session] = sorted(set(ledger.get(session, [])) | names)
        LEDGER.write_text(json.dumps(ledger))


main()
