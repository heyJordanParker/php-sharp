# Slice 12: Collection methods

Spec section 12's collection methods, with the parts of section 19 they reach (`==` on the element type). v1 plan Slice 2. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Section 12's collection methods `add`, `set`, `get`, `filter` on a `List`, `map`, `sumOf`, `first`, `any`, `groupBy`, `associateBy` and `sortedBy` work on master. The rest are missing or wrong, and two interim names the spec does not have still compile:

- **Seven methods do not exist.** `List`'s `contains`, `indexOf`, `insert`, `remove`, `clear` and `last`, and `Map`'s `has`, `keys` and `remove` each give mago's generic `non-existent-method`, such as "Method `contains` does not exist on `List<string>`."
- **A `Map`'s `filter` is silently accepted and wrong.** It compiles, types its result `List<V>`, and runs as the renumbering `List` filter, so the keys are lost. The spec keeps them.
- **`groupBy` and `associateBy` refuse a backed enum key,** which section 12's own `enum-map-keys` example writes: "Argument type mismatch for template `G`."
- **`Map`'s `delete` and `filterValues` compile.** They were the interim names while the engine compiled each file alone. Typed compilation has landed, so decision 026's chosen names replace them.

The Slice builds all of it in 3 mago-sharp PRs and 1 php-sharp PR, on the two precedents master already has. A read runs inline as PHP on the array, as `count` does (`INLINED_COLLECTION_METHODS`). A change writes back to its place, as a `Set`'s `add`, `remove` and `clear` do (`CHANGING_COLLECTION_METHODS`). The engine gains four `Sharp\Collection` methods. `insert` and `removeAt` exist because a PHP function that takes the array by reference cannot write a property with a `set` accessor. `put` and `clear` exist because ruling 7 makes a `List` index a place, and only the engine's collection of a `List` element reaches the element the list stores (below).

When the Slice lands, 6 of section 12's 10 entries leave `sharp/baseline/spec`: `collection-read-methods`, `collection-write-methods`, `index-changes`, `index-rules`, `natural-remove` and `nullable-map-values`. `enum-map-keys` stays, because the example uses a `Username` struct it never declares (task #498). Two of them need one spec-files change first: the harness `Line` declares no `operator ==`, so section 12's own rule makes `lines.contains(line)` and `lines.remove(line)` compile errors (ruling 1). The other 3 entries are not collection methods (found outside the Slice), so this Slice cannot write `Completes: §12` (ruling 2).

## Decisions team-lead approves

1. **Every read the spec names runs inline as PHP, and every change writes back to its place.** `contains`, `indexOf`, `last`, `has` and `keys` join `INLINED_COLLECTION_METHODS`. `clear` writes `$place = []`, as a `Set`'s does, and on a `List` element it runs `Sharp\Collection::clear` (ruling 7, below). `remove` searches inline, then removes through a `Sharp\Collection` method, as a `Set`'s `remove` does. `insert` is an ordinary call of the new `Sharp\Collection::insert`, as `add` and `set` are.
2. **`contains`, `indexOf` and a `List`'s `remove` compare by section 19's `==`, lowered once.** Where `==` on the element type lowers to `===`, the search is `\in_array` or `\array_search` with `strict: true`. Where the element's class declares `operator ==`, the search is `\array_find_key` with that `op_Equality`. Both forms come from the one helper `a == b` lowers through, so a search never compares differently from `==`.
3. **The checker refuses a search where `==` has no meaning on the element type,** with the reason `==` itself gives: a class without `operator ==` is an error, and a collection element is "not supported yet", as `==` on a collection is today.
4. **A `Map`'s `filter` runs as `Sharp\Collection::filterValues`,** which keeps the keys, as a `Set`'s `filter` does on master.
5. **`MapMethods::delete` and `MapMethods::filterValues` are deleted,** with no alias. Decision 026 rejected them as "one name per meaning", kept only while the engine could not tell a `List` from a `Map`. The engine's own `Sharp\Collection::delete` and `filterValues` stay, as runtime names PHP# code never writes.
6. **`groupBy` and `associateBy` take a key with an `int` or `string` backing value,** as a `Map` type does. The engine stores a backed enum case under its backing value, as `counts[status]` stores `$status->value`, and a loop reads it back as the case from the checker's type, as it already does.
7. **`keys()` reads each key back as the key type,** as a `for … of` loop does through `Lowering::key_read_back`: a `string` through `\strval`, a backed enum through its `from`.

## Edge cases the spec leaves open

Each is a proposal with its precedent. team-lead rules. The note writes the proposal everywhere below.

1. **`indexOf` with no match gives `null`, and its type is `int?`.** Precedent: Swift's `firstIndex(of:)` and Rust's `position` give an optional. PHP#'s own `List.get(i)` gives `T?`, and a missing `Map` key reads as null. A `-1` would reach `items[-1]`, which section 12 says throws. The other choice is Kotlin's, C#'s and Java's `-1`, with type `int`.
2. **`last()` of an empty `List` throws `OutOfRangeException` with "The list is empty."** Precedent: Kotlin's `last()` throws `NoSuchElementException("List is empty.")`, and C#'s `Last()` throws. PHP#'s `first(predicate)` and a bare index read already throw `OutOfRangeException`. The other choice is Swift's `last`, which gives `T?`.
3. **A `Map`'s `remove(key)` returns `bool`: true when the key was there.** Precedent: C#'s `Dictionary.Remove`. PHP#'s `List.remove` and `Set.remove` already return `bool` with that meaning, so one name has one result type. The other choice is Kotlin's and Swift's, which return the removed value as `V?`, and that cannot tell a missing key from a stored null in a `Map<string, int?>`.
4. **`insert(i, v)` takes `0 <= i <= count()`.** `i == count()` appends. Outside the range it throws `OutOfRangeException` "Undefined array key N", the message `set` throws past the end. Precedent: Kotlin's `add(index, element)`, C#'s `Insert` and Java's `add(int, E)` all throw outside `0..size`.
5. **A `List`'s `remove(value)` of a value it does not hold returns false and changes nothing.** The spec fixes this (`tags.remove("z"); // false, and tags is unchanged`). Nothing is written back, so a property's `set` does not run.

## Ruling 7: a `List` index is a place

