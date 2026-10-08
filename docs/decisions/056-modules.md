# 56. Modules

## Decision

Each namespace folder may hold `Module.sharp`, a `module { … }` declaration with no name, because the folder names it. It declares services with `singleton`, `scoped` and `transient`. A service is built only by a constructor call whose arguments are constants, configuration values or other services. A module holds no statements, branches, loops or methods. A choice made while the app runs goes in a class implementing `Factory<T>`, which is `public interface Factory<T> { T create(); }`. Binding a class that implements `Factory<Mailer>` to `Mailer`, as in `scoped Mailer = MailerFactory;`, makes the container call `create()` each time the binding's lifetime needs a new `Mailer`. `Environment.require("NAME")` is a static method. It returns a `string` and stops the app at startup when the value is missing.

A `scoped` lifetime is one HTTP request, one queued job or one console command run. The host starts and ends each scope, and the `php-sharp/laravel` adapter does that for Laravel. Under PHP-FPM, `singleton` and `scoped` behave the same, and they differ in long-running workers.

A nested folder's module overrides its parent's services. A test or environment module overrides with `module : App.Shop { … }`, a header that names a namespace, as the `namespace` line does. An environment module is `Module.<Environment>.sharp` beside the `Module.sharp` it overrides, such as `Module.Production.sharp`, and `APP_ENV` picks it. A test module is declared in the test file that uses it, with the same header. The compiler checks every environment's set of services. A missing or mistyped service is a compile error. The container builds every class through its main constructor, including `on` listeners. It implements PSR-11, and the optional package `php-sharp/laravel` makes Laravel's container ask PHP#'s container for every PHP# class. The language never depends on Laravel.

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
    singleton RedisStore = new RedisStore(Environment.require("REDIS_URL"));   // compiles; stops the app at startup when REDIS_URL is missing
}
```

```csharp
// app/Shop/MailerFactory.sharp
public class MailerFactory : Factory<Mailer>
{
    public MailerFactory(private FeatureFlags flags, private SmtpMailer smtp, private QueueMailer queue) { }
    public Mailer create() => this.flags.enabled("queue-mail") ? this.queue : this.smtp;   // compiles: the choice is made while the app runs
}

// in a module
scoped Mailer = MailerFactory;                                                 // compiles: create() runs once per request, job or command
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
public class Checkout
{
    public Checkout(private Mailer mailer, private PaymentGateway gateway) { }   // compile error when no module binds PaymentGateway
}
```

Each folder states what its classes get, next to the classes. The compiler sees every binding and every constructor, so a missing service fails the build, not a request.

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
        const redis = new RedisStore(Environment.require("REDIS_URL"));
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
- **Chosen, `Factory<T>`:** Spring's `FactoryBean<T>`.
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
