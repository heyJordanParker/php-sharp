# 77. Statements that never run

Superseded in part by [decision 83](083-php-through-the-standard-library.md): `global.` does not exist in PHP# code, so the call that ends a block by ending the process is `process.exit(…)` on `Process`, a call that never returns, in place of `global.exit(…)` below.

Superseded in part by [decision 80](080-global-calls-of-replaced-functions.md): outside the standard library, `global.exit(…)` below is a compile error that names `process.exit(…)`, so the form that ends a block there is a call that never returns.

## Decision

A statement that can never run is a compile error. That covers every form that ends a block: a statement after `return`, `throw`, `global.exit(…)`, `break` or `continue` in the same block, one after an `if` whose every branch ends the block, one after a loop that never ends, such as a `do` whose body ends the block with no `break` or `continue`, and a block after a condition that never finishes.

The checker refuses the first statement of the block that never runs, once per block, as Java does. The error names the line of the statement that ends the block, and says to delete the statement or move it above that line. A condition of an `if` or a `for … of` that never finishes is refused at the condition, which the error says to write as its own statement in place of the `if` or the `for`. A `.php` file keeps PHP's rule, where such code compiles.

## Options

### Chosen: a statement that never runs is a compile error

```csharp
public int total(List<int> prices)
{
    int sum = 0;
    for (const price of prices) {
        if (price < 0) {
            continue;
            sum -= price;                     // compile error: continue ends the block, so this never runs
        }
        sum += price;
    }
    return sum;
    sum = 0;                                  // compile error: return ends the block, so this never runs
}
```

The checker's analysis stops reading a block where the block ends, so it never checks a statement after that point. Refusing the statement means the compiler emits only code the checker read. A statement that never runs is also a mistake the reader should see: it was meant to run earlier, or it should go.

### Rejected: dead code compiles and is still checked

```csharp
public int total(int sum)
{
    return sum;
    sum = 0;                                  // compiles: checked, then never runs
}
```

The checker would have to read every statement after the block ends, as if it could run, and the compiler would emit code that never runs. The line stays in the file, and the reader has to work out that it never runs.

## Precedent

- **Chosen:** Java, whose "unreachable statement" is a compile error for a statement after `return`, `throw`, `break` or `continue`, after an `if`/`else` whose branches all end, and after `while (true)` without a `break`, reported once for the first such statement. Zig, whose "unreachable code" is a compile error.
- **Rejected:** C#, whose CS0162 "Unreachable code detected" is a warning, and Rust, whose `unreachable_code` lint is a warning. Both compile and check the code.

## Spec

- [Section 7, Methods](../spec.md#7-methods)
