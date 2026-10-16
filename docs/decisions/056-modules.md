# 56. Modules

Superseded in part by [decision 78](078-hosts.md): a host starts and ends each scope with the standard library's `Scope.run`, and PHP# ships no `php-sharp/laravel` adapter. A `singleton` and a static live as long as the app runs, which is one request under PHP-FPM and many under a long-running worker.

## Decision

Each namespace folder may hold `Module.sharp`, a `module { … }` declaration with no name, because the folder names it. It declares services with `singleton`, `scoped` and `transient`. A binding's value is a class, built through its main constructor, or one expression. A binding names the services it needs as parameters, as Dagger's `@Provides` methods do, so a choice made while the app runs is one expression over them, run each time the binding's lifetime needs a new service. A module holds no statements, branches, loops or methods. `environment.require("NAME")` is a method of the `Environment` a binding takes as a parameter. It returns a `string` and throws `ValueError` when the variable is not set.

A `scoped` lifetime is one HTTP request, one queued job or one console command run. The host starts and ends each scope, and the `php-sharp/laravel` adapter does that for Laravel. Under PHP-FPM, `singleton` and `scoped` behave the same, and they differ in long-running workers.

A nested folder's module overrides its parent's services. A test or environment module overrides with `module : App.Shop { … }`, a header that names a namespace, as the `namespace` line does. An environment module is `Module.<Environment>.sharp` beside the `Module.sharp` it overrides, such as `Module.Production.sharp`, and `SHARP_ENV` picks it. A test module is declared in the test file that uses it, with the same header. While a test of that file runs, its services are the test module's over the base modules, with no environment module, so the run time runs exactly the set the compiler checks. The compiler checks every environment's set of services: the base modules under all of that environment's modules at once. A missing or mistyped service is a compile error. PHP# creates the classes a module binds and the classes that declare an `on` method, each through its main constructor, as NestJS, ASP.NET Core, Angular and Guice with `requireExplicitBindings()` create only what is registered. A concrete class binds to itself, as `transient Checkout = Checkout;`. Every parameter of those constructors and of every binding needs a binding, and the compiler checks every such class whether or not another class takes it. A class no module binds is ordinary code, created with `new`. A listener's class needs no binding: PHP# creates a new one for every event and passes its constructor's services from the module of its namespace. The standard library's module binds `Environment` for every namespace. The container implements PSR-11, and the optional package `php-sharp/laravel` makes Laravel's container ask PHP#'s container for every PHP# class. The language never depends on Laravel.

A module is where `foreign` objects are created. This replaces "created once, where the app starts". The binding's lifetime decides how often a `foreign` object is created, and it reaches classes only through constructors. `Module` is a reserved file name.

## Options

### Chosen: a module per namespace folder, checked at compile time

```csharp
// app/Shop/Module.sharp
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Mail.QueueMailer;
import App.Pricing.PriceRule;
import App.Pricing.TaxRule;
import App.Pricing.DiscountRule;
import App.Cache.RedisStore;

module
{
    singleton Mailer = SmtpMailer;                                             // compiles
    singleton Mailer for SendReceipt = QueueMailer;                            // compiles: SendReceipt gets a QueueMailer
    singleton List<PriceRule> = [TaxRule, DiscountRule];                       // compiles
    singleton RedisStore(Environment environment) = new RedisStore(environment.require("REDIS_URL"));   // compiles: a foreign object, created here
    transient SendReceipt = SendReceipt;                                       // compiles: a class binds to itself, so PHP# creates it
}
```

```csharp
// in a module that binds FeatureFlags, QueueMailer and SmtpMailer
scoped Mailer(FeatureFlags flags, QueueMailer queue, SmtpMailer smtp) = flags.enabled("queue-mail") ? queue : smtp;   // compiles: runs once per request, job or command
```

```csharp
// app/Shop/Module.Production.sharp
module : App.Shop
{
    singleton Mailer = QueueMailer;                                            // compiles: in production, replaces App.Shop's SmtpMailer
}

// tests/Shop/CheckoutTest.sharp
module : App.Shop
{
    singleton Mailer = FakeMailer;                                             // compiles: the tests in this file get a FakeMailer
}
```

```csharp
// app/Shop/Checkout.sharp
public class Checkout
{
    public Checkout(private Mailer mailer, private PaymentGateway gateway) { }
}

// app/Shop/Module.sharp
module
{
    singleton Mailer = SmtpMailer;
    transient Checkout = Checkout;   // compile error: Checkout needs a PaymentGateway, and no module binds one
}
```

Each folder states what its classes get, next to the classes. The compiler sees every binding and every constructor, so a missing service fails the build, not a request.

### Rejected: the container builds any concrete class it is asked for

A class no module binds would be built through its constructor, as .NET's `ActivatorUtilities` and Laravel's container do. The compiler would then have to decide which classes the container builds, so a class created with `new`, such as `Money`, could be refused for a constructor parameter no container fills. Two hybrids of this were tried and rejected as incoherent. Only bound classes and listeners are created, so the set the compiler checks is the set the container builds.

### Rejected: runtime resolution by type, with registration at startup

```csharp
public class AppServiceProvider : ServiceProvider
{
    public override void register()
    {
        this.app.singleton(typeof(Mailer), app => new SmtpMailer());           // compiles; a forgotten line fails only when a class needs a Mailer
    }
}
```

.NET's `IServiceCollection` and Laravel's container work this way. A missing service shows at startup or at first use, not at compile time.

### Rejected: startup code that creates every object by hand

```csharp
public class Main
{
    public static void start()
    {
        const redis = new RedisStore(new Environment().require("REDIS_URL"));
        const checkout = new Checkout(new SmtpMailer(), new StripeGateway(redis));   // every class is created here
    }
}
```

This was the old "created once, where the app starts", in `Main.start`. Every new class edits one file.

### Rejected: Scala 3's `given` and `using`

```scala
given Mailer = SmtpMailer()
class Checkout(using mailer: Mailer)
```

A constructor's needs are found by type from the scope around the call, which can sit far from the class.

### Rejected: attributes

```csharp
public class Checkout
{
    [Inject] public Mailer mailer { get; set; }                               // would compile
}
```

Attributes are for consumers, such as an app's own `[Retry]`, never for the core language.

## Precedent

- **Chosen:** NestJS modules for the per-folder structure and overrides, and Dagger for the compile-time check.
- **Chosen, only bound classes:** NestJS, ASP.NET Core, Angular, and Guice with `requireExplicitBindings()`.
- **Chosen, binding parameters:** Dagger's `@Provides` methods.
- **Chosen, `scoped`:** ASP.NET Core's per-request scope.
- **Chosen, environment and test modules:** ASP.NET Core's `appsettings.Production.json`, and NestJS's testing module.
- **Rejected, runtime resolution:** .NET's `IServiceCollection` and Laravel's service container.
- **Rejected, `given` and `using`:** Scala 3's contextual abstractions.
- **Rejected, attributes:** Java's `@Inject` (JSR-330).

## Spec

- [Section 9.1, Named constructors](../spec.md#91-named-constructors)
- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)
- [Section 32, Modules](../spec.md#32-modules)
