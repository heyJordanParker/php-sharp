# 80. `global.` calls of functions the standard library replaces

Superseded by [decision 83](083-php-through-the-standard-library.md): `global.` does not exist in PHP# code, so every `global.` call below is the error an unknown name gets, and a bare call of a PHP function names no function, with no message that names a member to write.

## Decision

Outside the standard library, a call through `global` of a plain PHP function the standard library replaces is a compile error with its bare call's message. The message names the member that does the function's work, as `global.trim(name)` names `name.trim()`, `global.printf(…)` names `console.write(…)` on the `Console` your constructor takes, and `global.exit(1)` names `process.exit(…)` on the `Process`. `global.die(…)` names `console.writeError(text)`, then `process.exit(1)`.

A read of such a function as a value, as `names.map(global.trim)`, names the same member and says to pass a lambda that makes the call.

A PHP constant the standard library gives is replaced the same way: a read of `PHP_INT_MAX` or `PHP_INT_MIN`, bare or through `global`, is a compile error that names `Int.MAX` or `Int.MIN`.

A function the standard library has no member for keeps its `global.` call, as `global.levenshtein(a, b)` does, and so does a function PHP# syntax replaces, as `global.strval(value)`, since the syntax is no member to call. The standard library writes `global.` for every function, because its members are made of those calls.

This replaces decision 76's rule that `global.` reaches every plain PHP function, and its "The standard library calls PHP the same way, with no exemption". Only the standard library may call a replaced function.

## Options

### Chosen: a replaced function is reached through its member only

```csharp
public class Report
{
    public Report(private Console console) { }

    public string label(string name) => global.trim(name);                       // compile error: write name.trim()
    public void say() { global.printf("Pruned"); }                               // compile error: write console.write(…) on the Console your constructor takes
    public List<string> trimmed(List<string> names) => names.map(global.trim);   // compile error: write x.trim(), and pass a lambda that makes the call
    public List<string> kept(List<string> names) => names.map(name => name.trim());   // compiles
    public int distance(string a, string b) => global.levenshtein(a, b);         // compiles: nothing replaces levenshtein
    public string shown(int value) => global.strval(value);                      // compiles: (string)value is the syntax for it
}
```

Each replaced function has one spelling, the standard library's, so a reader never meets PHP's name for work the library names, and code never chooses between the two. Output, exit, the clock and files go through `Console`, `Process`, `Clock` and `Files`, which a constructor takes, so a class's dependencies show in its constructor.

### Rejected: `global.` reaches every plain PHP function

```csharp
public void say() { global.printf("Pruned"); }                                   // compiles; has Console, with no Console in the constructor
public string label(string name) => global.trim(name);                           // compiles, beside name.trim()
```

Every replaced function has two spellings, and one of them is PHP's, so writing PHP# means knowing PHP's functions. A class prints or exits with no `Console` or `Process` in its constructor, so a test cannot give it a fake one.

## Precedent

- **Chosen:** Kotlin, which reports a Java class Kotlin maps to its own, such as `java.lang.Integer`, with the Kotlin type to write.
- **Rejected:** Rust's `extern "C"` and C#'s P/Invoke, which reach any foreign function, including one the standard library wraps.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)