Section 12 says "A `List` index is a place, as a Rust `Vec` index and a Go slice index are, so a change through it changes the element the list stores", and "A change through a `Map` index is a compile error". The Slice builds each rule this way:

1. **A change through a `List` index changes the stored element.** `grid[0].add(5)`, `grid[0].insert(0, 7)`, `grid[0].remove(1)`, `grid[0].set(0, 8)` and `cube[1][0].add(3)` lower to the method on the index, `$grid[0]->add(5)`. `zend_compile_sharp_receiver` reads an index receiver through the collection of what it indexes. `sharp_collection_read_dimension` then gives an element collection that holds its container and index. A change separates every array the element is in, through `sharp_collection_place`, and writes back through the container. So a property's `set` hook runs once per change, and a copy that shares the list stays as it was.
2. **A write into a `Map` element of a `List` is a `put`.** `maps[0]["c"] = 3` lowers to `$maps[0]->put('c', 3)`, and `maps[0]["d"] ??= 4` puts only for a missing key. A `Set`'s `add` on an index is a `put` too. `Sharp\Collection::put(key, value)` inserts or replaces the element and gives the value.
3. **A write into a `Map` a hooked property holds is a `put` on the property.** PHP gives no reference to a hooked property, so `this.totals["c"] = 3` lowers to `$this->totals->put('c', 3)`, and its `set` hook runs. A property without hooks keeps PHP's write, so its opcodes match the PHP twin's.
4. **`clear` on a `List` element is `Sharp\Collection::clear`.** `grid[0].clear()` lowers to `$grid[0]->clear()`, which empties the element and writes it back. A `clear` on a local, a field or a property stays `$place = []`.
5. **A missing index throws `OutOfRangeException`,** as a bare read does, and leaves no gap. That covers a method through the index (`grid[5].add(1)`), a `clear`, a write into a `Map` element (`maps[2]["c"] = 3`), and a property written through the index (`points[5].x = 5`, `+=` and `++`). `zend_delayed_compile_prop` reads a PHP# index whose property is written, so `FETCH_DIM_R` with `ZEND_SHARP_OPERATOR` throws, where PHP's `FETCH_DIM_W` would add null at the key.
6. **After `?.`, a change through a `List` index reaches the stored element, and a null element or a missing index skips the call.** Spec line 840 says "A `Map` read is handled where it is read, with `??`, `?.`, `is`, `as`, `match` or `get`", and line 854 says "The same holds for a `List` index". So a `List` index read with `?.` treats a missing index as null, and only a bare read throws (rule 5). `rows[0]?.add(5)` lowers to `$rows[0]?->add(5)`, the index itself, not a copy. The bridge marks that index with `ZEND_DIM_IS`, the mark php-src gives an index `??` reads. `zend_compile_sharp_receiver` then emits `FETCH_DIM_IS`, and `sharp_collection_read_dimension` gives null for a missing index under `BP_VAR_IS`, which the call's `JMP_NULL` skips. The rule holds whichever path the method takes. A method the bridge inlines, such as `rows[9]?.clear()`, `rows[9]?.remove(1)`, `rows[9]?.contains(1)` or `rows[9]?.count()`, tests `($rows[9] ?? null) === null` first. Any other method that changes nothing, such as `rows[0]?.entries()`, keeps `($rows[0] ?? null)?->entries()`.
7. **A change through a `Map` index is a compile error,** as Go refuses `m[k].x = 5`, because a `Map` read is a copy. Section 12's `index-changes` example annotates `stock["sku-1"]?.add(5);` as a compile error, and the checker refuses it.
8. **An element a plain PHP `ArrayAccess` object gives is a collection of its own.** `rows[0].map(…)` on such an object casts the array `offsetGet` gives to a `Sharp\Collection`, as master does. The change does not reach the object, which owns its own storage.

## Rulings needed outside this Slice

1. **The harness `Line` needs `operator ==` and `hash()`.** Section 12 says "`contains`, a `List`'s `remove`, `indexOf` and `Set<T>` need `==` on the element type. Without it, they are a compile error." Section 19 gives a class `==` only through `operator ==`. `sharp/harness/app/Line.sharp` on `ci/spec-examples` declares none, so `collection-read-methods` (`lines.contains(line)`) and `collection-write-methods` (`lines.remove(line)`) fail by the spec's own rule. The spec never declares `Line`, so the harness decides. Proposal, after the harness `Money`:

   ```diff
    public class Line
    {
        public int id { get; set; } = 1;
        …
        public Product product { get; set; } = new Product();
   +
   +    public static bool operator ==(Line a, Line b) => a.id == b.id;
   +
   +    public int hash() => this.id;
    }
   ```

   `sharp/harness/` is a spec file, so this lands in a spec PR before PR 4. The other choice is to change both examples to a `List<string>`.
2. **No PR of this Slice can write `Completes: §12`.** `COMPLETES` fails on a section that keeps an entry, and §12 keeps `map-string-keys`, `iterables` and `yield`, none of them a collection method. Proposal: PR 4's body writes no `Completes:` line and names the six entries it removes. `Completes: §12` lands with the last of the PRs that own the other three (found outside the Slice).

## How the states were observed

Every state below comes from the `mago` built from mago-sharp `master` at `8818f9550` in slot 1, `/Users/jordan/Developer/sharp-worktrees/1/mago-sharp/target/debug/mago`, built at 01:19 on 2026-10-12 (`stat -f "%Sm"`) with `git status --short` empty. It ran on one scratch project, `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-collection-methods/ws`, whose `composer.json` maps `App\` to `app/` and whose `mago.toml` has `paths = ["app"]`.

```sh
MAGO=/Users/jordan/Developer/sharp-worktrees/1/mago-sharp/target/debug/mago
P=/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-collection-methods
compile() { env -u FORCE_COLOR XDG_CACHE_HOME=$P/cache MAGO_REPORTING_FORMAT=emacs MAGO_LOG=error \
  $MAGO --no-version-check --colors never --threads 2 --workspace $P/ws compile; }
```

One `compile` run printed every error line quoted below, and ended with `Compiled 6 PHP# files into .sharp/. 12 PHP# files were refused`. The 6 compiled files are `Line`, `Plan`, `Product`, `Money`, `Interim` and `MapFilter` (`find $P/ws/.sharp -type f`).

