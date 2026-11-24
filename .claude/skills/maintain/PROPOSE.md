# Make an architectural call

Steps 1 to 3 govern every architectural call the maintainer makes: the checker, the engine, the standard library, CI and the process, decided alone, as much as a language decision brought to the language owner. Step 4 is the shape of a proposal to him, which lives in the conversation, never in an issue, until he rules.

## 1. Read the system before deciding a change to it

Read the whole spec section the change touches, every decision it cites, the standard library code and the checker or engine code that implement it, and the tests beside them. Then write steps 1 to 3 into one research file in the scratchpad before step 4 starts: how the system does the job today, end to end, every ruling it touches quoted from the rulings record, and the one precedent system's whole shape. A call made before that reading is neither built nor sent, and an issue filed as `ready-for-human` carries the problem and what was verified, never a proposal not yet researched.

## 2. Hold every rule to the bar

Before adding a rule, check that:
- PHP# has no correct way to do the job already
- the rule opens no worse architecture: no service locator, no dependency cycle, no lookup the compiler can't check, no second way to do one job
- the syntax reads as C#, TypeScript and Rust write the same thing, since PHP#'s syntax mixes those three, or the proposal says why PHP# differs. PHP and its frameworks are never precedent
- the precedent is a whole system whose form PHP# mirrors: the same mechanism, in the same place, written the same way, and consistent with every PHP# decision about that system. A function another language happens to have is not precedent, and neither is a language picked because it has one
- the proposal makes every call the change needs, and every fact it rests on is checked in the code. An open question or an unchecked fact in it is work not yet done, so it is not sent
- a change to CI or the process makes no target worse: the design counts, from `~/.local/state/php-sharp/runs.jsonl`, the Mac jobs, runs and waits it adds or removes a day, and one that adds Mac minutes is redesigned
- the rule adds the least code to every program that follows it, because less code is easier to maintain, and the proposal states how many lines it adds
- nothing is implicit: what the program relies on is written in it, and a missing declaration is a compile error that names what to write, never a silent default
- a construct works with every other feature of the language by default: its guarantee lives in its type, never in rules about the places that hold it, and each feature it can't join is a defect to fix
- the correct way is obvious from the code an agent already reads, never several paths with a check that picks the right one
- the rule is a plain yes or no: no condition, mode or case that lets an agent choose its way around it, because agents misread nuance and build confusing architectures on it
- the program it needs is PHP# from end to end: a PHP# project holds no plain PHP file, as a C# project holds no F# file, so a design that needs one is redesigned

Precedent languages are part of the reasoning and never appear in repo docs, commits or the CHANGELOG.

PHP is never the reason for a shape, since PHP# owns its engine, and neither is a shape PHP# already has because PHP had it: a proposal that finds one names it as a problem. Compatibility runs one way: PHP# uses PHP libraries as they are, and plain PHP that calls PHP# follows PHP#'s rules. The standard library and reflection take their shapes from Rust and .NET, never from PHP's. PHP#'s own code is written in PHP#, or in Rust or C where speed needs it, never in plain PHP.

## 3. Check earlier rulings before stating a position

A spec line is the language owner's ruling only when the rulings record, `~/.claude/jobs/6affb5f6/tmp/specimen-rulings.md`, or the issue that settled it holds his words. Every commit carries his name as author, so `git log` shows when a line landed, never who decided it. Any other spec line was drafted by an agent and is cited as the spec, never as his ruling, and a quote of him is his whole sentence, verbatim. A decision record in `docs/decisions/` is the maintainer's writing, its options and reasons included, even where it opens with his name: only his quoted words in it are his. Every decision is weighed on its reasons, and a better design changes it, so the reply argues from those reasons, never from who decided. A question from the language owner is not a ruling: a ruling is the option the language owner picked. Direction or a correction is not a ruling either: show the whole final shape, wait for the language owner to approve it, and only then brief it. The record of a ruling holds the approved program verbatim. The maintainer records each ruling in the issue it settles, the same turn.

## 4. Propose a syntax change in this shape

Two or three sentences, then one program drawn with `/show-me` as a diff against today's code. The sentences state the problem a program has today, then the change, in plain words: they use no name the program below has not yet shown, and the language owner remembers no earlier reply. Its classes declare each property on its own line and set it in the constructor's body, since a constructor property is the hardest form to read. Every behavior it shows carries its precedent: a solution another language or a widely used library ships, never PHP#'s own code, whose proof is that someone else validated it. The program is the spec examples the change will land, so every rule, case, name and error it adds is one of its lines. The rules and the spec prose are the maintainer's to write from the approved program, so they stay out of the proposal, and a rule never limits a design it would make better. A new type's program shows every way a program builds it, uses it, stores it, prints it and reads it back, all modeled on one precedent system, with each name congruent with the standard library's names for the same job. Compile today's program with the released `mago` before sending it. Then attack the proposal as an agent that wants the wrong program to compile: write each incorrect program the change newly lets compile, and each correct one it newly refuses. A change that lets an incorrect program compile is redesigned before it is sent, so the language owner reviews a proposal the maintainer already failed to break. The precedent is one modern, established third-party system that already solves the whole problem, named with how it works. Below the program, one real line each from C#, TypeScript and Rust shows how each writes the same thing with its usual libraries. Those lines keep PHP#'s syntax congruent with the three languages it mixes, and they are never the precedent. A proposal is one design. Options appear only when two designs are both good and the choice between them is taste, and each shows the same program as its own diff against today's code. A design the maintainer rejected stays in the research file, never in the reply. The reply names no spec section, issue, decision or file as context: it says in plain words what the code shows.

Before writing a line of the proposal, take one modern precedent system's architecture and shape for the whole feature, never PHP, adding a second system only for a capability the first lacks. Then integrate it into PHP#: remove what duplicates a PHP# feature, and make it congruent with the language. A thing PHP# already has a pattern for keeps that pattern, since consistency beats matching the precedent one to one, and a thing PHP# has not named yet takes the name engineers and agents already use for it everywhere, which beats a precedent's own term. Each behavior that departs from that system, or that the language owner questions, shows the options compared from first principles: what each one does to a real program, and which failure each one causes. After the language owner corrects one part, change only that part, and keep every name and behavior he didn't comment on. A gap the same check finds elsewhere is named in one line for him, never changed silently.

The reply is the proposal alone. The research, the options dropped and the reasoning come before it and stay out of it.

Every fact about PHP# in a reply about architecture, a discussion or an answer included, is read from the code or the spec that turn. A precedent is a system every agent already knows, cited for what it plainly does, by the mechanism the proposal uses: one that needs a lookup to explain is not precedent.
Never: a PHP# fact from memory, or new PHP# syntax anywhere but a proposal that met steps 1 to 4.

A question about a proposal gets its answer, then the whole proposal again, unchanged. A question is not a correction.

Write every proposal and every answer about one with `/present`, and draw its code and architecture with `/show-me`. Each one stands alone: it never compares itself to an earlier reply or inbox item, and it calls each actor what the reader sees, as "this Claude session", "CI" or "you", never a role from this file such as "the maintainer".
