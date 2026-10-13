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

A class gets each service from the modules of its own namespace, then from each parent namespace's modules in turn. The first module with a binding wins. An environment or test module comes before the base module of its namespace, and in one module a binding `for` the class comes before one for any class. A binding's own parameters resolve from the namespace of the module that holds it.

When nothing binds a parameter's type, a concrete class is built through its constructor each time. An interface or abstract class needs a binding.

The checker walks the graph from each binding and reports, at the binding:

- a constructor parameter that only a binding can build and no module binds;
- a cycle, naming each class on it;
- a `scoped` service a `singleton` holds, which .NET's scope validation calls a captive dependency.

It walks the graph once for the base modules. It walks it again for each environment module and each test module, over the base bindings that module changes, and reports there only what that module changed.

## Compiler

A file whose modules bind services returns their binding table, after any class a test file declares beside its module. A module of laws alone returns nothing:

```php
return [
    // namespace served, service id, class a `for` names or null, lifetime, value
    ['app\shop', 'app\mail\mailer', null, 'singleton', 'App\Mail\SmtpMailer'],
    ['app\shop', 'app\mail\mailer', 'App\Shop\SendReceipt', 'singleton', 'App\Mail\QueueMailer'],
    ['app\shop', 'list<app\pricing\pricerule>', null, 'singleton', ['App\Pricing\TaxRule', 'App\Pricing\DiscountRule']],
    ['app\shop', 'app\cache\redisstore', null, 'singleton',
        static fn (\Sharp\Environment $environment) => new \App\Cache\RedisStore($environment->require('REDIS_URL'))],
];
```

A service's id is its PHP# type in lowercase, as codex's `service_id` writes it for the analyzer, the table and the attribute alike. PHP runs a `List` or a `Map` as an array, so a constructor or binding parameter that holds a collection of objects carries `#[\Sharp\Internal\Service('list<app\pricing\pricerule>')]`. Every other parameter's service is its PHP type.

## Engine

`Sharp\Container` resolves a service the way the checker does:

- Before it builds a class, it reads the modules of the class's folder and of each folder above it, up to the project root that holds `.sharp/`, once per request.
- With `SHARP_ENV` set, it reads the `Module.<SHARP_ENV>.sharp` beside each `Module.sharp` first, so the environment module wins.
- It builds a class through its constructor and calls a binding's closure. Either way, it fills each parameter with the service its `Service` attribute, or else its class, names. It reads the parameters from the function's own argument information, with no reflection.
- It keeps each `singleton` for the request and each `scoped` service for the current scope. A `transient` service and an unbound class are built every time.
- It throws an `Error` that names the service no module binds, the class that needs itself, or the parameter with no class type.

The request is the scope, as each CLI run is. A long-running host runs each unit of work in `Sharp\Container::scope(fn)`.

```php
final class Container
{
    public function get(string $id): mixed { }                  // the service $id names, as PSR-11's get does
    public function has(string $id): bool { }                   // whether a module binds $id or it is a concrete class
    public static function scope(\Closure $run): mixed { }      // runs $run in a new scope and returns its result
}
```

`Environment.require(name)` gives a variable a binding needs, and throws `ValueError` when it is not set.

How a test module is picked at run time, how a host reaches the container, and the PSR-11 adapter wait on rulings.

## Files

```text
sharp/mago/crates/
├── syntax/src/cst/cst/module.rs        Binding, BindingValue, Lifetime, ModuleParent; ModuleMember::Binding
├── syntax/src/parser/internal/module.rs   parses bindings and the parent header
├── names/src/internal/walker.rs        resolves a binding's service, classes and `for`
├── semantics/src/internal/checker/sharp.rs   placement of base, environment and test modules
├── codex/src/metadata/module.rs        ModuleMetadata, BindingMetadata, service_id
├── codex/src/scanner/class_like.rs     registers each module's bindings
├── analyzer/src/statement/module.rs    each binding, and the service graph: missing services, cycles, captive dependencies
├── analyzer/src/statement/return.rs    a binding expression's wrong type names the binding
├── prelude/assets/extensions/sharp.php Environment.require
└── sharp-bridge/src/lower.rs           a module file's binding table, and the Service attribute
ext/sharp/
├── sharp.c                             Sharp\Container, and Environment.require
├── sharp.stub.php                      their public methods
└── sharp_arginfo.h                     generated from sharp.stub.php
Zend/tests/sharp/
├── Container.inc                       new: the app the container tests build
├── container_resolution.phpt           new: each kind of binding, nested namespaces
├── container_environment.phpt          new: SHARP_ENV's module first
├── container_lifetimes.phpt            new: singleton, scoped, scope(), transient
├── container_refusals.phpt             new: missing service, cycle, untyped parameter
├── environment.phpt                    Environment.require
└── project.inc                         a test project's files may sit in folders
docs/
├── spec.md                             scoped-binding and module-errors name their Module.sharp
├── decisions/056-modules.md            unchanged: it matches section 32
└── architecture/modules.md             this note
sharp/baseline/spec                     drops module-law, state-machine-law and every section 32 example
```
