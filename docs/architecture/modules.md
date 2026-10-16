# Modules

A module tells the container how to build the app's objects. PHP# creates the classes a module binds and the classes that declare an `on` method, and nothing else. The checker proves at compile time that each of them can be built: every service it takes has a binding, no service takes one of a shorter lifetime, and no service needs itself. The engine resolves the same bindings at run time from tables the compiler writes, with no reflection scanning.

Precedents: .NET's `IServiceCollection` and `IServiceProvider` for lifetimes, scopes and resolution, Dagger and Hilt for the compile-time check, NestJS for a module per folder, and Guice's `requireExplicitBindings()` for creating only bound classes.

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
    transient Checkout = Checkout;                             // a class bound to itself, so PHP# creates it
    law same(int a) => a == a;                                 // a law, as before
}
```

- **The value is a class when it is a bare name, or a list of bare names, that names no parameter of the binding.** Anything else is one expression. A bare class name is not an expression in PHP#, so the two never overlap.
- **Any other member is an error at the member,** so a module holds no statements, loops or methods.

### Placement

- `Module.sharp` holds its folder's module, with no parent header.
- `Module.<Environment>.sharp` holds an environment module. Its header names its own folder's namespace.
- Any other file may hold a test module beside its classes. Its header names the module it overrides.
- The standard library's `Sharp/Module.sharp` serves the root namespace, so its bindings reach every class after the class's own namespaces. It binds `Environment` and `Dispatcher`.

### Each binding

The analyzer checks each binding where it is written:

- the value is a class assignable to the service, or a list of classes assignable to the list's element type, or an expression whose type is assignable to the service;
- a class value is concrete, and each parameter of its main constructor needs a binding, resolved from the class's own namespace;
- each binding parameter needs a binding, resolved from the namespace of the module that holds it;
- a parameter whose type no binding can give, such as a `string`, is refused with the expression binding to write instead;
- `for` names a class;
- one module binds a service and `for` pair once.

A missing service of a bound class is reported at the binding's class value, with a note at the parameter, so a bound vendor class is reported too. A missing service of a binding parameter is reported at the parameter.

### Listeners

A class with an `on` method needs no binding. PHP# creates a new one for every event, which is the `transient` lifetime, so each parameter of its main constructor needs a binding in the base modules, resolved from the class's namespace, of any lifetime. A missing service is reported at the parameter, as "`OrderNotices` listens to `OrderPaid` and needs a `Mailer`, and no module binds one".

### The service graph

A class gets each service from the modules of its own namespace, then from each parent namespace's modules in turn, and the standard library's module last. The first module with a binding wins. An environment or test module comes before the base module of its namespace, and in one module a binding `for` the class comes before one for any class. A binding's own parameters resolve from the namespace of the module that holds it.

The checker walks the graph from each binding through bindings only, since every class the container builds is bound, and reports, at the binding:

- a cycle, naming each class on it;
- a `scoped` service a `singleton` holds, which .NET's scope validation calls a captive dependency.

It walks the graph once for the base modules. It walks it again for each environment, as one set: the base modules under every `Module.<Environment>.sharp` of that environment, as the engine reads them all at once. It walks it again for each test module, over the base modules alone. Each environment or test module file reports, at one of its own bindings, only what its bindings take part in.

## Compiler

A file whose base or environment modules bind services returns their binding table, after any class it declares. A module of laws alone returns nothing:

```php
return [
    // namespace served, service id, class a `for` names or null, lifetime, value
    ['app\shop', 'app\mail\mailer', null, 'singleton', 'App\Mail\SmtpMailer'],
    ['app\shop', 'app\mail\mailer', 'App\Shop\SendReceipt', 'singleton', 'App\Mail\QueueMailer'],
    ['app\shop', 'list<app\pricing\pricerule>', null, 'singleton', ['App\Pricing\TaxRule', 'App\Pricing\DiscountRule']],
    ['app\shop', 'app\cache\redisstore', null, 'singleton',
        static fn (\Sharp\Environment $environment) => new \App\Cache\RedisStore($environment->require('REDIS_URL'))],
    ['app\shop', 'app\shop\checkout', null, 'transient', 'App\Shop\Checkout'],
];
```

The standard library's table names the root namespace, `''`. A test file's module is a call instead, `\Sharp\Internal\testModule(['App\Shop\CheckoutTest'], [ /* its rows */ ]);`, naming each class the file declares, even when the module binds nothing. Each of those classes also gets two methods, which PHPUnit runs around each of its tests in its own process and in a child one:

```php
#[\PHPUnit\Framework\Attributes\Before(PHP_INT_MAX)]      // before every other Before method and setUp
public function __sharpStartTest(): void { \Sharp\Internal\startTest(static::class); }

