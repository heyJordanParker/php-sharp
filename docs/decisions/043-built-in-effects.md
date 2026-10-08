# 43. The effects of PHP's built-in functions

## Decision

A PHP built-in function the standard library has not classified has one catch-all effect, `Php`, which means "calls PHP code the checker cannot see into". It replaces the earlier rule that every other built-in function is pure. When the standard library wraps a function, its real effect replaces `Php`, as `Environment` does for `getenv()`, and other families get theirs as each is wrapped. Pure code cannot call a function with `Php`. Printing has the effect `Console`, and `exit` has the effect `Process` (decision 63).

`Php` covers PHP's built-in functions only. A call into a plain PHP library with no `extern` keeps its unknown effect, which no `uses` accepts (decision 16).

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
    public void remember(string token) { setcookie("t", token); }   // compiles; has Php: setcookie is not classified yet
}

public class CookieFormatter : Formatter
{
    public string format(string name)
    {
        setcookie("seen", name);                                    // compile error: format must be pure, and setcookie has the effect Php
        return name;
    }
}
```

A function nobody has classified never passes as pure. A missing classification shows up as a compile error, and each family the standard library wraps makes more code pure.

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
