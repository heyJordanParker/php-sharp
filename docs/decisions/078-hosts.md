# 78. Hosts

## Decision

Each lifetime means the same thing under every host: php-fpm, the CLI, FrankenPHP and Laravel Octane.

- **The app is what the host keeps in memory.** Under php-fpm and the CLI the app runs for one request or one command. Under a long-running host, such as Octane or FrankenPHP's worker mode, one app serves request after request. A host that runs several workers runs one app in each.
- **A static and a `singleton` live as long as the app runs.** A static's initial value runs once, when the app first uses its class's statics, so under a long-running host each request sees what the last one left.
- **A `scoped` service lives for one scope, and the host starts and ends each scope with the standard library's `Scope.run(body)`.** `Scope.run` returns what `body` returns and has `body`'s effects, through `uses body`. PHP's own request is the default scope. A long-running host wraps each unit of work in it: an Octane request, a FrankenPHP worker request, a queued job. PHP# ships no framework code for it.
- **A scope follows the fiber that runs in it.** A fiber started inside `Scope.run` belongs to that scope, and a `Scope.run` inside a fiber is that fiber's own. A `scoped` service resolved after its scope ended throws `Sharp\ContainerError`.
- **An app holds at most 65,536 run-time types.** The types an app makes as it runs, from `unserialize` and from substitutions such as `Node<List<T>>`, live as long as the app, and the program bounds them. Past the limit, a new type text from input fails as other bad input does, and every type the app holds keeps working.

```csharp
public class Worker
{
    public Worker(private JobQueue queue) { }

    public void work()
    {
        for (const job of this.queue.pending())
        {
            Scope.run(() => job.handle());     // each job gets its own scoped services
        }
    }
}
```

## Options

### Chosen: one call starts a scope, and the scope follows the fiber

A host calls `Scope.run`, and nothing else changes for it. A fiber that suspends inside a scope keeps building from that scope when it resumes, and code on another fiber builds from its own.

### Rejected: a framework adapter per host

Decision 56 named a `php-sharp/laravel` adapter that would start and end Laravel's scopes. Each framework would need one, and PHP# would follow each framework's releases. One standard-library call serves every host.

### Rejected: a scope that ends in call order

A scope kept in one global, restored when its body returns, makes a fiber that suspends inside it hand its scope to the main code, and a later call reads the scope after it was freed.

### Rejected: freeing each run-time type when nothing uses it

A type pointer reaches objects, frames, closures, run-time caches and class values, and none of them counts it. A refcount, or a sweep at each scope's end, would free types a real app reuses on its next request, at the cost of hundreds of lines where each missed holder is a crash.

## Precedent

- **Lifetimes:** ASP.NET Core's host defines `Singleton`, `Scoped` and `Transient`, and `IServiceScopeFactory.CreateScope()` starts a scope.
- **A scope that follows the work it starts:** .NET's `AsyncLocal`, Node's `AsyncLocalStorage.run` and tokio's task-local `scope`. .NET throws `ObjectDisposedException` when code uses a disposed scope.
- **Statics under a long-running host:** C#, TypeScript and Rust keep a static for the process's life.
- **Run-time types:** C# keeps each generic instantiation it makes at run time, as `MakeGenericType` makes one, for the process's life.

## Spec

- [Section 6, Fields and properties](../spec.md#6-fields-and-properties)
- [Section 32, Modules](../spec.md#32-modules)