`Line`, `Plan`, `Product` and `Money` are copies of `sharp/harness/app/` on `ci/spec-examples`. `Money` declares `operator ==` and `hash()`, and `Line` declares neither. Four files hold section 12's examples as `sharp/bin/spec` builds them: the setup lines and the block inside a `run` method, each bare expression assigned to a `const`, because PHP# refuses a statement with no effect.

**The engine part of each state is read from code.** No PHP# engine is built in slot 1 (`stat …/php-sharp/sapi/cli/php` finds none). A compiled file runs the PHP `crates/sharp-bridge/src/lower.rs` emits. A collection method the bridge does not inline lowers to `$array->method(…)`, and `ZEND_METHOD_CALL_SHARP` makes the engine run it on `Sharp\Collection` in `ext/sharp/sharp.c`.

**PHP's own behavior was run** with Homebrew PHP 8.5.1, the version php-sharp is based on (`main/php_version.h` says 8.5.11), on `$P/php-behavior.php` and `$P/php-hooks.php`. Each PHP result quoted below is that run's output.

# Every method of section 12, its state and its mechanism

The states are **works**, **missing** (refused with `non-existent-method`), **refused** (another error), **silently accepted** (code the spec does not have compiles) and **wrong** (it compiles and runs differently from the spec).

## Methods that work on master

`add`, `set`, `get`, `count`, `filter` on a `List`, `map`, `sumOf`, `first`, `any`, `groupBy` and `associateBy` with an `int` or `string` key, and `sortedBy` work. `app/ReadMethods.sharp` holds `collection-read-methods`, and only its `contains` line was refused:

```csharp
namespace App;

class ReadMethods
{
    public void run()
    {
        List<Line> lines = [new Line()];
        Line line = new Line();
        const total = lines.filter(l => !l.refunded).sumOf(l => l.amount);
        const names = lines.map(l => l.name);
        const free = lines.first(l => l.free);
        const anyFree = lines.any(l => l.free);
        const held = lines.contains(line);
        const groups = lines.groupBy(l => l.product.id);
        const byId = lines.associateBy(l => l.id);
        const sorted = lines.sortedBy(l => l.amount);
    }
}
```

printed only `app/ReadMethods.sharp:13:28:error - non-existent-method: Method `contains` does not exist on `List<Line>`.`

- Mechanism: none in this Slice. `crates/prelude/assets/extensions/sharp.php` declares them on `Sharp\ListMethods`, `Sharp\MapMethods` and `Sharp\SetMethods`, and `ext/sharp/sharp.c` runs them on `Sharp\Collection`.
- `count` and every `Set` method are the precedent: `mago_names::INLINED_COLLECTION_METHODS` names them, and `Lowering::collection_call` lowers each from the checker's type.

## `contains`

"`lines.contains(line);`" in the methods that read, and "`contains` … need[s] `==` on the element type (section 19). Without it, [it is] a compile error."

Missing. `app/Contains.sharp`:

```csharp
namespace App;

class Contains
{
    public bool vip(List<string> tags) => tags.contains("vip");
    public bool priced(List<Money> prices, Money price) => prices.contains(price);
}
```

printed

```text
app/Contains.sharp:5:48:error - non-existent-method: Method `contains` does not exist on `List<string>`.
app/Contains.sharp:5:43:error - mixed-return-statement: Could not infer a precise return type for method `Contains.vip`. Saw type `Any?`.
app/Contains.sharp:6:67:error - non-existent-method: Method `contains` does not exist on `List<Money>`.
app/Contains.sharp:6:60:error - mixed-return-statement: Could not infer a precise return type for method `Contains.priced`. Saw type `Any?`.
```

and `ReadMethods.sharp` above refused `lines.contains(line)` the same way.

- PHP it lowers to:
  - an element whose `==` lowers to `===` (an `int`, a `string`, a `bool`, an enum, `Any`, and each of them nullable): `\in_array('vip', $tags, true)`
  - a `List<float>` given an `int`: `\in_array((float) $n, $ratios, true)`, as `float_equality` casts the side that may hold an `int`
  - an element whose class declares `operator ==`: `\array_find_key($prices, static fn ($element) => \App\Money::op_Equality($element, $price)) !== null`, where `$element` is a hidden `element#N`
- PHP's behavior, run: `in_array("a", ["a","b","a"], true)` is `true`, `in_array(1, [1.0], true)` is `false`, and `in_array(new Money(5), [new Money(5)], true)` is `false`. So strict search is section 19's `==` exactly where `==` lowers to `===`, needs the `(float)` cast for an `int` argument, and is identity, not `==`, for a class with `operator ==`.
- Mechanism: prelude, names, analyzer, bridge.
  - Prelude: `ListMethods::contains(T $value): bool`, `@mutation-free`.
  - Names: `("List", "contains")` joins `INLINED_COLLECTION_METHODS`.
  - Analyzer: `check_element_equality`, beside `check_sorted_by_key` in `crates/analyzer/src/expression/binary/comparison.rs`, runs `sharp_refusal` for `==` on the element type against itself, and reports the refusal it gives (decision 3). `fetch_invocation_return_type` calls it for `contains`, `indexOf` and a `List`'s `remove`, as it calls `check_sorted_by_key` for `sortedBy`.
  - Bridge: `Lowering::element_search` lowers "the first index whose element `==` the value", the one search all three methods share. Its form comes from `Lowering::equality`, which `declared_operator` and `float_equality` become, so `a == b` and a search lower through one helper (decision 2).
- The new error, for `List<Line>` where `Line` declares no `operator ==`: "`contains` compares elements with `==`, and `Line` declares no `operator ==`." with the note "`==` on a class instance exists only where its class declares `operator ==`." and the help "Declare `operator ==` and `hash()` in `Line`, or test for the same object with `lines.any(l => l === line)`." A `List<List<int>>` gets "`contains` on `List<List<int>>` is not supported yet: `==` on a collection is not supported yet."

## `indexOf`

"`contains`, a `List`'s `remove`, `indexOf` … need `==` on the element type."

Missing. `app/IndexOf.sharp`, `public int position(List<string> tags) => tags.indexOf("vip");`, printed

```text
app/IndexOf.sharp:5:52:error - non-existent-method: Method `indexof` does not exist on `List<string>`.
app/IndexOf.sharp:5:47:error - mixed-return-statement: Could not infer a precise return type for method `IndexOf.position`. Saw type `Any?`.
```