#[\PHPUnit\Framework\Attributes\After(PHP_INT_MIN)]       // after every other After method and tearDown
public function __sharpEndTest(): void { \Sharp\Internal\endTest(); }
```

PHP# refuses a member name that starts with `__`, so no member a test declares can take these names.

A compiled file whose check resolves services through modules lists their files among its inputs, so the engine refuses it after one is edited, added or deleted. A module file, a test file's module and a listener's file list the `Module.sharp` of their own folder, of each folder of a class their check reads, and of each folder above those, up to the root, with no file at most of them. An environment module also lists its environment's `Module.<Environment>.sharp` of each of those folders.

A service's id is its PHP# type in lowercase, as codex's `service_id` writes it for the analyzer, the table and the attribute alike. PHP runs a `List` or a `Map` as an array, so a constructor or binding parameter that holds a collection of objects carries `#[\Sharp\Internal\Service('list<app\pricing\pricerule>')]`. Every other parameter's service is its PHP type.

## Engine

`Sharp\Container` resolves a service the way the checker does:

- Before it builds a class, it reads the modules of the class's folder and of each folder above it, up to the project root that holds `.sharp/`. It reads each module file once per request, and stops at a module that throws as it loads, which it reads again the next time.
- With `SHARP_ENV` set, it also reads the `Module.<SHARP_ENV>.sharp` beside each `Module.sharp`, and the environment module wins outside a test.
- The standard library's `autoload.php` reads the library's module once per request, through `Sharp\Internal\readModule`.
- It builds only what a binding binds, and the class of each listener `emit` runs, a new one for every event whatever lifetime a module gives it (spec section 15). It builds a class through its constructor and calls a binding's closure. Either way, it fills each parameter with the service its `Service` attribute, or else its class, names. It reads the parameters from the function's own argument information, with no reflection.
- It runs a module file in a symbol table of its own. `zend_execute` would give the PHP code that called the engine one, which a tracing-JIT frame never frees, and `emit` and `Container::get` are both called from PHP code.
- `emit` resolves one `Sharp.Dispatcher` for the whole app, whoever emits: a running test's module binding first, whatever its namespace, then the app's top module that binds it, which `mago compile` names in `.sharp/listeners.php`, then the standard library's `ImmediateDispatcher` (spec section 15). The checker refuses a binding of it in any other app module, and each of two top modules of the same depth.
- It keeps each `singleton` for as long as the app runs and each `scoped` service for the scope the running fiber runs in. A `transient` service is built every time.
- It throws its own failures as `Sharp\ContainerError`, which extends `Error`: the service no module binds, the class that needs itself, the parameter with no class type, or the bound class that isn't there. It throws a constructor's own exception unchanged, and the request goes on.

The request is the default scope, as each CLI run is. A long-running host runs each unit of work in the standard library's `Scope.run`, which plain PHP calls as `\Sharp\Scope::run(fn () => …)` (spec section 32). A scope is a refcounted table with an ended mark. Each fiber context holds the scope it started in, the one it runs in and the services it is building, in a reserved slot the fiber observers fill, so a fiber started inside a scope belongs to it, and a `scoped` service resolved after its scope ended throws `Sharp\ContainerError`.

```php
final class Container
{
    public function get(string $id): mixed { }                  // the service $id names
    public function has(string $id): bool { }                   // whether a module binds $id
}

final class ContainerError extends \Error { }                  // a failure of the container itself
```

```csharp
// sharp/composer/library/Sharp/Scope.sharp: runs body in a new scope, and takes on body's effects
public static T run<T>(Function<T()> body) => Internal.runScoped(body);
```

**Tests.** A test file's module applies while a test of a class the file declares runs, as Spring applies a test class's own `@TestConfiguration`. The test needs no code, and the project adds nothing to `phpunit.xml`. The class's `__sharpStartTest` calls `Sharp\Internal\startTest($class)` before PHPUnit sets a test up, and its `__sharpEndTest` calls `Sharp\Internal\endTest()` after the test finishes, in PHPUnit's own process and in the child process of `#[RunInSeparateProcess]` or `processIsolation`. While a test runs, its module comes before the base modules and the environment modules don't apply, even with `SHARP_ENV` set, so a test runs exactly the set of services the checker checks. Each test builds its services again. `sharp/phpunit/` is a Composer project whose PHP# test class runs under the PHPUnit version its `composer.lock` pins, and CI runs it with `sharp/bin/test --phpunit`.

