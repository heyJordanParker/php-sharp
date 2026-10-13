# Modules

A module tells the container how to build the app's objects. The checker proves at compile time that every service the container builds has a binding, takes no service from a shorter lifetime, and needs no cycle. The engine resolves the same bindings at run time from tables the compiler writes, with no reflection scanning.

Precedents: .NET's `IServiceCollection` and `IServiceProvider` for lifetimes, scopes and resolution, Dagger and Hilt for the compile-time check, and NestJS for a module per folder.

## Checker

### Syntax

A module is `module`, an optional parent header, and its members in braces. A member is a law or a binding:

```csharp
module : App.Shop                                              // parent header: an environment or test module
{
    singleton Mailer = SmtpMailer;                             // lifetime, service, value: a class
    singleton Mailer for SendReceipt = QueueMailer;            // `for` names the one class this binding serves
    singleton List<PriceRule> = [TaxRule, DiscountRule];       // a list of classes, for a List service
    singleton RedisStore(Environment environment) = new RedisStore(environment.require("REDIS_URL"));   // parameters and one expression
    law same(int a) => a == a;                                 // a law, as before
}
```

- **The value is a class when it is a bare name, or a list of bare names, that names no parameter of the binding.** Anything else is one expression. A bare class name is not an expression in PHP#, so the two never overlap.
- **Any other member is an error at the member,** so a module holds no statements, loops or methods.

### Placement

- `Module.sharp` holds its folder's module, with no parent header.
- `Module.<Environment>.sharp` holds an environment module. Its header names its own folder's namespace.
- Any other file may hold a test module beside its classes. Its header names the module it overrides.

### Each binding

The analyzer checks each binding where it is written:

- the value is a class assignable to the service, or a list of classes assignable to the list's element type, or an expression whose type is assignable to the service;
- a class value is concrete, and built through its main constructor;
- each binding parameter is a service, resolved the same way as a constructor parameter;
- `for` names a class;
- one module binds a service and `for` pair once.

### The service graph

A class gets each service from the module of its own namespace, then from each parent namespace's module in turn. The nearest binding wins. A binding's own parameters resolve from the module that holds it. A binding with `for` serves only the class it names.

When nothing binds a parameter's type, a concrete PHP# class is built through its main constructor, and the standard library's foreign classes, such as `Environment`, come from the host.

The checker walks the graph from its starting classes and reports, at the parameter that needs it:

- a missing service;
- a cycle;
- a `scoped` or `transient` service held by a `singleton`, which .NET's scope validation calls a captive dependency.

It walks the graph once for the base modules, and once more for each environment module and each test module, each over the bindings it overrides.

The starting classes wait on a ruling. The proposal is every class a binding names, every binding parameter, and every `on` listener.

## Compiler

Each module file compiles to a `.sharpc` that returns its binding table. No class is declared for it, since the container finds a module by its folder:

```php
return [
    ['App\Mail\Mailer', null, Sharp\Container::SINGLETON, 'App\Mail\SmtpMailer'],
    ['App\Mail\Mailer', 'App\Shop\SendReceipt', Sharp\Container::SINGLETON, 'App\Mail\QueueMailer'],
    ['List<App\Pricing\PriceRule>', null, Sharp\Container::SINGLETON, ['App\Pricing\TaxRule', 'App\Pricing\DiscountRule']],
    ['App\Cache\RedisStore', null, Sharp\Container::SINGLETON,
        static fn (\Sharp\Environment $environment) => new \App\Cache\RedisStore($environment->require('REDIS_URL'))],
];
```

A service's id is its full PHP# type. PHP runs a `List` as an array, so a constructor or binding parameter whose PHP type drops its type arguments carries `#[\Sharp\Internal\Service('List<App\Pricing\PriceRule>')]`.

## Engine

`Sharp\Container` resolves a service the way the checker does:

- It walks the requesting class's folder up to the project root and includes each `Module.sharp` it finds, once per process.
- With `SHARP_ENV` set, it also includes the `Module.<SHARP_ENV>.sharp` beside each one, whose bindings win.
- It builds a class through its constructor and calls a binding's closure. Either way, it fills each parameter with the service its type, or its `Service` attribute, names.
- It keeps each `singleton` for the process and each `scoped` service for the current scope. A `transient` service is built every time.

The engine starts one scope for each request and for each CLI run. A long-running host runs each unit of work in `Sharp\Container::scope(fn)`.

```php
final class Container
{
    public function get(string $id): object { }                 // the service $id names, as PSR-11's get does
    public function has(string $id): bool { }                   // whether the container can build $id
    public static function scope(\Closure $run): mixed { }      // runs $run in a scope of its own
}
```

The standard library's `Sharp\Psr\Container` implements `Psr\Container\ContainerInterface` over it, so any framework's container can ask for a PHP# class.

## Files

```text
sharp/mago/crates/
├── syntax/src/cst/cst/module.rs        Binding, BindingValue, Lifetime, ModuleParent; ModuleMember::Binding
├── syntax/src/parser/internal/module.rs   parses bindings and the parent header
├── names/src/internal/walker.rs        resolves a binding's service, classes and `for`
├── semantics/src/internal/checker/sharp.rs   placement of base, environment and test modules
├── codex/src/metadata/binding.rs       new: BindingMetadata
├── codex/src/scanner/class_like.rs     registers each module's bindings
├── analyzer/src/statement/module.rs    new: checks each binding
├── analyzer/src/services.rs            new: the service graph: missing services, cycles, captive dependencies
└── sharp-bridge/src/lower.rs           lowers a module file to its binding table
ext/sharp/
├── sharp.c                             Sharp\Container: tables, lifetimes, scopes, SHARP_ENV
└── sharp.stub.php                      Sharp\Container's public methods
sharp/composer/
└── src/Psr/Container.php               new: Sharp\Psr\Container, the PSR-11 adapter
docs/
├── spec.md                             untouched: section 32 is final
├── decisions/056-modules.md            matches the spec: binding parameters, no Factory<T>, SHARP_ENV
└── architecture/modules.md             this note
sharp/baseline/spec                     drops module-law and every section 32 example
```