- PHP it lowers to, with edge case 1's `null`:
  - strict: `(($index#1 = \array_search('vip', $tags, true)) === false ? null : $index#1)`
  - `operator ==`: `\array_find_key($lines, static fn ($element) => \App\Line::op_Equality($element, $line))`
- PHP's behavior, run: `array_search("z", ["a","b","a"], true)` is `false`, `array_search("a", …, true)` is `0`, and `array_find_key([], fn ($e) => true)` is `NULL`. `0` is falsy, so the strict form tests `=== false` through a hidden `$index#N`, the bridge's `$operand#N` precedent.
- Mechanism: prelude `ListMethods::indexOf(T $value): ?int`, `@mutation-free`; `("List", "indexOf")` in `INLINED_COLLECTION_METHODS`; `check_element_equality`; `Lowering::element_search`.

## `insert`

"`lines.insert(0, line);`" in the methods that change a collection in place.

Missing. `app/WriteMethods.sharp` holds `collection-write-methods`:

```csharp
namespace App;

class WriteMethods
{
    public void run()
    {
        List<Line> lines = [new Line()];
        Line line = new Line();
        Map<string, Plan> plans = [:];
        Plan pro = new Plan();
        lines.add(line);
        lines.insert(0, line);
        lines.set(0, line);
        lines.remove(line);
        lines.clear();
        plans["pro"] = pro;
        plans.remove("pro");
    }
}
```

printed

```text
app/WriteMethods.sharp:12:15:error - non-existent-method: Method `insert` does not exist on `List<Line>`.
app/WriteMethods.sharp:14:15:error - non-existent-method: Method `remove` does not exist on `List<Line>`.
app/WriteMethods.sharp:15:15:error - non-existent-method: Method `clear` does not exist on `List<Line>`.
app/WriteMethods.sharp:17:15:error - non-existent-method: Method `remove` does not exist on `Map<string, Plan>`.
```

- PHP it lowers to: `$lines->insert(0, $line)`, the ordinary method call `add` and `set` lower to. The engine runs it as `Sharp\Collection::insert`, which takes edge case 4's range, separates the array, puts the value at the index, renumbers, and writes back through a property's `set` with `sharp_collection_write_back`, as `Sharp\Collection::set` does.
- Why not PHP's `array_splice`: it takes the array by reference. Run: `array_splice($order->lines, 0, 0, ['x'])` on a property with a `set` hook threw `Error: Indirect modification of Order::$lines is not allowed`, and `$order->lines = []` ran the hook.
- Mechanism: prelude `ListMethods::insert(int $index, T $value): void`; `"insert"` joins `CHANGING_COLLECTION_METHODS`, so the binder records the call as a write to its local; `check_changed_collection` needs no change, because a method without `@mutation-free` already reaches it; engine `Sharp\Collection::insert` in `ext/sharp/sharp.stub.php`, `sharp_arginfo.h` (regenerated) and `sharp.c`.

## `remove` on a `List`

"`lines.remove(line); // by value: the first equal element, and true when one was removed`", and "On a `List`, `remove(value)` removes the first element equal to `value` by `==` (section 19), renumbers the list, and returns `bool`: true when it removed one."

Missing. `WriteMethods.sharp:14` above, and `app/NaturalRemove.sharp`, which holds `natural-remove`:

```csharp
namespace App;

class NaturalRemove
{
    public void run()
    {
        List<Line> lines = [new Line()];
        Map<string, Plan> plans = ["pro": new Plan()];
        List<string> tags = ["a", "b", "a"];
        const removed = tags.remove("a");
        const missing = tags.remove("z");
        List<Line> free = lines.filter(l => l.free);
        Map<string, Plan> active = plans.filter(p => p.active);
    }
}
```

printed

```text
app/NaturalRemove.sharp:10:30:error - non-existent-method: Method `remove` does not exist on `List<string>`.
app/NaturalRemove.sharp:11:30:error - non-existent-method: Method `remove` does not exist on `List<string>`.
app/NaturalRemove.sharp:13:36:error - invalid-local-assignment-value: Invalid assignment to `active`: it is declared as `Map<string, Plan>`.
```

- PHP it lowers to: `($index#1 = \array_search('a', $tags, true)) !== false && $tags->removeAt($index#1) === null`, and with `operator ==`, `\array_find_key(…)` tested `!== null`. This is the shape a `Set`'s `remove` has on master, `isset($tags['new']) && $tags->delete('new') === null`: an inline test, then a `Sharp\Collection` method that writes back.
- The engine runs `Sharp\Collection::removeAt(int $index): void`: it separates the array, removes the element, renumbers, and writes back. A `List` keeps `0..n-1`, so the engine's `delete`, which keeps the other keys, is not it, and deciding by the array's shape is what decision 026 rejects.
- Mechanism: prelude `ListMethods::remove(T $value): bool`; `("List", "remove")` in `INLINED_COLLECTION_METHODS`; `check_element_equality`; `Lowering::element_search`; `Lowering::collection_call`'s arms keyed by the collection and the method, since `remove` means three things; engine `Sharp\Collection::removeAt`.

## `clear`

"`lines.clear();`"

Missing (`WriteMethods.sharp:15` above).

- PHP it lowers to: `$lines = []`, the arm a `Set`'s `clear` has on master. A plain assignment runs a property's `set` hook (`php-hooks.php` above), and a receiver that lives nowhere is refused by `check_changed_collection`.
- Mechanism: prelude `ListMethods::clear(): void`; `("List", "clear")` in `INLINED_COLLECTION_METHODS`; `"clear"` is already in `CHANGING_COLLECTION_METHODS`.

## `last`

"the last item is `items.last()`, as in Kotlin."

Missing. `app/Tail.sharp`, `public int last(List<int> items) => items.last();`, printed

```text
app/Tail.sharp:5:47:error - non-existent-method: Method `last` does not exist on `List<int>`.
app/Tail.sharp:5:41:error - mixed-return-statement: Could not infer a precise return type for method `Tail.last`. Saw type `Any?`.
```

`app/IndexRules.sharp` holds `index-rules` whole, and `last` is its only error a line does not expect:

```text
app/IndexRules.sharp:15:9:error - invalid-array-access: A `List` is not written by index, because a write could leave a gap in its keys.
app/IndexRules.sharp:18:28:error - non-existent-method: Method `last` does not exist on `List<int>`.
app/IndexRules.sharp:19:22:error - mismatched-array-index: `List<int>` is indexed by `int`, but this index is `int|string`.
```

