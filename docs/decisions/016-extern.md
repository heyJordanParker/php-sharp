# 16. Declaring the effect of plain PHP

## Decision

A plain PHP library's effect is declared once in the project with `extern`, such as `extern StripeClient uses Http;`. A second declaration is a compile error, and a call with no declaration has an unknown effect that no `uses` accepts.

## Options

### Chosen: one `extern` per class, method or function

```csharp
// app/Stubs/Stripe.sharp
namespace App.Stubs;

import Stripe.StripeClient;

extern StripeClient uses Http;   // compiles: the one declaration in the project
extern StripeClient;             // compile error: StripeClient already has an extern declaration
```

```csharp
public class StripeGateway : PaymentGateway
{
    public Charge charge(Cart cart) => StripeClient.charges().create(…);   // compiles: StripeClient is declared Http
}

public class MailchimpSync : AudienceSync
{
    public void sync(Contact contact) { Mailchimp.lists().addListMember(…); }   // compile error: Mailchimp has no extern
}
```

### Rejected: a `foreign` wrapper class per library

```csharp
public foreign class Stripe
{
    public Charge charge(ChargeRequest request) => StripeClient.charges().create(request);   // compiles; one method per library method
}

public class StripeGateway : PaymentGateway
{
    public StripeGateway(private Stripe stripe) { }   // every user receives the wrapper through its constructor
}
```

Each library needs a class that repeats every method it uses, before any PHP# code can call it.

### Rejected: a `uses PHP` catch-all

```csharp
public interface AudienceSync
{
    void sync(Contact contact) uses PHP;   // not PHP#: accepts any plain PHP call
}
```

An implementation may then reach the network, the database or the clock, and the declaration says none of it.

### Rejected: the effect written on each import

```csharp
import Stripe.StripeClient uses Http;       // not PHP#: in one file
import Stripe.StripeClient uses Database;   // not PHP#: in another file, which disagrees
```

The effect is repeated in every file that imports the library, and nothing keeps the copies in agreement.

## Precedent

- **Chosen:** C#'s `extern`, TypeScript's `.d.ts` files, Hack's `.hhi` files and Koka's `extern`.

## Spec

[Section 29, Effects](../spec.md#29-effects)
