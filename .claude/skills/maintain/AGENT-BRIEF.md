# Writing agent briefs

An agent brief is the comment the maintainer posts on an issue when it moves to `ready-for-agent`. It is the contract a builder works from through `/implement`. The issue's body and discussion are context, and the brief is the contract.

## Principles

### The design is decided before the brief is posted
A builder builds once when the brief holds every design call. A design question found after the build costs a whole round of builds and tests.

### Durability over precision
The issue may wait days while the code changes. Name types, signatures, commands and messages. Never name line numbers.

### Behavioral acceptance criteria
Each criterion is a command and what it prints, or a program and what it gives, and each one can be checked on its own.

### Explicit scope boundaries
State what is out of scope, so the builder doesn't widen the change.

## Template

```markdown
## Agent Brief

**Category:** bug / enhancement
**Summary:** one line on what a PHP# user can do once it lands

**Current behavior:**
What a PHP# program does today, with the repro and the exact message.

**Desired behavior:**
What it does after the change, with its edge cases and error messages.

**Decided design:**
- `TypeOrFunction`: what changes and why
- every new public name, and every consumer the change reaches with its expected effect

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

**Acceptance criteria:**
- [ ] `a_class_plain_php_names_is_used_whichever_class_declares_the_member_it_reaches` fails before and passes after
- [ ] the analyzer's tests, clippy and fmt pass
- [ ] a CHANGELOG `### Fixed` line quotes 0.6.23's message

**Out of scope:**
- a bare `use` import with no code after it, which still runs nothing
```