Lines 15 and 19 are `lines[0] = line;` and `items[id];`, which the spec annotates `compile error`.

- PHP it lowers to, with edge case 2's exception: `($items === [] ? throw new \OutOfRangeException('The list is empty.') : \array_last($items))`. The receiver is read twice, so one that runs code goes into a hidden `$receiver#N`, as `collection_receiver` holds a `Set`'s.
- PHP's behavior, run: `array_last([1, 2, 3])` is `3`, `array_last([])` is `NULL`, and `array_last([1, null])` is `NULL`. So `\array_last(…) ?? throw` would throw on a `List<int?>` whose last element is null, and the test is on the empty array instead. The run of the conditional itself threw `OutOfRangeException: The list is empty.`
- Mechanism: prelude `ListMethods::last(): mixed`, `@return T`, `@mutation-free`; `("List", "last")` in `INLINED_COLLECTION_METHODS`.

## `has`

"`map.has(key)` tells them apart, as Kotlin's `containsKey` does."

Missing. `app/NullableMapValues.sharp` holds `nullable-map-values`:

```csharp
namespace App;

class NullableMapValues
{
    public void run()
    {
        Map<string, int?> limits = ["pro": null];
        const stored = limits["pro"] ?? 0;
        const missing = limits["team"] ?? 0;
        const hasPro = limits.has("pro");
        const hasTeam = limits.has("team");
    }
}
```

printed

```text
app/NullableMapValues.sharp:10:31:error - non-existent-method: Method `has` does not exist on `Map<string, int?>`.
app/NullableMapValues.sharp:11:32:error - non-existent-method: Method `has` does not exist on `Map<string, int?>`.
```

- PHP it lowers to: `\array_key_exists('pro', $limits)`, and for a `Map<Status, int>`, `\array_key_exists($status->value, $counts)` through `Lowering::stored_key`.
- PHP's behavior, run: for `['pro' => null]`, `isset($limits["pro"])` is `false` and `array_key_exists("pro", $limits)` is `true`. So a `Set`'s `isset` test would read a stored null as missing, and `has` takes `\array_key_exists`. For `["5" => 1]`, `array_key_exists("5", $stock)` is `true`, so a numeric string key finds the int key PHP stored.
- Mechanism: prelude `MapMethods::has(K $key): bool`, `@mutation-free`; `("Map", "has")` in `INLINED_COLLECTION_METHODS`; the arm a `Set`'s `contains` has, testing with `\array_key_exists` for a `Map` and `isset` for a `Set`, whose stored values are never null.

## `keys`

"This covers the key of `for (const [key, value] of map)` and of `keys()`."

Missing, and refused on purpose. `app/MapKeys.sharp`, `public List<string> skus(Map<string, int> stock) => stock.keys();`, printed

```text
app/MapKeys.sharp:5:63:error - non-existent-method: Method `keys` does not exist on `Map<string, int>`.
app/MapKeys.sharp:5:57:error - mixed-return-statement: Could not infer a precise return type for method `MapKeys.skus`. Saw type `Any?`.
```

The test `a_map_has_no_keys_or_entries_method` in `crates/analyzer/tests/sharp.rs` keeps it out: "`keys()` and `entries()` would read a backed enum key back as its backing value, so a `Map` has neither until the loop's written key type reads it back as the case." Typed compilation made the loop read the key back with no type written, so `keys()` can read it back the same way.

- PHP it lowers to, by the `Map`'s key type, as `Lowering::key_read_back` reads a loop key:
  - `int`, or any other key type: `\array_keys($ids)`
  - `string`: `\array_map(\strval(...), \array_keys($stock))`
  - a backed enum: `\array_map(\App\Status::from(...), \array_keys($counts))`
- PHP's behavior, run: for `['5' => 1, 'sku-2' => 2]`, `array_keys` is `[5, 'sku-2']` and `array_map(strval(...), array_keys(…))` is `['5', 'sku-2']`.
- Mechanism: prelude `MapMethods::keys(): array`, `@return list<K>`, `@mutation-free`; `("Map", "keys")` in `INLINED_COLLECTION_METHODS`; `key_read_back` takes the key type and a lowered key, so a loop and `keys()` share it. `entries()` stays absent from `MapMethods`: the spec does not name it, and the test keeps that half.

## `remove` on a `Map`

"`plans.remove("pro"); // by key`", and "`remove` removes a value from a `List` and a key from a `Map`."

Missing (`WriteMethods.sharp:17` above). The interim `delete` compiles (below).

- PHP it lowers to, with edge case 3's `bool`: `\array_key_exists('pro', $plans) && $plans->delete('pro') === null`, a `Set`'s `remove` arm with `\array_key_exists` for `isset`, because a `Map` value may be null.
- Mechanism: prelude `MapMethods::remove(K $key): bool`; `("Map", "remove")` in `INLINED_COLLECTION_METHODS`; `"remove"` is already in `CHANGING_COLLECTION_METHODS`. The engine's `Sharp\Collection::delete` does the change, as it does for a `Set`.

## `filter` on a `Map`

"`filter` renumbers what a `List` keeps, and keeps a `Map`'s keys", and "`plans.filter(p => p.active); // Map<string, Plan>, keys kept`".

Silently accepted and wrong. `NaturalRemove.sharp:13` above refused `Map<string, Plan> active = plans.filter(…)` because the result is typed as a `List`. Written as that `List`, it compiles. `app/MapFilter.sharp` compiled:

```csharp
namespace App;

class MapFilter
{
    public List<Plan> active(Map<string, Plan> plans) => plans.filter(p => p.active);
}
```

`MapMethods::filter` declares `@return list<V>`. The bridge does not inline it, so it runs as `$plans->filter(…)`, and `ZEND_METHOD(Sharp_Collection, filter)` calls `sharp_collection_filter` with `keep_keys` false: "Spec section 12: filter renumbers what it keeps."

- PHP it lowers to: `$plans->filterValues(fn ($p) => $p->active)`, the arm a `Set`'s `filter` has on master. `sharp_collection_filter` with `keep_keys` true adds each kept entry under its own key.
- PHP's behavior, run: `array_filter(["pro" => 1, "team" => 0])` is `['pro' => 1]`, the result `filterValues` gives.
- Mechanism: prelude `MapMethods::filter` declares `@return array<K, V>`; `("Map", "filter")` in `INLINED_COLLECTION_METHODS`.

