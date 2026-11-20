# Writing agent briefs

An agent brief is the comment the maintainer posts on an issue when it moves to `ready-for-agent`. It is the contract a builder works from through `/implement`. The issue's body and discussion are context, and the brief is the contract.

## Principles

### The design is decided before the brief is posted
A builder builds once when the brief holds every design call. A design question found after the build costs a whole round of builds and tests.

### The brief names every file the change touches
The maintainer read the code the change reaches, so the brief lists each file it edits, adds or deletes. That list is the builder's lane, and the maintainer keeps lanes from overlapping. A brief written before the last release is read against the code again before its dispatch, and its files, branch and design are corrected by a `Brief update:` first.

### Durability over precision
The issue may wait days while the code changes. Name types, signatures, commands and messages. Never name line numbers.

### Behavioral acceptance criteria
Each criterion is a command and what it prints, or a program and what it gives, and each one can be checked on its own.

### The spec changes only for a shape the language owner approved
The brief copies the approved program from the rulings record as the spec's examples, and the spec text around them describes those lines. A rule the change seems to need beyond them goes to the language owner as a proposal first.

### Explicit scope boundaries
State what is out of scope, so the builder doesn't widen the change.

### A changed goal gets a whole new brief
A `Brief update:` corrects one part of the brief. When an issue's goal, order or design changes, post a new whole `## Agent Brief` comment, which replaces the old brief and every `Brief update:` before it, so a fresh builder never reconciles a history.

## Template

```markdown
## Agent Brief

**Category:** bug / enhancement
**Summary:** one line on what a PHP# user can do once it lands

**Verified:** (a bug) the repro's command and the output the maintainer saw, on the released version
**Root cause:** (a bug) the function that causes it, and why

**Current behavior:**
What a PHP# program does today, with the repro and the exact message.

**Desired behavior:**
What it does after the change, with its edge cases and error messages.

**Decided design:**
- `TypeOrFunction`: what changes and why
- every new public name, and every consumer the change reaches with its expected effect

**Files it changes:**
- `path/to/file.rs`: what changes in it

**Acceptance criteria:**
- [ ] `<test name>` fails before the fix and passes after it
- [ ] <command> prints <output>
- [ ] the crate's tests, `cargo clippy -- -D warnings` and `cargo fmt --check` pass on the crates it touches
- [ ] a `sharp/CHANGELOG.md` line under `## [Unreleased]` says what works now and quotes what the last release did instead

**Out of scope:**
- what this issue does not change
```

## Example

```markdown
## Agent Brief

**Category:** bug
**Summary:** a class that plain PHP uses through an inherited static method counts as used

**Verified:** `mago compile` on 0.6.23 with the program below prints "Nothing uses `App`, so it never runs."
**Root cause:** the resolver of a written class name records a reference to the class that declares the static member, never to the class written before `::`.

**Current behavior:**
A PHP# class `App` extends a plain PHP class that declares
`public static function make(): static`. One plain PHP file calls `App::make()`.
`mago compile` refuses `App` with "Nothing uses `App`, so it never runs."

**Desired behavior:**
`App` counts as used. A static call, a static property read and a constant read
in plain PHP count as a use of the class written before `::`, whichever class
declares the member.

**Decided design:**
- the resolver of a written class name records a reference to that class, as `new` already does
- unused-class detection, incremental invalidation and law reach read that reference unchanged

**Files it changes:**
- `sharp/mago/crates/analyzer/src/resolver/class_name.rs`: records the reference
- `sharp/mago/crates/analyzer/tests/sharp.rs`: the test
- `sharp/CHANGELOG.md`: the Fixed line

**Acceptance criteria:**
- [ ] `a_class_plain_php_names_is_used_whichever_class_declares_the_member_it_reaches` fails before and passes after
- [ ] the analyzer's tests, clippy and fmt pass
- [ ] a CHANGELOG `### Fixed` line quotes 0.6.23's message

**Out of scope:**
- a bare `use` import with no code after it, which still runs nothing
```
