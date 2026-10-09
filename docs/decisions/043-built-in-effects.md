# 43. The effects of PHP's built-in functions

## Decision

PHP#'s Composer package ships an `extern` declaration for every PHP built-in function, with its real effect or as pure. There is no catch-all effect. This replaces the earlier rule that every other built-in function is pure. Printing has the effect `Console`, and `exit` has the effect `Process` (decision 63).

A call into a plain PHP library with no `extern` has an unknown effect, which no `uses` accepts (decision 16). A built-in function follows the same rule, so a missing declaration is a compile error that names it.

## Options

### Chosen: strict by default

```csharp
public interface Formatter
{
    string format(string name);                                     // implementations must be pure
}

public class Visitor
{
    public string greet(string name) => "Hello, " + name.trim();    // compiles; pure: trim is a standard-library method
    public void remember(string token) { file_put_contents("seen.txt", token); }   // compiles; has Files
}

public class FileFormatter : Formatter
{
    public string format(string name)
    {
        file_put_contents("seen.txt", name);                        // compile error: format must be pure, and file_put_contents has the effect Files
        return name;
    }
}
```

A built-in function never passes as pure unless its declaration says so. A missing declaration shows up as a compile error, not as a silent pass.

### Rejected: pure by default, with a list of impure functions

```csharp
public class RegionFormatter : Formatter
{
    public string format(string name) => name + " in " + getenv("AWS_REGION");   // not PHP#: passes as pure, because getenv is missing from Psalm's list
}
```

A function missing from the list passes as pure, and nothing reports it. Laws (section 28) would then reason about code that reads the environment. Psalm's list misses `getenv`, so Psalm reads `getenv("AWS_REGION")` as pure.

## Precedent

- **Chosen:** Haskell's foreign functions, which run in `IO` unless declared pure, and Rust's foreign functions, which are `unsafe` to call.
- **Rejected:** Psalm's list of impure built-in functions, which reads every function missing from it as pure.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 29, Effects](../spec.md#29-effects)