**PSR-11.** `Sharp\Psr\Container` implements `Psr\Container\ContainerInterface` over `Sharp\Container`. It throws `Sharp\Psr\NotFoundException` for an id no module binds, and `Sharp\Psr\ContainerException`, with the engine's `Sharp\ContainerError` as its previous, for a service the container can't build. A constructor's own exception passes through unchanged. The engine can't implement an interface Composer loads after it starts, so the adapter is PHP, and the Composer package requires `psr/container`.

`environment.require(name)`, a method of the standard library's `foreign` class `Sharp\Environment`, gives a variable a binding needs, and throws `ValueError` when it is not set.

## Files

```text
sharp/mago/crates/
├── syntax/src/cst/cst/module.rs        Binding, BindingValue, Lifetime, ModuleParent; ModuleMember::Binding
├── syntax/src/parser/internal/module.rs   parses bindings and the parent header
├── names/src/internal/walker.rs        resolves a binding's service, classes and `for`
├── semantics/src/internal/checker/sharp.rs   placement of base, environment and test modules
├── codex/src/metadata/module.rs        ModuleMetadata, BindingMetadata, service_id
├── codex/src/scanner/class_like.rs     registers each module's bindings; the library's serves the root namespace
├── analyzer/src/statement/module.rs    each binding, every parameter of a bound class, a binding and a listener's class,
│                                       and the service graph: cycles, captive dependencies
├── analyzer/src/statement/class_like/listener.rs   a listener's constructor takes services
├── analyzer/src/statement/return.rs    a binding expression's wrong type names the binding
├── orchestrator/src/service/incremental_analysis/compile.rs   a compiled file's module files among its inputs
└── sharp-bridge/src/lower.rs           a module file's binding table, a test file's testModule call and its classes'
                                        __sharpStartTest and __sharpEndTest, the Service attribute
ext/sharp/
├── sharp.c                             Sharp\Container, Sharp\ContainerError,
│                                       Sharp\Internal\{readModule, testModule, startTest, endTest, runScoped}
├── sharp.stub.php                      their public signatures
└── sharp_arginfo.h                     generated from sharp.stub.php
sharp/composer/
├── composer.json                       requires psr/container, and psr/event-dispatcher for slices/15-events.md;
│                                       maps Sharp\Psr\ to src/Psr/
├── autoload.php                        reads the library's module
├── library/Sharp/Module.sharp          new: binds Environment and Dispatcher for every namespace
├── library/Sharp/Scope.sharp           Scope.run, which a long-running host calls for each unit of work
├── src/Psr/Container.php               new: Sharp\Psr\Container, PSR-11 over Sharp\Container
├── src/Psr/ContainerException.php      new: PSR-11's error for a service the container can't build
└── src/Psr/NotFoundException.php       new: PSR-11's not-found error
sharp/phpunit/                          new: a PHP# test class under real PHPUnit, in one process, in a separate
                                        process and with process isolation, which sharp/bin/test --phpunit runs
sharp/bin/test                          --phpunit
sharp/docker/                           the image installs composer, php-xml and unzip
Zend/tests/sharp/
├── Container.inc                       the app the container tests build, with the library's module
├── container_resolution.phpt           each kind of binding, nested namespaces
├── container_environment.phpt          SHARP_ENV's module first
├── container_lifetimes.phpt            singleton, scoped, transient
├── scope_run.phpt                      Scope.run: a scope per body, its end, a throw, nesting, statics
├── scope_fibers.phpt                   a scope follows the fiber that runs in it
├── scope_fibers_building.phpt          each fiber builds its own services, a suspended one included
├── container_refusals.phpt             each Sharp\ContainerError, a constructor that throws, a module that won't load
├── container_test_module.phpt          new: a test file's module while its test runs
├── container_test_environment.phpt     new: no environment module while a test runs
├── container_phpunit.phpt              new: the methods PHPUnit runs around a test, in this process and a child one
├── container_psr.phpt                  new: the PSR-11 adapter and its errors
├── library_autoload.inc                the package holds the library's module
├── environment.phpt                    Environment.require
└── project.inc                         a test project's files may sit in folders
docs/
├── spec.md                             sections 15 and 32 say which classes PHP# creates
├── decisions/056-modules.md            the same, and the rejected "any concrete class"
└── architecture/modules.md             this note
sharp/baseline/spec/                    drops every section 28 and 32 example, and the examples the newer mago passes
```
