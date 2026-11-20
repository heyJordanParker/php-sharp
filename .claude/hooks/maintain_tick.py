#!/usr/bin/env python3
import json
import os
import sys

TICK = "Maintainer tick"


def main():
    try:
        event = json.load(sys.stdin)
    except ValueError:
        return 0
    if event.get("prompt", "").strip() != TICK:
        return 0
    project = os.environ.get("CLAUDE_PROJECT_DIR") or event.get("cwd", "")
    path = os.path.join(project, ".claude", "skills", "maintain", "SKILL.md")
    try:
        with open(path, encoding="utf-8") as fh:
            text = fh.read()
    except OSError as error:
        text = f"The SOP at {path} could not be read: {error}. Read it before anything else."
    if text.startswith("---\n"):
        text = text.split("\n---\n", 1)[-1]
    context = f"Run this tick by the SOP below, read from {path} as it stands now.\n\n{text}"
    json.dump({"hookSpecificOutput": {"hookEventName": "UserPromptSubmit",
                                      "additionalContext": context}}, sys.stdout)
    return 0


if __name__ == "__main__":
    sys.exit(main())
