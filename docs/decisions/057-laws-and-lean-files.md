# 57. Laws and structure in PHP#

## Decision

Both kinds of facts the checker verifies are written in PHP#, in `.sharp` files (Jordan, 10-21). No `.lean` file of the project is ever read.

**Laws only, as in Bend.** A class, an enum or a module states its laws in PHP#, with `law`. A law is a claim over every value of its parameters, and `when` after the parameters states its condition. `law name(parameters) => claim;` is proven by the checker alone. A law the checker cannot prove alone carries a body after its claim, and the body is its proof, in ordinary PHP#: `if` and `else`, `match` statements with block arms, locals, `return;`, and calls of laws as statements. A call of a law is a fact the case knows from then on, and a law calls itself only on a smaller value. No loop carries an invariant.

Lean stays underneath. The checker translates the pure code a law reaches and the theorem of each law into the generated `.sharp/` folder on each compile, turns a body into the theorem's proof, and Lean's kernel checks it. Nobody writes or reads Lean. A case the checker cannot close is the error `law-unproven`, with the case's conditions and the claim still to show printed in PHP#.

**Structure is a module's `imports` list, as Java's `module-info` lists what a module `requires`.** A namespace's `Module.sharp` lists the namespaces its code imports from, `module { imports App.Tenant.Community, App.Shared.Schema; }`, and an import of any other namespace is a compile error at the import. The checker checks the list as it checks types, with no Lean.

## Options for laws

### Chosen: laws only, the body is the proof in PHP#, Lean underneath

```csharp
// app/Shared/Money.sharp
namespace App.Shared;

public class Money
{
    public Money(public int amount { get; }, public string currency { get; }) { }
    public Money add(Money other) => new Money(this.amount + other.amount, this.currency);
    public Money times(int n) => n <= 0 ? new Money(0, this.currency) : this.add(this.times(n - 1));

    law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency;   // the checker proves it alone
    law timesKeepsCurrency(Money m, int n) => m.times(n).currency == m.currency
    {
        if (n <= 0) {
            return;                                     // m.times(n) is new Money(0, m.currency)
        }
        Money.timesKeepsCurrency(m, n - 1);             // the law for the smaller n
        Money.addKeepsCurrency(m, m.times(n - 1));      // adding keeps that currency
    }
}
```

A PHP# developer, usually an AI agent, writes a claim and its proof in the language it already writes, in the file that holds the code. Each `if` and `match` arm is a case split, each law call a fact, a call of the law itself Lean's recursion on the theorem, and each `return;` closes its case with Lean's automatic steps. When a case stays open, the error says what is left in PHP#:

```text
error[law-unproven]: law timesKeepsCurrency is not proven
  when n > 0, still to show: m.add(m.times(n - 1)).currency == m.currency
  help: the law for a smaller n is the call Money.timesKeepsCurrency(m, n - 1)
```

### Rejected: proofs in a Lean file beside the class

```lean
-- app/Shared/Money.lean
import Code.App.Shared.Money
open Sharp

theorem addKeepsCurrency : App.Shared.Money.addKeepsCurrency := by
  simp [App.Shared.Money.addKeepsCurrency, App.Shared.Money.add]
```

This was the previous decision: the checker created `Money.lean`, appended the proof Lean's automatic steps found or a marked gap, and refused a law with no proof, a gap, or a proof whose law was deleted. An agent had to know Lean to finish any proof the automatic steps missed, and every law split across two files in two languages.

### Rejected: Lean `by` steps inside the `.sharp` file

```csharp
law addKeepsCurrency(Money a, Money b) => a.add(b).currency == a.currency
    by simp [Money.add];
```

Lean's tactic language inside PHP#. The developer still writes Lean, and the steps name Lean's lemmas and the translation's names, which change when the translation does.

### Rejected: test-like proof blocks

```csharp
law timesKeepsCurrency(Money m, int n) => m.times(n).currency == m.currency
    proof {
        assert(m.times(0).currency == m.currency);
        assert(m.times(n).currency == m.times(n - 1).add(m).currency);
    }
```