## The interim `delete` and `filterValues`

Silently accepted: the spec names neither. `app/Interim.sharp` compiled:

```csharp
namespace App;

class Interim
{
    public Map<string, Plan> active(Map<string, Plan> plans)
    {
        plans.delete("pro");
        return plans.filterValues(p => p.active);
    }
}
```

- Mechanism: delete both from `MapMethods`, and `"delete"` from `CHANGING_COLLECTION_METHODS` (decision 5). Each use will then get "Method `delete` does not exist on `Map<string, Plan>`."
- Uses to move, every one found by `trace grep "\.delete\(|filterValues"`: `Zend/tests/sharp/CollectionMethods.sharp` (2), `EnumKeys.sharp` (1), `Lambdas.sharp` (1) and `Roster.sharp` (2), each with its `.inc` twin; the `.sharp` sources in `crates/analyzer/tests/sharp.rs` (`map_get_and_delete_take_a_backed_enum_key_in_sharp_and_in_php` and `a_map_keyed_by_a_backed_enum_takes_the_case_in_every_key_position` among them) and `crates/sharp-bridge/tests/lower.rs`. `Sets.inc` writes the engine's `->delete` and `->filterValues` a `Set` lowers to, which stay.

## `groupBy` and `associateBy` with a backed enum key

"An inferred `Map`, such as a `groupBy` result, gives its keys back the same way", and `for (const [status, group] of orders.groupBy(o => o.status)) { … } // compiles`.

Refused. `app/EnumMapKeys.sharp` holds `enum-map-keys` with a minimal `Order` whose `status` is the example's `Status`. Its only errors are at the `groupBy` line:

```text
app/EnumMapKeys.sharp:49:54:error - template-constraint-violation: Argument type mismatch for template `G`.
app/EnumMapKeys.sharp:49:54:error - invalid-argument: Invalid argument type for argument #1 of `List<Order>.groupby`: expected `Function<G(Order)>`, but found `Function<Status(Any?)>`.
```

The rest of the example compiled with no error, the untyped `for (const [status, n] of stats.countByStatus(orders))` included.

- Today the engine would refuse the key too: `sharp_collection_key_slot` throws "A map key must be of type int|string, Status given" for an object.
- PHP it lowers to: unchanged, `$orders->groupBy(fn ($o) => $o->status)`. The engine's `sharp_collection_key_slot` stores a backed enum case under its backing value. A loop over the result reads each key back as `Status::from($key)` from the checker's type `Map<Status, List<Order>>`, as it reads `countByStatus`'s.
- Mechanism: analyzer and engine. `G of array-key` on `groupBy` and `associateBy` accepts a type with an `int` or `string` backing value, as `get_backing_key_type` already lets `Map<Status, int>` fill `K`. Engine: `sharp_collection_key_slot` reads `zend_enum_fetch_case_value` for a backed enum case.

## The rest of section 12's names

- **`first` throws `OutOfRangeException` when nothing matches.** Works: `ZEND_METHOD(Sharp_Collection, first)` throws "No element matches the predicate".
- **A `sortedBy` key compiles exactly when `<` compiles.** Works: `check_sorted_by_key`.
- **`Set<T>` needs `==` on `T`.** Works as a refusal: `report_unsupported_set_elements` refuses a `Set` of anything but an `int`, a `string` or a backed enum with "This type is not supported yet in PHP#."
- **`Iterable<T>`'s `filter`, `map`, `take` and `toList`.** Refused, by the type. `app/Lazy.sharp`, `public List<int> firstTen(Iterable<int> numbers) => numbers.filter(n => n > 0).map(n => n * 2).take(10).toList();`, printed `app/Lazy.sharp:5:31:error - semantics: This type is not supported yet in PHP#.` They are task #454's (Iterable lazy chains), not this Slice's.

# What a plain PHP caller sees

Every collection still is a PHP array, and every method runs in PHP#'s compiled code, so plain PHP sees only the values:

- A `List` after `insert` or `remove` is a list, `0..n-1`, as before.
- `keys()` of a `Map<string, int>` holding `"5"` gives `["5", "sku-2"]`, strings, as a PHP# caller expects. Plain PHP reading the same `Map` still sees the int key `5`.
- A `groupBy` result keyed by `Status` reaches plain PHP keyed by the backing values, as any `Map<Status, …>` does.
- `Sharp\Collection` gains the public methods `insert`, `removeAt`, `put` and `clear`. Plain PHP cannot make one, because its constructor is private.

# Public names this Slice adds or changes

team-lead approves these. Everything not in this tree is tactical.

