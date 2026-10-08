# 29. Typed compilation

## Decision

A `.sharp` file runs only after the checker accepts it, and a type error stops it from running, as in C# and Java. The checker's types reach the running program, inferred ones too. A generic method can test a type parameter its call inferred, `typeof(TItem)` works on every type parameter, and a loop over a `Map` keyed by a backed enum gives its keys back as the enum with no type written. This reverses the earlier model, where each file compiled alone from its own text. `vendor/bin/mago compile` compiles each accepted `.sharp` file into a `.sharpc` file in one `.sharp/` folder at the project root, and the engine runs only current compiled files. A type error, a broken structure rule, or a law left unproven (decision 57) stops a file from running. Pragmas, `ignore` and the baseline hide warnings, never an error.

## Options

### Chosen: the checker's types reach the running program

```csharp
public class Inbox
{
    public bool holds<TItem>(List<TItem> items, Any value) => value is TItem;   // compiles
}

inbox.holds(orders, message);                   // compiles; runs: TItem is Order, inferred from orders
for (const [status, n] of counts) { … }         // compiles; runs: status is a Status
const byStatus = orders.groupBy(o => o.status);
for (const [status, group] of byStatus) { … }   // compiles; runs: an inferred Map's keys are Status too
```

Code writes a type only when the checker cannot work it out. The cost is in how code runs. The checker must accept a file before it runs, in development and in every deployment, and a missing or stale result stops the program. Editing one file can change how the files that inferred its types run.

```text
project/
├── app/Orders/Order.sharp
└── .sharp/
    └── app/Orders/Order.sharpc     <- what the engine runs; a missing, stale or mismatched one is refused, naming the fix
```

The checker compiles, and the engine runs its output, as C#'s compiler and runtime split the work. `mago compile` mirrors the source paths into one folder, vendor packages included, so a deploy copies one folder and nothing is written into `vendor/`. In development, `sharp.compile_command` compiles a stale file on demand. A broken structure rule stops a file as a type error does. A rule that reads the whole project can lag in development until the next full compile, and is exact at deploy.

### Rejected: each file compiles alone (Hack's model)

```csharp
inbox.holds(orders, message);                        // compile error: holds uses TItem as a class, so write it: holds<Order>(…)
inbox.holds<Order>(orders, message);                 // compiles
for (const [Status status, int n] of counts) { … }   // the key's type is written so the engine can rebuild it
```

The engine sees one file's text, so a type the checker inferred never reaches it. A call must write every type argument the body uses as a class, and the signature does not show which calls must.

### Rejected: pass a `Class<T>` argument (Java's model)

```csharp
public bool holds<TItem>(Class<TItem> type, List<TItem> items, Any value) => value is type;   // not PHP#: the class travels as an argument
inbox.holds(typeof(Order), orders, message);
```

The caller writes `typeof(Order)` only so the engine can run, and the signature names the class twice.

### Rejected: the engine receives the types beside the source

```text
.sharp/app/Orders/Order.types       <- not PHP#: the checker's types, which the engine reads beside Order.sharp and compiles itself
```

The checker's types would be keyed by positions in the checker's own parse, so the engine's parse would have to match the project's checker byte for byte. When the checker's version and the engine's drift apart, types attach to the wrong code with no error. Lowering would also split between the checker and the engine, and the engine would keep linking Rust and Mago. A compiled file is one input, and the engine only checks that it is current.

## Precedent

- **Chosen:** C#, whose runtime builds a specialized generic type for each type argument, and Swift, which passes type metadata. Both compile before the code runs, as Java does.
- **Chosen, how code runs:** C#'s split between the compiler and the runtime, TypeScript's `outDir`, Python's `PYTHONPYCACHEPREFIX`, and Roslyn analyzers at severity `error`, which stop a build as a type error does.
- **Rejected, each file compiles alone:** Hack's `reify`, whose type arguments must be written at every call.
- **Rejected, a `Class<T>` argument:** Java's `Class<T>`, and Kotlin's `KClass<T>` outside `reified` functions.
- **Earlier decisions:** this extends decision 7 to inferred type arguments, supersedes decision 26's rejection of "the checker's types decide what the engine emits", and changes decision 27's loop rule.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 17, Loops](../spec.md#17-loops)
- [Section 25, Referring to classes](../spec.md#25-referring-to-classes)
- [Section 27, PHP# files](../spec.md#27-php-files)
- [Section 28, Verification](../spec.md#28-verification)