A block of assertions reads like a test, and a test checks the values it names. A law covers every value, so its proof needs cases and recursion, which a list of assertions cannot state. The chosen body states them with PHP#'s own `if`, `match` and calls.

### Rejected: `ensures` and `requires` contracts on methods

```csharp
public Money add(Money other)
    requires other.currency == this.currency
    ensures result.currency == this.currency
    => new Money(this.amount + other.amount, this.currency);
```

Dafny and Eiffel attach claims to each method. Every method body then needs its own proof, a caller must show each `requires` at every call, and a fact that spans methods, such as one about `times` and `add` together, has no home. A law states a fact on its own, over the code it names, and `when` covers what `requires` would.

### Rejected: loop invariants

```csharp
public static int openCount(List<Order> orders)
{
    let count = 0;
    for (const o of orders) invariant count <= orders.count() {
        if (o.open) {
            count += 1;
        }
    }
    return count;
}
```

Dafny proves a loop through an invariant the developer writes on it. The checker proves a loop that only counts or sums alone, and a loop it cannot prove is written as recursion that gets smaller, which a law's body proves with a call of the law itself.

### Rejected: one rules file beside each namespace folder

```text
app/Tenant/
├── Store.lean           <- every rule and law about App.Tenant.Store
└── Store/
    ├── Refunds.sharp
    └── StoreService.sharp
```

The layout before the previous decision. Every law of a namespace collected in one growing Lean file, far from the class it describes.

### Rejected: laws stated in Lean

```lean
-- app/Tenant/Store.lean
theorem refundNeverExceedsPaid (paid refunded amount : Int)
    (h1 : refunded ≤ paid) (h2 : amount ≤ App.Tenant.Store.Refunds.remaining paid refunded) :
    refunded + amount ≤ paid := by
  unfold App.Tenant.Store.Refunds.remaining
  omega
```

A PHP# developer can't read what a class claims without reading Lean.

## Options for structure

### Chosen: the importing namespace lists what it imports

```csharp
// app/Tenant/Store/Module.sharp
namespace App.Tenant.Store;

module
{
    imports App.Tenant.Community, App.Shared.Schema, Illuminate.Support;
}
```

A namespace states its own dependencies in the module file it already has, in PHP#. An import outside the list is a compile error at the import line, and two lists that name each other are a compile error on each list, so namespaces import one way.

### Rejected: a provider-side `visible to` list, as Rust's `pub(in path)` and Go's `internal`

```csharp
// app/Shared/Ledger/Module.sharp
module
{
    visible to App.Shared, App.Tenant.Billing;
}
```

The namespace that is used names who may use it. Rust's `pub(in path)` and Go's `internal` folders work this way. A namespace then can't see its own dependencies in one place, and a rule such as "Store imports only Community and Schema" needs a line in every namespace Store must not reach.

### Rejected: structure rules as Lean `@[rule]` definitions in `Module.lean`

```lean
-- app/Tenant/Store/Module.lean
import Code.App.Tenant.Store.Module
open Sharp

@[rule] def importsOnly : Rule := .importsOnly [App.Tenant.Community, App.Shared.Schema]
```

This was the previous design: Lean ran each rule over the code's namespaces, imports and references. A PHP# developer had to write Lean to state where code may depend, and every compile that judged a rule started Lean.

## Precedent

- **Chosen, laws:** Bend 2, where a law and its proof are in the same file: a law is proven by a definition of the same name, written in Bend itself. Bend's `--verdict` mode and Aeneas, which translates Rust to Lean, check code through a prover the developer never writes.
- **Chosen, the error that shows what is left:** Lean's and Agda's goal display, printed here in PHP#.
- **Chosen, structure:** Java's `module-info.java`, whose `requires` lists the modules a module reads.
- **Rejected, proof files:** the previous version of this decision.
- **Rejected, contracts and loop invariants:** Dafny, Eiffel.
- **Rejected, a provider-side list:** Rust's `pub(in path)`, Go's `internal`.

## Spec

- [Section 27, PHP# files](../spec.md#27-php-files)
- [Section 28, Verification](../spec.md#28-verification)
- [Section 28.1, Lean files](../spec.md#281-lean-files)
- [Section 32, Modules](../spec.md#32-modules)