```text
mago-sharp
├── crates/prelude/assets/extensions/sharp.php   <- the standard library's collection methods
│   ├── ListMethods                              <- gains contains(T): bool, indexOf(T): ?int, insert(int, T): void,
│   │                                               remove(T): bool, clear(): void, last(): T
│   └── MapMethods                               <- gains has(K): bool, keys(): list<K>, remove(K): bool;
│                                                   filter returns array<K, V>; delete and filterValues deleted
├── crates/names/src/lib.rs
│   ├── CHANGING_COLLECTION_METHODS              <- gains "insert", loses "delete"
│   └── INLINED_COLLECTION_METHODS               <- gains (List, contains), (List, indexOf), (List, last), (List, remove),
│                                                   (List, clear), (Map, has), (Map, keys), (Map, remove), (Map, filter);
│                                                   its doc says "lowered from the checker's type", since filter and
│                                                   remove call a Sharp\Collection method
├── crates/analyzer/src/expression/binary/comparison.rs
│                                                <- check_element_equality added, beside check_sorted_by_key;
│                                                   reuses invalid-argument and not-supported-yet, no new code
└── crates/sharp-bridge/src/lower.rs             <- Lowering::element_search and Lowering::equality added;
                                                    collection_call's arms keyed by (collection, method);
                                                    key_read_back takes a lowered key, for loops and keys();
                                                    element_write puts into a Map element of a List or of a
                                                    hooked property; changes_list_element keeps the index after ?.,
                                                    and null_safe_index marks it ZEND_DIM_IS; read_collection
                                                    tests an inlined ?. call's List index with ?? null

php-sharp
├── ext/sharp/sharp.stub.php, sharp_arginfo.h, sharp.c
│                                                <- Sharp\Collection::insert(int $index, mixed $value): void,
│                                                   removeAt(int $index): void, put(mixed $key, mixed $value): mixed
│                                                   and clear(): void added; element collections and
│                                                   sharp_collection_place reach a List element;
│                                                   sharp_collection_key_slot stores a backed enum case's value;
│                                                   sharp_collection_read_dimension gives null for a missing
│                                                   index under BP_VAR_IS
├── Zend/zend_compile.c                          <- zend_compile_sharp_receiver reads an index receiver through its
│                                                   container, with FETCH_DIM_IS for a ZEND_DIM_IS index;
│                                                   zend_delayed_compile_prop reads a written property's PHP# index
├── Zend/tests/sharp/Grid.sharp, Point.sharp, index_changes.{inc,phpt}, harness/RowSet.inc
│                                                <- every change through a List index (ruling 7)
├── Zend/tests/sharp/CollectionMethods.sharp, collection_methods.{inc,phpt}, collection_methods_jit_*.phpt
│                                                <- rows for every new method
├── Zend/tests/sharp/EnumKeys.sharp, EnumKeys.inc, enum_keys*.phpt
│                                                <- a groupBy keyed by a backed enum, read back as the case
├── Zend/tests/sharp/{CollectionMethods,EnumKeys,Lambdas,Roster}.{sharp,inc}
│                                                <- delete and filterValues moved to remove and filter
└── ext/sharp/sharp_unit.h                       <- the pinned Mago commit moves

the PHP the bridge emits
├── \in_array($v, $c, true)                                        <- contains, == as ===
├── \array_find_key($c, static fn ($e) => \C::op_Equality($e, $v))   <- the search, == as operator ==
├── ((($i = \array_search($v, $c, true)) === false) ? null : $i)    <- indexOf
├── ($i = …search…) !== false && $c->removeAt($i) === null          <- List remove
├── $c->insert($i, $v)                                              <- insert
├── $c = []                                                         <- clear
├── $c[$i]->clear(), $c[$i]->put($k, $v), $c[$i]->add($v)          <- a change through a List index
├── $c[$i]?->add($v)                                                <- a change after ?. on a List index, $i marked
│                                                                      ZEND_DIM_IS, so a missing index skips
├── ($c === [] ? throw new \OutOfRangeException('The list is empty.') : \array_last($c))   <- last
├── \array_key_exists($k, $c)                                       <- has
├── \array_keys($c), \array_map(\strval(...) | \E::from(...), \array_keys($c))   <- keys
├── \array_key_exists($k, $c) && $c->delete($k) === null            <- Map remove
└── $c->filterValues($f)                                            <- Map filter
```

Not added: no `sharp_kind`, no CST node, no parse error, no issue code, no crate. No new PHP# name outside section 12: `removeAt` is the engine's, and PHP# code calling it is `non-existent-method`, as calling `filterValues` on a `Set` is today.

The names, each with the slate it was picked from:

