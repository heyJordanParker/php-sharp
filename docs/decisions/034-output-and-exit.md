# 34. Output and exit

## Decision

Output and exit are function calls only. `echo` and `print` are removed, with a compile error that names `printf` or `fwrite`. `die` is removed, with a compile error that says to write the message to STDERR, then `exit(1)`, because `die("…")` exits with status 0. `exit(code)` stays as PHP 8.4's built-in function. It skips `finally` blocks, as in Java and C#, and its effect is tracked.

## Options

### Chosen: function calls only

```csharp
fwrite(STDERR, "Prune failed" + PHP_EOL);    // compiles
exit(1);                                     // compiles; runs: ends the process with status 1, skipping every finally block
printf("Pruned %d carts" + PHP_EOL, pruned); // compiles
echo "Pruned";                               // compile error: write printf or fwrite
die("Prune failed");                         // compile error: write the message to STDERR, then exit(1)
```

Every way to write output or end the process is an ordinary call, so the effect system sees it, and no form reports success while it prints a failure.

### Rejected: PHP's forms as they are

```csharp
echo "Pruned";                               // not PHP#: a statement, not a call
die("Prune failed");                         // not PHP#: prints the message, then exits with status 0
```

`die("…")` reports success to the shell and to CI while it prints a failure, and `echo` and `print` are statements, which the effect system would need its own rule for.

## Precedent

- **Chosen:** C#, Kotlin, Swift, Go and Rust, which print and exit through library functions.
- **Rejected:** PHP's `echo`, `print` and `die`.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 29, Effects](../spec.md#29-effects)
