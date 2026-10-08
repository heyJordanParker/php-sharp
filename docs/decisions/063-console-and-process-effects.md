# 63. The effects of printing and `exit`

## Decision

Printing has the effect `Console`, and `exit` has the effect `Process`. This replaces "`Php` until the standard library wraps them" (decision 34). `Console` covers standard output and standard error, which includes `printf` and `fwrite(STDOUT|STDERR, …)`. `Files` covers every other file. `Process` covers `exit`. `Console` and `Process` join the standard library's list of standard effects.

## Options

### Chosen: `Console` and `Process`

```csharp
public class Prune
{
    public static void report(int pruned) { printf("Pruned %d carts" + PHP_EOL, pruned); }   // compiles; has Console
    public static void warn() { fwrite(STDERR, "Prune is slow" + PHP_EOL); }                // compiles; has Console, not Files
    public static void fail() { exit(1); }                                                   // compiles; has Process
}

public interface Formatter
{
    string format(string name);                                                              // implementations must be pure
}

public class LoudFormatter : Formatter
{
    public string format(string name) { printf(name); return name; }                         // compile error: format must be pure, and printf has the effect Console
}
```

The effect names what the call does. A method that prints and a method that ends the process show different effects, and neither looks like a call into unknown PHP code.

### Rejected: `Php` until the standard library wraps them

```csharp
public static void report(int pruned) { printf("Pruned %d carts" + PHP_EOL, pruned); }       // compiles; has Php
```

`Php` means "calls PHP code the checker cannot see into". Printing would share that effect with every unclassified built-in function, so a signature could not say that a method only prints.

## Precedent

- **Chosen:** Koka's `console` effect.
- **Rejected:** decision 34's interim rule.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 29, Effects](../spec.md#29-effects)