- `$tags->removeAt($index)`, the engine method: Kotlin's `removeAt`. Also considered: `$tags->deleteIndex($index)` (beside the engine's `delete`, which keeps the keys), `$tags->splice($index)` (PHP's renumbering function, but it also takes a length), `$tags->unsetPosition($index)` (PHP's `unset` does not renumber), `$tags->takeOut($index)`, `$tags->shiftDown($index)` (names the effect on later elements).
- `check_element_equality(context, call, element_type)`: the shape of `check_sorted_by_key`. Also considered: `refuse_incomparable_elements` (after `refuse_non_int_operands`), `check_search_by_value`, `check_searched_element`, `check_equatable_list` (Swift's word, not PHP#'s).
- `self.element_search(call, receiver, value)`: what the three methods share. Also considered: `self.index_of(…)` (repeats one PHP# method's name), `self.equal_element(…)`, `self.find_position(…)`, `self.matching_key(…)`.

# The PRs, in order

Each PR ends in something a developer runs. PR 1 is independent. PRs 2 and 3 follow PR 1, because all three change `Lowering::collection_call`'s arms. PR 4 needs PRs 1 to 3, php-sharp PR #99 (Guard 1) merged, and ruling 1's spec PR.

1. **mago-sharp: a `List`'s reads.** `contains`, `indexOf` and `last` in `ListMethods` and `INLINED_COLLECTION_METHODS`; `check_element_equality`; `Lowering::equality` taken out of `declared_operator` and `float_equality`; `Lowering::element_search`; `collection_call`'s arms keyed by the collection.
   - Run: `compile` compiles `Contains.sharp`, `IndexOf.sharp`, `Tail.sharp` and `IndexRules.sharp` but its two annotated lines, and refuses `ReadMethods.sharp`'s `lines.contains(line)` with "`contains` compares elements with `==`, and `Line` declares no `operator ==`."
2. **mago-sharp: a `List`'s changes.** `insert`, `remove` and `clear` in `ListMethods`; `"insert"` in `CHANGING_COLLECTION_METHODS`; `(List, remove)` and `(List, clear)` in `INLINED_COLLECTION_METHODS`; the `removeAt` lowering.
   - Run: `compile` compiles `NaturalRemove.sharp`'s `tags.remove` lines, and `WriteMethods.sharp` but `lines.remove(line)` and `plans.remove("pro")`.
3. **mago-sharp: a `Map`'s methods.** `has`, `keys` and `remove` in `MapMethods`; `filter` typed `array<K, V>` and lowered to `filterValues`; `delete` and `filterValues` deleted, with every test that used them; `key_read_back` shared with `keys()`; `groupBy` and `associateBy` keys with a backing value.
   - Run: `compile` compiles `NullableMapValues.sharp`, `MapKeys.sharp`, `NaturalRemove.sharp`, `EnumMapKeys.sharp` and `WriteMethods.sharp` but `lines.remove(line)`, and refuses `Interim.sharp` and `MapFilter.sharp` (the result is a `Map`, not a `List`).
4. **php-sharp: the engine runs them.** Bump the pinned Mago commit; `Sharp\Collection::insert`, `removeAt`, `put` and `clear`; every change through a `List` index (ruling 7); a backed enum key in `sharp_collection_key_slot`; the moved uses of `delete` and `filterValues`; the engine tests below; `sharp/baseline/spec` loses `collection-read-methods`, `collection-write-methods`, `index-changes`, `index-rules`, `natural-remove` and `nullable-map-values` through the `cp` command a run prints.
   - Run: `sharp/bin/test Zend/tests/sharp/collection_methods.phpt Zend/tests/sharp/enum_keys.phpt` passes, and `sharp/bin/test --spec` passes with the shorter list.

Each PR body states the Problem, the Solution and an annotated file tree. PRs 1 to 3 are mago-sharp's, where Guard 1 does not run, and carry no `Completes:` line. PR 4's line follows ruling 2.

# The tests

Each test proves one rule of section 12 and fails on a real bug in it, with its forms as rows. The existing test for each rule grows a row before a new test is added.

**mago-sharp, `crates/analyzer/tests/sharp.rs`:**

- `the_binder_knows_every_changing_collection_method` and `every_inlined_collection_method_is_a_method_of_its_collection` need no edit: they fail when the stub and the two tables disagree, which is how they catch a missing `insert` or a misspelled `indexOf`.
- `collection_methods_take_and_give_the_types_of_their_elements`: a row per new method's type, `indexOf` giving `int?`, `keys()` of a `Map<Status, int>` giving `List<Status>`, and a `Map`'s `filter` giving `Map<K, V>`.
- `a_changing_collection_method_needs_a_place_the_caller_can_write`: rows for `insert`, a `List`'s `remove` and `clear` on a value that lives nowhere.
- `a_changing_collection_method_may_run_on_a_property_with_a_set_hook`: the same three on a property.
- New, `a_search_needs_equality_on_the_element_type`: `contains`, `indexOf` and `remove` on a `List<Line>` without `operator ==` are refused with the message above, on a `List<List<int>>` are not supported yet, and on a `List<Money>` with `operator ==`, a `List<string>` and a `List<float>` given an `int` compile. A row compiles the help's `lines.any(l => l === line)`.
- `map_get_and_delete_take_a_backed_enum_key_in_sharp_and_in_php` becomes `map_get_has_and_remove_take_a_backed_enum_key_in_sharp_and_in_php`.
- `a_map_has_no_keys_or_entries_method` becomes `a_map_has_no_entries_method`, since its reason no longer holds for `keys()`.
- New, `group_by_and_associate_by_take_a_key_with_a_backing_value`: a `Status` key compiles and types `Map<Status, List<Order>>`, and a `Line` key gets the `Map` key error.

**mago-sharp, `crates/sharp-bridge/tests/lower.rs`:**

- `every_inlined_collection_method_lowers_to_php_on_the_array` takes each new pair, with an argument for each, and its `filter` row covers a `Map`.
- One test per lowering form: the strict search, the `op_Equality` search, the `(float)` argument, `indexOf`'s `null`, a `List`'s `remove` through `removeAt`, `last` with a receiver held in `$receiver#N`, `keys()` read back by each key type, and `has` and `remove` with a backed enum key.

**php-sharp, engine behavior run on the engine in all four opcache modes:**

- `CollectionMethods.sharp`, `collection_methods.inc`, `collection_methods.phpt` and its two JIT variants: each method on a local, on a parameter whose caller's copy stays unchanged, and on a property with a `set` accessor, which writes back through `set`; `insert` at `count()` appends and past it throws; `last()` of `[]` throws "The list is empty."; `remove` of an absent value is `false`; `indexOf` of an absent value is `null`; `contains` and `remove` on a `List<Money>` run `op_Equality`; `has` tells a stored null from a missing key; `keys()` gives `"5"`; a `Map`'s `filter` keeps its keys; `remove` by a backed enum key.
- `EnumKeys.sharp` and `EnumKeys.inc`: a `groupBy` keyed by `Status`, read back as `Status` in an untyped loop, and seen by plain PHP keyed by the backing values.
- `Grid.sharp`, `Point.sharp`, `index_changes.inc` and `index_changes.phpt`: each rule of ruling 7. That is `add`, `insert`, `remove`, `set` and `clear` through an index; a copy that shares the list left as it was; a nested index; a `Set`'s `add` and `remove`; a `Map` element's `remove`, write and `??=`; through a property, and through a hooked property, counting its `set` hook's runs, for `put` and `clear`; an index that runs code, once; a missing index for a method, a `clear`, a write and a property write, each `OutOfRangeException`, and a bare `rows[9].add(7)` on a property and a hooked property; after `?.` on a local, a property and a hooked property, a change that reaches the stored element, and a null element and a missing index that skip `add`, `clear`, `remove`, `contains` and `count` without running the `set` hook; and `map` on an element of a plain PHP `ArrayAccess` holder.

# What the Slice refuses as "not supported yet", and why

- **A search on a `List` of collections.** `contains`, `indexOf` and `remove` on a `List<List<int>>` refuse as `==` on a collection refuses today, "`==` on a collection is not supported yet." The equality Slice decides `==` on collections, and the search follows it through `Lowering::equality` with no change here.
- **A method on a value that may be a collection or an object, or two kinds of collection.** Unchanged: `report_collection_method_on_object` already refuses every pair in `INLINED_COLLECTION_METHODS`, so each new one inherits it.

# Found outside the Slice

- **`map-string-keys` fails for two reasons, neither a method.** `app/MapStringKeys.sharp`, the example with its setup line, printed `app/MapStringKeys.sharp:9:34:error - invalid-local-assignment-value: Invalid assignment to `stock`: it is declared as `Map<string, int>`.` for `Map<string, int> stock = ["5": 1, "sku-2": 2];`, so a literal's all-digit string key is typed as the int PHP stores. `sharp/bin/spec` puts the example's `void reserve(string sku, int count) { … }` in the class, and it printed `app/MapStringKeys.sharp:5:10:error - semantics: A method without `public`, `protected` or `private` is not supported yet in PHP#.` The first belongs to section 12's literal rules, and the second to section 5 or 7.
- **`iterables` and `yield`** need `Iterable<T>`, which is refused (`Lazy.sharp` above). They are task #454's.
- **`sharp/agents/architecture.md` still says "the `filterValues` and `delete` names on master stay as the interim forms"** until typed compilation. PR 3 makes that sentence false. Its owner deletes it when PR 3 lands.
