# 38. Superglobals and the environment

## Decision

PHP# has no superglobals. `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_REQUEST`, `$_SESSION`, `$_ENV` and `$GLOBALS` are compile errors that read "PHP# has no superglobals; take a Request". Request data arrives as an object, such as a framework's `Request`. The standard library adds `Environment`, a `foreign` class with a standard effect of the same name, which has `variable(name)`, `arguments` and `currentDirectory`. `getenv()` and PHP's other environment built-ins carry the `Environment` effect.

## Options

### Chosen: no superglobals, and a standard `Environment`

```csharp
public class Deploy
{
    public Deploy(private Environment environment) { }                                   // compiles; Deploy has the Environment effect
    public string region() => this.environment.variable("AWS_REGION") ?? "us-east-1";   // compiles
    public string target() => this.environment.arguments[1];                           // compiles
}

public string origin() => _SERVER["HTTP_HOST"];                                        // compile error: PHP# has no superglobals; take a Request
```

Every input a class reads arrives through its constructor or its parameters, so its signature shows that it reads the environment, and a test passes a fake `Environment`.

### Rejected: no superglobals, and nothing added

```csharp
public string region() => getenv("AWS_REGION") as string ?? "us-east-1";   // compiles; no signature shows the environment read
public string target() => …;                                               // no typed way to read the script's arguments
```

A script has no typed way to read its arguments, and environment reads stay invisible in signatures.

## Precedent

- **Chosen:** C#'s `Environment`, Rust's `std::env`, and Go's `os.Getenv` and `os.Args`.
- **Rejected:** ASP.NET Core's `HttpContext` and Go's `http.Request` alone, which carry the request with no environment object beside them.

## Spec

- [Section 2, Variables](../spec.md#2-variables)
- [Section 29, Effects](../spec.md#29-effects)
