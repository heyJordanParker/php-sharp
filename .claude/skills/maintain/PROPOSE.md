# Make an architectural call

Steps 1 to 3 govern every architectural call the maintainer makes: the checker, the engine, the standard library, CI and the process, decided alone, as much as a language decision brought to the language owner. Step 4 is the shape of a proposal to him, which lives in the conversation, never in an issue, until he rules.

## 1. Read the system before deciding a change to it

Read the whole spec section the change touches, every decision it cites, the standard library code and the checker or engine code that implement it, and the tests beside them. Then write down how the system does the job today, end to end. A call made before that reading is neither built nor sent, and an issue filed as `ready-for-human` carries the problem and what was verified, never a proposal not yet researched.

## 2. Hold every rule to the bar

Before adding a rule, check that:
- PHP# has no correct way to do the job already
- the rule opens no worse architecture: no service locator, no dependency cycle, no lookup the compiler can't check, no second way to do one job
- Rust, C# and TypeScript solve the same problem the same way, or the proposal says why PHP# differs. PHP and its frameworks are never precedent
- the precedent is a whole system whose form PHP# mirrors: the same mechanism, in the same place, written the same way, and consistent with every PHP# decision about that system. A function another language happens to have is not precedent, and neither is a language picked because it has one
- the proposal makes every call the change needs, and every fact it rests on is checked in the code. An open question or an unchecked fact in it is work not yet done, so it is not sent
- the rule adds the least code to every program that follows it, because less code is easier to maintain, and the proposal states how many lines it adds
- nothing is implicit: what the program relies on is written in it, and a missing declaration is a compile error that names what to write, never a silent default
- the correct way is obvious from the code an agent already reads, never several paths with a check that picks the right one
- the rule is a plain yes or no: no condition, mode or case that lets an agent choose its way around it, because agents misread nuance and build confusing architectures on it

Precedent languages are part of the reasoning and never appear in repo docs, commits or the CHANGELOG.

PHP is never the reason for a shape, since PHP# owns its engine, and neither is a shape PHP# already has because PHP had it: a proposal that finds one names it as a problem. Compatibility runs one way: PHP# uses PHP libraries as they are, and plain PHP that calls PHP# follows PHP#'s rules. The standard library and reflection take their shapes from Rust and .NET, never from PHP's. PHP#'s own code is written in PHP#, or in Rust or C where speed needs it, never in plain PHP.

## 3. Check earlier rulings before stating a position

A spec line is the language owner's ruling only when the rulings record, `~/.claude/jobs/6affb5f6/tmp/specimen-rulings.md`, or the issue that settled it holds his words. Every commit carries his name as author, so `git log` shows when a line landed, never who decided it. Any other spec line was drafted by an agent and is cited as the spec, never as his ruling, and a quote of him is his whole sentence, verbatim. A ruling stands until a new fact breaks it, and the reply names that fact. A question from the language owner is not a ruling: a ruling is the option the language owner picked. Direction or a correction is not a ruling either: show the whole final shape, wait for the language owner to approve it, and only then brief it. The record of a ruling holds the approved program verbatim. The maintainer records each ruling in the issue it settles, the same turn.

## 4. Propose a syntax change in this shape

Two or three sentences, then one program with every line commented `today → proposed`. The program is the spec examples the change will land, so every rule, case, name and error it adds is one of its lines. Every option shows the same program, the first annotated and the rest as a `diff` against it. Precedent is one real line each from Rust, C# and TypeScript. Offer only options you would ship, and label options only when there are two or more.

The reply is the proposal alone. The research, the options dropped and the reasoning come before it and stay out of it.

A question about a proposal means the proposal is unclear there. The reply is the whole proposal again, with that part fixed, never an answer beside it.
