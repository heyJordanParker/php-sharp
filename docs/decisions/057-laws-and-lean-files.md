# 57. Laws and Lean files

## Decision

A class states its laws in PHP#, as in `law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;`. The parameters range over every possible value. Its proofs go in a Lean file of the same name beside it, `Money.lean` beside `Money.sharp`. A module states laws that span its classes in `Module.sharp`. Their proofs, and the namespace's structure rules, go in `Module.lean` in the same folder. Both files are optional. The old layout, one rules file beside the folder, goes.

The checker generates the Lean translation of pure code and the Lean statement of each law into `.sharp/`, never into the source tree. A law's generated statement is named by its class's full name and the law's name, as in `App.Shared.Money.addKeepsCurrency`. A module law's is named by its namespace, `Module` and the law's name, as in `App.Shop.Module.ordersStayInTenant`. When a class or module has a law and no Lean file, `mago compile` creates the file beside it, so it writes `.sharp/` and that one missing `.lean` file. For each new law, it appends a proof found by Lean's automatic steps (`simp`, `omega`, `decide`), or a marked gap when none is found. It never rewrites a proof that exists. A law without a proof, a gap, or a proof whose law was deleted is a compile error that names it. Laws hold only over pure code.

## Options

### Chosen: laws in PHP#, proofs in a Lean file beside the class

```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;   // compiles once Money.lean proves it
}
```

```lean
-- app/Shared/Money.lean
import Code.App.Shared.Money
open Sharp

theorem addKeepsCurrency : App.Shared.Money.addKeepsCurrency := by
  simp [App.Shared.Money.addKeepsCurrency, App.Shared.Money.add]
```

The checker created `Money.lean` and appended the proof `simp` found. A PHP# developer reads what a class claims in the class itself, in PHP#. The proof sits one file away, and the checker writes the first draft of it.

### Rejected: one rules file beside each namespace folder

```text
app/Tenant/
├── Store.lean           <- every rule and law about App.Tenant.Store
└── Store/
    ├── Refunds.sharp
    └── StoreService.sharp
```

This was the old layout, `app/Tenant/Store.lean`. Every law of a namespace collects in one growing file, far from the class it describes.

### Rejected: laws stated in Lean

```lean
-- app/Tenant/Store.lean
theorem refundNeverExceedsPaid (paid refunded amount : Int)
    (h1 : refunded ≤ paid) (h2 : amount ≤ App.Tenant.Store.Refunds.remaining paid refunded) :
    refunded + amount ≤ paid := by
  unfold App.Tenant.Store.Refunds.remaining at h2
  omega
```

This was the old "laws never sit in a `.sharp` file". A PHP# developer can't read what a class claims without reading Lean.

### Rejected: laws and proofs both in `.sharp`

```csharp
law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency
    proof { … };                                    // a proof language inside PHP#
```

Dafny works this way. PHP# would need its own proof language, where Lean already has one, with its tactics and its library.

## Precedent

- **Chosen:** Bend 2, which splits claims (`LAWS.bend`) from proofs (`PROOF.bend`). For a state machine, a pure transition method on the status enum lets laws cover every state and event.
- **Rejected, one file per namespace:** Mathlib's `Mathlib/Order.lean` beside `Mathlib/Order/`.
- **Rejected, laws and proofs together:** Dafny.

## Spec

- [Section 27, PHP# files](../spec.md#27-php-files)
- [Section 28, Verification](../spec.md#28-verification)
- [Section 28.1, Lean files](../spec.md#281-lean-files)
