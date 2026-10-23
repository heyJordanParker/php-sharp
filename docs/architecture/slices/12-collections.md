# Slice 12: Collections

Spec section 12 (collections) and section 17's collection rules, with the section 5 rule one section 12 example needs and the section 30 rules the entries of a `Map` need. team-lead approved this design with the answers each question records.

**Superseded in part by [decision 83](../../decisions/083-php-through-the-standard-library.md):** `global.` does not exist in PHP# code, and a bare call of a PHP function or a bare PHP constant names nothing, as a misspelled name does. Every `global.` call in user code below is the error an unknown name gets, and every message that names a member to write in place of a PHP function is gone, with the checker's table of replacements (`REPLACEMENTS`, `WRAPPED_CLASSES`) and the functions that read it.

## The answer first

Most of section 12 works on master `c2aca5e564`. Values and copy-on-write, the crossings from plain PHP, `as` on a collection, literals and their kinds, spreads, a bare `List` read, the `Map` read rules, `Map` keys of every scalar, enum, struct and class kind, the plain PHP view of a `Map`, a literal landing in a `Set`, every collection method and the `==` rules of `contains`, `remove` and `indexOf` compile, refuse and run as the spec annotates. 16 of section 12's 21 examples pass, and 3 of section 17's 4.

Fifteen rules do not hold. Rules 1 to 4 and 10 are silently accepted or wrong, rule 15 has no spec line, and the rest are refused:

1. **A `List` read throws on a missing index only when nothing follows it.** `lines[0] is Line`, `lines[0] as Line` and `lines[0]?.name` read a missing index as null. Spec line 911 says it throws "whatever follows it".
2. **`items[5] ?? 0` is silently accepted** and gives `0`. The spec makes it a compile error that names `getOrNull`.
3. **A `List` variable passed where a `Set` is expected is silently accepted.** The callee turns it into a `Set`. The spec makes it a compile error that names `toSet()`.
4. **`for (const entry of plans)` gives the value, not the entry.** `Plan shown = entry;` compiles. The spec says `entry` is a `[string, Plan]` tuple, and section 30's tuples do not exist yet.
5. **`lines[0] = line` is refused:** "A `List` is not written by index, because a write could leave a gap in its keys."
6. **`getOrNull`, `toSet` and a `Map`'s `values` do not exist** (`non-existent-method`).
7. **A collection cannot key a `Map` or join a `Set`,** even when its elements have `==`. The refusal's list of key kinds leaves collections out, where section 19 gives them `==` and a hash.
8. **`Iterable<T>` does not exist:** "Cannot find type `Iterable`."
9. **Its lazy `filter`, `map`, `take` and `toList` do not exist.**
10. **A lazy plain PHP source is never checked.** The engine accepts any `Traversable` for an `Iterable<T>` without reading its elements (`php_sharp.h:169`).
11. **`yield value;` is refused:** "This expression is not supported yet in PHP#." Every `yield` rule follows from it.
12. **`yield ...other;` is a parse error,** and so is PHP's `yield from`, which the spec replaces with it.
13. **A method without an access modifier is refused** as "not supported yet". Section 5 makes it `private`, and `map-string-keys` writes one. The key rule of that example already holds.
14. **`line.parseCsv()` does not exist.** The `yield` example calls it, and no spec line declares it.
15. **`?? continue` in a loop's condition runs the condition again while the value stays null.** The spec has no line on it (the leftover from #131).

The Slice builds all of it in one PR across `sharp/mago/`, the engine and the standard library. Most of `Iterable`, its lazy operations and `yield` already exist on two unmerged branches, written before #102, #123 and #131. The Slice ports their parts and leaves the rest (decision 1). Rule 1 is built mostly by deleting the quiet `List` read that ruling 7 of `12-collection-methods.md` added (decision 3).

When the Slice lands, all six entries leave `sharp/baseline/spec`: §12 `index-rules`, `iterables`, `map-string-keys`, `set-variable-arguments` and `yield`, and §17 `map-values`. With question 1 answered as proposed, the §30 `tuples` entry leaves too, and the PR writes `Completes: §12, §17, §30`. Every error the spec run shows for `tuples` today is a tuple parse error, and a parse error hides the analysis of the rest of its file, so its other lines are unconfirmed until the parse is built.

## Questions team-lead answered

Each decides what a user writes. Each has its proposal and its answer, which the rest of this note follows.

1. **A `Map`'s entries are tuples, and tuples do not exist yet.** Spec lines "`for (const entry of plans) { … } // entry is a [string, Plan] tuple`", "A `Map<TKey, TValue>` is an `Iterable<[TKey, TValue]>` of its entries" and "A list literal passed where a `Set` or a tuple is expected becomes one". Section 30's tuple types, literals and unpacking are parse errors today (§30 `tuples` is listed). Refusing `for (const entry of plans)` until then is not possible: `for-of` passes on master, and `SPEC_GUARD` fails a pull request that lists an example the base checked.
   - **Proposed:** the Slice builds section 30 whole, because a tuple a user cannot unpack or read is no use. A tuple type `[Money, int]` or `[Money total, int count]` is mago's sealed list shape, shown in messages as written. A list literal becomes a tuple where a tuple is declared. `const [a, b] = t` unpacks it, and `t.total` reads a named element. At run time a tuple is a PHP list. A single loop variable over a `Map` is the tuple `[TKey, TValue]`, and a `Map` that lands in an `Iterable<T>` place gives its entries. The PR writes `Completes: §12, §17, §30`.
   - Build only the `Map` entry: a single loop variable is typed `[TKey, TValue]`, and every tuple type, literal and unpacking stays a parse error until a section 30 Slice. `entry` can then only be passed on.
   - A section 30 Slice lands first, and this PR waits for it.
   - **Answered: as proposed.** A tuple type is the CST's `TupleHint` of `TupleElementHint`s, and codex's `TList` of its element types with `tuple_names`. A local declaration's `target` is a `LocalTarget`, a name or a `LocalUnpack`. A PHP# `Iterable<T>` is a `TIterable` with `holds_map_entries`, so a `Map` held as one gives `[TKey, TValue]`. The engine reads the type text `[A, B]`: a PHP list of exactly that length whose elements hold their types.
2. **`List.get(i)` and `List.set(i, v)` stay as second names.** Spec lines "`x.getOrNull(i)` is the safe read" and "`x[i] = v` on a `List` replaces the element at `i`". Master has `get` and `set` on `List` from the old spec, which the spec no longer names anywhere. Section 12's other uses of `.get(` are on a `Map`.
   - **Proposed:** both are deleted, with no alias, as decision 5 of `12-collection-methods.md` deleted a `Map`'s interim `delete` and `filterValues`. Every use moves to `getOrNull` and the index write: the `.get(` and `.set(` calls of 10 engine test sources (21 calls, a `Map`'s `get` among them), and the mago tests. A `Map`'s `get` stays.
   - Keep both beside the spec's forms.
   - **Answered: as proposed.**
3. **`items[i] ?? fallback` where the element type is nullable.** Spec line "`x[i] ?? fallback` is a compile error that names `getOrNull`".
   - **Proposed:** a compile error for every element type, because the `??` reads as a guard against a missing index, which it never is. The help writes `items.getOrNull(i) ?? fallback`, which covers a missing index and a stored null alike.
   - Allowed on a `List<T?>`, where `??` handles a stored null, as Kotlin's `list[i] ?: 0` compiles on a `List<Int?>` and still throws on a missing index.
   - **Answered: as proposed,** since the spec line covers every element type.
4. **`?? continue` in a loop's own condition.** Spec line "`a ?? throw …`, `a ?? return`, `a ?? continue` and `a ?? break`: leave when `a` is null." Answer 2 of #131 lets it stand wherever `?? throw` stands. Observed: `while (this.next() ?? continue) { … }`, `do { … } while (this.next() ?? continue);` and `for (let i = 0; this.next() ?? continue; i++) { … }` each called `next()` 5 times for 2 passes, while `next()` gave null 3 times. So a source that stays null spins forever. `while (this.next() ?? break)` ended the loop at the first null.
   - **Proposed:** a compile error in the condition of the loop it would continue: "`?? continue` in a loop's condition runs the condition again until the value is not null: use `?? break` to end the loop, or test the value in the body." Section 17 gains that line. Rust refuses an unlabeled `continue` in a `while` condition (E0590). `?? break` there stays allowed.
   - Keep it, and section 17 gains a line that says it runs the condition again.
   - **Answered: keep it.** Section 14.4 lets `?? continue` stand wherever `?? throw` stands, and it skips to the loop's next check, as `continue` does anywhere in the loop. Section 17 gains that line and the `continue-in-condition` example, with a row for `while`, `do` and `for`.
5. **`is`, `as` and `match` on an `Iterable<T>`.** Spec lines "`as` to a collection type checks every element too" (section 12) and section 21's type tests. A lazy source can be checked only by reading it, and the engine's `sharp_type_holds` holds any `Traversable` for `Iterable<Order>` without reading it.
   - **Proposed:** `value is Iterable<Any?>` tests that the value is iterable. A test of `Iterable<T>` for any other `T` is a compile error: "`is Iterable<Order>` would read the source to check its elements: test `is Iterable<Any?>`, or hold it as an `Iterable<Order>`, which checks each element as it is read."
   - True for any `Traversable`, with an array's elements checked, as the engine does today.
   - **Answered: a PHP# `Iterable` carries its element type,** as every PHP# generic does, following #131's "a plain PHP closure is never a `Function<…>`". `is Iterable<Order>` reads the carried type and no element, as C#'s reified `is IEnumerable<Order>` does: a PHP# generator's `Iterable<T>`, a check stage's `T`, a map stage's lambda return type, and every other stage its source's. A plain PHP `Traversable` is only an `Iterable<Any?>`. A collection is checked whole.
6. **`string.parseCsv()`, which the `yield` example calls.** Spec line "`yield line.parseCsv(); // compiles: each row is a List<string>`" and "The complete method list is specified with the standard library."
   - **Proposed:** the standard library gains `public List<string> parseCsv()` in `Sharp.Text.Text`'s `extension(string text)` block, beside `trim()`. It runs `global.str_getcsv(text, ",", "\"", "")`, and a blank line gives `[""]`, because PHP gives `[null]` there.
   - A harness extension only, so the standard library gains no name the spec does not list.
   - **Answered: as proposed.**
7. **How far section 5's `private` default reaches in this Slice.** Spec line "`private`: this class. This is the default for members outside an interface." `map-string-keys` writes `void reserve(string sku, int count) { … }`. The checker refuses a method, a property, a constant and a named constructor without a modifier as "not supported yet".
   - **Proposed:** methods only, the one kind section 12 needs. PR #132 changes the property refusal, and §24 `naming-cases` holds the constant one.
   - Every member kind in one rule, since the spec states it once. It conflicts with #132 in `is_slice_property`.
   - **Answered: methods only.** #132 owns properties, and §24 `naming-cases` owns constants.

## Decisions team-lead approves

1. **The Slice ports the two `feat/slice-lazy-and-types` branches, and merges neither.** mago-sharp `feat/slice-lazy-and-types` (5 commits) and php-sharp `feat/slice-lazy-and-types` (2 commits) predate #102, #123 and #131. A preview merge of the mago-sharp one into `sharp/mago` (`git merge-tree -X subtree=sharp/mago master mago-sharp/feat/slice-lazy-and-types`) conflicts in 10 files. Both also carry `Object` (section 24) and `typeof(value)`, which section 25's `Class.of` replaced. The Slice takes, with their tests:
   - `Yield::Spread(YieldSpread)` and its parse of `yield ...expr` in a `.sharp` file only, `check_yield` and `check_iterator_returns`, the analyzer's naming of `yield ...` in its spread messages, and the bridge's `YIELD`, the loop of `YIELD`s that `yield ...other;` runs as, so each element takes the generator's next automatic key, and the generator flag.
   - The `Iterable<T>` type hint, `Sharp\IterableMethods`, and the resolver's rule that a place declared `Iterable<T>` has only the lazy operations.
   - The engine's `Sharp\Sequence` and its `sequence.phpt` tests, and `Import.sharp` with `yield.phpt`.
2. **`x[i] = v` on a `List` lowers to the engine's `Sharp\Collection::set`,** through `Lowering::element_write`, which already lowers a write into a `Map` element of a `List` or of a hooked property to `put`. So a write reaches a local, a property, a hooked property's `set` and a `List` element, and throws `OutOfRangeException` past the end, as `lines.set(0, line)` does on master ("Undefined array key 0"). `x[i] op= v`, `x[i]++` and `x[i]--` lower to `$x->set($i, $x[$i] op v)` with the index read once, which the refusal's own help writes today.
3. **A `List` index never reads quietly.** Ruling 7.6 of `12-collection-methods.md` built a quiet read before `?.`, from a spec line the round 7 rulings removed. The Slice deletes it:
   - the `ZEND_DIM_IS` mark the bridge's `null_safe_object` and `read_collection` put on a `List` index,
   - `zend_compile_sharp_receiver`'s and `zend_delayed_compile_dim`'s `ZEND_DIM_IS` arms for a PHP# index, and `sharp_collection_read_dimension`'s `BP_VAR_IS` read,
   - `analyze_member_object`'s nullable type for the `List` index before `?.`.
   - `php_shape`'s `tested` keeps `x[k] ?? null` for a `Map` index only, so `is`, `as` and `match` read a `List` index bare.
   - So `rows[0]?.add(5)` on a `List<List<int>>` becomes section 14.4's needless null check, a compile error, and on a `List<List<int>?>` it skips a null element and throws on a missing index. A `Map` read keeps every quiet form.
4. **`getOrNull` and `toSet` are inlined, and `values` runs on the engine.** `("List", "getOrNull")` and `("List", "toSet")` join `INLINED_COLLECTION_METHODS`, as `last` is there. `getOrNull(i)` lowers to `($list[$i] ?? null)`, and `toSet()` to `new \Sharp\Set($list)`, the form a `Set` literal takes. A `Map`'s `values()` runs on `Sharp\Collection::values`, beside `keys`, which `sharp_collection_keys` gives.
5. **A `Set` place takes a `List` only as a literal.** `verify_argument_type` widens a `Set` parameter to a `List` of its elements (`arguments.rs:377`). It will do so only for a list literal argument. Every place that expects a `Set` and gets a `List` variable, an argument, a local, a return or a property, is the compile error "A `List` is not a `Set`: write `picked.toSet()`." Plain PHP may still pass a list to a PHP# `Set` parameter, which the method makes a `Set` before its body runs.
6. **A collection keys a `Map` and joins a `Set` when its elements have `==`,** as section 19 says. The checker's key rule accepts a `List`, `Map` or `Set` whose every type argument has `==` and `hash()`, and its message names them. The engine needs no change: `sharp_hash` already hashes an array by its elements in order and a `Map` or `Set` by its entries in any order, and `sharp_equal` compares them (`sharp_table.c`, lines 309 to 337 and `sharp_equal_arrays`), for a collection inside a struct key. A stored `Map` or `Set` key is shared, so a later write to the original separates the writer, and the key never changes.
7. **A lazy plain PHP source is checked as it is read.** Every crossing runs through `sharp_type_accepts`, which already turns an `int` into a `float` in place. Its `Iterable` arm will put a `Sharp\Sequence` that checks each element against `T` in place of a `Traversable`, so a bad element throws `TypeError` at the loop that reads it. An array, a `Sharp\Set` and a `Sharp\Map` are checked whole, and a `Sharp\Map` is then read through a `Sharp\Sequence` of its entries.
8. **Section 5's default reaches methods** (question 7). `scan_method` gives a `.sharp` method without a modifier `Visibility::Private`, as `scan_properties` does (`property.rs:241`). The bridge gives it `ZEND_ACC_PRIVATE`, as `Lowering::property` does (`lower.rs:2699`). `is_slice_method` loses its refusal, and `Lowering::method` its `unreachable!`.
9. **`=>` in a literal fails as a typo does,** at the `=>`.
10. **Decisions 026 and 033 match the spec.** 026 still says `lines[0] = line` is a compile error, names `set`, and has a `Map` loop give values. 033 still says a `Map` is an `Iterable<TValue>`.

One new engine class, `Sharp\Sequence`. No new issue code, no new crate.

## Edge cases the spec leaves open

Each is a proposal with its precedent. team-lead rules. The note writes the proposal everywhere below.

1. **`items[i] ??= v` on a `List` is the compile error of question 3,** because it reads `items[i] ?? …` first.
2. **A method that yields cannot `return value;`** and ends with `return;`, as C#'s CS1622 says. The ported `check_iterator_returns` refuses it.
3. **`toList()` numbers from 0,** whatever keys the source gives, as `Sharp\Sequence` does.
4. **A lazy element that is wrong throws** `TypeError: …: Return value must be of type Iterable<App.Order>, …: [3] expected App.Order, got string 'x'` at the read, worded by `sharp_first_problem` as every crossing is.
5. **`yield ...other` over a `Map` yields its entries,** as a loop over it does (question 1).

## Existing work, and how the Slice reuses it

Every open and closed PR of php-sharp and mago-sharp, and every branch of both, was searched by title and branch name for collections, `yield`, iterables, variadics, lazy, `Set`, `Map`, spreads, `List`, sequences and indexes (`gh pr list --state all`, `gh api …/branches`).

- **mago-sharp `feat/slice-lazy-and-types` and php-sharp `feat/slice-lazy-and-types`, unmerged.** `yield`, `Object`, `typeof(value)`, `Iterable<T>`, its lazy operations and `Sharp\Sequence`. Decision 1 says what the Slice takes.
- **php-sharp #114 (`feat/collection-engine`) and mago-sharp #160, #163, #166 and #176, merged.** Every collection method, the `List` index place, and ruling 7.6's quiet read before `?.`. The Slice keeps the methods and the place, and deletes the quiet read (decision 3).
- **php-sharp #123 (`feat/slice-structs`), merged.** `Sharp\Map`, `Sharp\Set`, their table, `Sharp\Map::values()` and the struct key hash. Decisions 4 and 6 build on them. Its "Order and dependencies" left task #539's `List` index write, `getOrNull` and the literal rule to this Slice.
- **php-sharp #102 (`feat/slice-generics`), merged.** `sharp_type_accepts` with its `SHARP_BUILT_IN_ITERABLE` arm, and the crossing checks every element check uses. Decision 7 builds on them.
- **php-sharp #131 (`feat/pattern-matching`), merged.** `php_shape`, list patterns, `is List<T>`, and `?? continue` (question 4).
- **php-sharp #128 (`feat/readonly`) and #115 (`feat/extensions`), merged.** Readonly collections and `string.trim()`, the precedent of question 6.
- **mago-sharp #16 and php-sharp #46 (`feat/slice-signatures`), merged.** Variadics and spreads into a call, which hold today.
- **php-sharp #132 (`feat/properties`), open.** It keeps every "without `public`, `protected` or `private`" refusal and edits `is_slice_property` (question 7).
- **No branch holds** the `List` index write, `getOrNull`, `toSet`, `values()` on a `Map`, collection keys, or tuples.

## How the states were observed

Every state below comes from a release `mago` and a debug engine built in this worktree from master `c2aca5e564`, with `git status` clean:

```sh
CARGO_INCREMENTAL=0 CARGO_PROFILE_RELEASE_LTO=off rustup run 1.97.0 cargo build --quiet --locked --release -j 2 \
  --manifest-path sharp/mago/Cargo.toml --target-dir sharp/build/darwin-arm64/mago --bin mago
CARGO_BUILD_JOBS=2 sharp/bin/build
TEST_MAGO_EXECUTABLE=sharp/build/darwin-arm64/mago/release/mago sharp/build/darwin-arm64/sapi/cli/php sharp/bin/spec
```

The spec run ended "Checked 192 examples, ignored 1, and 43 fail.", matching `sharp/baseline/spec`. Its section 12 and 17 lines are quoted under each rule.

Every rule was probed in one scratch project, `/private/tmp/claude-501/-Users-jordan-Developer-php-sharp/345d8519-d589-441b-947d-8f4fe33a0768/scratchpad/ws`, a copy of a spec run's project with the harness, one probe class per rule group under `app/Probe/`, and a plain PHP `Show` that prints with `var_export`. The groups are `Literals`, `Arrow`, `Spreads`, `Indexing`, `QuietReads`, `Places`, `StructPlace`, `Landing`, `Tuples`, `Crossings`, `MapKeys`, `CollectionKeys`, `Values`, `Methods`, `Effects`, `Iterables`, `Yields`, `YieldSpread`, `YieldFrom`, `FileRows`, `Loops`, `EntryType`, `LetTyped`, `ForIn`, `Foreach` and `LeaveInCondition`. `mago compile` checked them, and the engine ran each through `probe.php`, which loads the spec run's class map. `opcache.opt_debug_level=0x10000` printed the opcodes quoted under rule C17. Each message and value quoted below is that run's output.

# Every rule of sections 12 and 17, its state and its mechanism

The states are **works**, **refused** (an error that names the feature as missing, or a parse error), **silently accepted** (code the spec refuses compiles), **wrong** (it compiles and runs differently from the spec) and **wrong error** (refused, but not for the spec's reason). The mechanism is what the Slice changes: **checker** is `sharp/mago/` up to the analyzer, **bridge** is `crates/sharp-bridge`, **engine** is `ext/sharp` and `Zend/`, **library** is `sharp/composer/library`. "none" means the rule holds on master and the Slice only keeps it under test.

## Section 12

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| C1 | three collection types, `List<T>`, `Map<TKey, TValue>`, `Set<T>` | works | none | |
| C2 | collections are values; assigning or passing copies only when one side writes | works: `copy-on-write` passes; a `Map` copy gave `[1, 2]` counts, a `Set` copy `[1, 2]` | none | `sharp_separate` |
| C3 | a method that changes a collection it was given changes its own copy | works: `changed-copy-returned` passes; `Values.bump` left the caller's `a` at 1 | none | |
| C4 | a collection in a property changes through its `set`; a local copy changes only the local | works: `property-collections` passes; `propertyCopy` gave `[0, 1]` | none | |
| C5 | element types are checked where a collection enters from plain PHP: a plain PHP caller, a typed assignment of plain PHP's return, plain PHP writing a property; `TypeError` names the element | works for `List`, `Map` and `Set` (below); `Iterable` is C44 | none | `sharp_type_accepts` |
| C6 | an `int` where PHP# expects a `float` becomes a `float` | works: plain PHP's `[1, 2]` arrived as `[1.0, 2.0]` | none | |
| C7 | a call from PHP# to PHP# checks nothing at run time | works: `element-checks` passes | none | |
| C8 | `as` to a collection type checks every element, null if any is wrong | works: `[new Line(), "x"] as List<Line>` gave `NULL` | none | |
| C9 | literals: `[a, b]` a `List`, `["pro": pro]` a `Map`, `[:]` an empty `Map`, a declared `Set` | works: `collection-literals` passes | none | |
| C10 | PHP's `["key" => value]` is not used | fails as a typo does, at the `=>` | parser (decision 9) | |
| C11 | an empty literal with no declared type is a compile error | works: "An empty literal needs a type: write `List<T> messages = []`." | none | |
| C12 | a literal of the other collection is a compile error at a declaration, an assignment, a default, a return or an argument | works at all five: "`[]` is an empty List. An empty Map is written `[:]`." | none | |
| C13 | a `List` spread appends in order | works: `[...open, ...closed]` gave `[1, 2, 3]` | none | |
| C14 | a `Map` spread copies entries, a later key replaces, no key is renumbered | works: `[...defaults, ...overrides]` kept `"5"` and `"6"` as strings and took `pro` 30 | none | |
| C15 | a literal cannot spread a `List` and a `Map` together | works: "A literal cannot hold a `List`'s values and a `Map`'s entries together." | none | |
| C16 | only a `List` spreads into a call | works: a `Map` and a `Set` get "PHP# spreads only a list." | none | |
| C17 | a `List` read throws `OutOfRangeException` on a missing index, whatever follows it | wrong: a bare read throws; `is`, `as` and `?.` read null | checker, bridge, engine (decision 3) | deletion of ruling 7.6 |
| C18 | `x.getOrNull(i)` is the safe read | refused: "Method `getornull` does not exist on `List<int>`." | checker, bridge (decision 4) | `INLINED_COLLECTION_METHODS` |
| C19 | `x[i] ?? fallback` is a compile error that names `getOrNull` | silently accepted: `items[5] ?? 0` gave `0` | checker | `check_sharp_map_read`'s place |
| C20 | a `Map` read gives `TValue?` | works: `map-reads` passes | none | |
| C21 | `x[i] = v` on a `List` replaces, and throws past the end; appending is `add` | refused: "A `List` is not written by index, because a write could leave a gap in its keys." | checker, bridge (decision 2) | `Sharp\Collection::set`, `element_write` |
| C22 | `x[key] = v` on a `Map` inserts or replaces | works | none | |
| C23 | a `List` index is an `int`; a wider one is a compile error; `items[-1]` throws; `last()` | works: `int\|string`, `Any`, `string` and `"0"` each get "`List<int>` is indexed by `int`, but this index is …"; `items[-1]` threw "Undefined array key -1"; `last()` gave 3 | none | |
| C24 | a `List` index is a place: `grid[0].add(5)` changes the stored list | works: `grid[0].count()` gave 3 | none | |
| C25 | a struct's fields never change, so `points[0].x = 5` stays a compile error | works: `users[0].value = "b"` got "A struct never changes; …" | none | |
| C26 | a change through a `Map` index is a compile error | works: `index-changes` passes | none | |
| C27 | a `Map` read used as a `TValue` is a compile error naming `??` and `get` | works: `map-reads` passes | none | |
| C28 | a `Map` with nullable values; `has` tells a stored null from a missing key | works: `nullable-map-values` passes | none | |
| C29 | a key read back from a `Map<string, TValue>` is a `string`, in a loop and in `keys()` | works: `"5"` arrived as `string '5'` both ways; `map-string-keys` fails only on rule 13 | checker, bridge (decision 8) | the property default's two lines |
| C30 | anything with `==` and `hash()` keys a `Map` and joins a `Set` | wrong error for a collection: `Set<List<int>>` and `Map<List<int>, int>` get "A `Set`'s element is anything with `==` and `hash()`: an `int`, …, a struct, or a class that declares `operator ==`." | checker (decision 6) | `sharp_hash`, `sharp_equal` |
| C31 | a loop over a `Map` gives each key as the key type; a written type is allowed; an inferred `Map` too | works: `Status::Paid` with and without a type, and through `groupBy` | none | |
| C32 | plain PHP gets a PHP array wherever it gets a `Map` or a `Set`, except where only an object fits; a `Map` whose key has no PHP array form stays the `Map` and is a compile error into an `array` parameter; a `Map` two of whose keys would be one PHP array key throws where it crosses; `count()` | works on master as this row first read it: a `mixed` parameter got `Sharp\Map`; `Map<Status, int>` to `array` got "… a PHP array's keys are `int` or `string`, and this `Map`'s keys are `Status`." The slice collections-to-plain-php changed the rule to the one this row now reads | none here; collections-to-plain-php | |
| C33 | a list literal passed where a `Set` or a tuple is expected becomes one | works for a `Set`: `["vip", "new", "vip"]` arrived with 2 elements; a tuple type is a parse error, "A PHP# parameter needs a type, as in `int extra`." | checker, bridge (question 1) | |
| C34 | a `List` variable passed there is a compile error, and where a `Set` is expected it names `toSet()` | silently accepted as an argument (`Landing.apply(picked)` ran); refused without `toSet()` as a local, a return and a property; `toSet` does not exist | checker, bridge (decisions 4, 5) | `new \Sharp\Set` |
| C35 | the read methods; `first` throws; a `sortedBy` key has `<`; a method that takes a function has its effects | works: `first` threw "No element matches the predicate"; `sortedBy(l => l.product)` refused; a getter mapping through plain PHP got "Getters must be pure." | none | |
| C36 | the changing methods change in place | works: `collection-write-methods` passes | none | |
| C37 | each collection does the natural thing with `remove` and `filter` | works: `natural-remove` passes | none | |
| C38 | `contains`, a `List`'s `remove`, `indexOf` and `Set<T>` need `==` on the element type | works for a class: "`contains` compares elements with `==`, and `Plan` declares no `operator ==`."; a collection element is C30 | none, and C30 | |
| C39 | `Iterable<T>` is anything a loop can read and compiles to PHP's `iterable`; `List<T>` and `Set<T>` are `Iterable<T>` | refused: "Cannot find type `Iterable`." | checker, bridge (decision 1) | the lazy branch's type hint |
| C40 | a `Map<TKey, TValue>` is an `Iterable<[TKey, TValue]>` of its entries | refused with C39, and needs tuples | checker, bridge, engine (question 1) | |
| C41 | `values()` gives a `Map`'s values | refused: "Method `values` does not exist on `Map<string, Plan>`." | checker, engine (decision 4) | `sharp_collection_keys` |
| C42 | an `Iterable` has no index | refused with C39 | checker | |
| C43 | `filter`, `map` and `take` return an `Iterable<T>` and run only when `toList()` or a loop reads | refused with C39 | checker, bridge, engine (decision 1) | `Sharp\IterableMethods`, `Sharp\Sequence` |
| C44 | a lazy plain PHP source is checked element by element as it is read | wrong: any `Traversable` is accepted unchecked | engine (decision 7) | `sharp_type_accepts` |
| C45 | the lazy operations belong to `Iterable<T>`; a plain PHP class keeps its own methods until held as one | refused with C39 | checker | the lazy branch's resolver |
| C46 | `yield value;` in a method that returns `Iterable<T>` | refused: "This expression is not supported yet in PHP#." | checker, bridge (decision 1) | `check_yield` |
| C47 | `yield ...other;` | refused: parse error "Expected one of `Semicolon`, `CloseTag`, found `DotDotDot`" | checker, bridge (decision 1) | `Yield::Spread` |
| C48 | each yielded value is checked against `T` | refused with C46 | checker | `analyze` of `yield` |
| C49 | `yield ...other;` replaces PHP's `yield from`; `other` is an `Iterable<T>` | wrong error: `yield from` is "Parse error encountered during parsing" | checker | `check_yield`'s named refusal |
| C50 | the body runs only when a loop or `toList()` reads the result | refused with C46 | bridge | PHP's generators |
| C51 | a method with `yield` compiles to a PHP generator; `yield` gives no value back | refused with C46: `const sent = yield 1;` gets the same refusal | checker, bridge | `check_yield` |
| C52 | `yield` needs a method that returns `Iterable<T>` | wrong error: `List<int> numbers() { yield 1; }` gets "not supported yet" | checker | `check_yield` |
| C53 | `line.parseCsv()`, which the `yield` example calls | refused: "Attempting to access a method on a non-object type (`string`)." | library (question 6) | `string.trim()` |

## Section 17's collection rules

| Rule | Spec line | State on master | Mechanism | Reuses |
|---|---|---|---|---|
| L1 | `for … of` loops over a collection or any `Iterable<T>` | works over collections and plain PHP's `File.lines`; `Iterable<T>` is C39 | with C39 | |
| L2 | the loop variable is `const` or `let`, and each pass gets a fresh one | works: two lambdas made in two passes gave `Coffee` and `Tea` | none | |
| L3 | `for (const [key, plan] of plans)` | works: `key` was `string 'pro'` | none | |
| L4 | `for (const entry of plans)`: `entry` is a `[string, Plan]` tuple | wrong: `Plan shown = entry;` compiles, and `entry` ran as the `App\Plan` | checker, bridge (question 1) | |
| L5 | `for (const [i, line] of lines.entries())` | works: `i` was `int 0` | none | |
| L6 | `for (const plan of plans.values())` | refused: C41 | with C41 | |
| L7 | the key arrives as the key type, and writing it stays allowed | works: C31 | none | |
| L8 | a `const` loop variable may have its type written | works; `[string key, int n]` over a `Map<Status, int>` and `Plan line` over a `List<Line>` are refused | none | |
| L9 | `let` with a written type is a parse error | works: "Parse error encountered during parsing" | none | |
| L10 | a written `string` key over a `Map<string, TValue>` reads back as a `string` | works: `"5"` arrived as `string '5'` | none | |
| L11 | `for (x in y)` is a parse error | works: fails as a typo does, at the `in` | none | |
| L12 | `while`, `do … while` and C's `for` keep their form | works | none | |
| L13 | PHP's `foreach` is removed | works: fails as a typo does | none | |
| L14 | every body is written in braces | works: `braces` passes | none | |
| L15 | (no line) `?? continue` in a loop's condition | runs the condition again while the value is null | a spec line (question 4) | the `Leave` check of #131 |

## The six failing examples

`sharp/bin/spec` on master printed:

```text
FAIL §12 index-rules (docs/spec.md:925)
  docs/spec.md:927 expects no error, and the checker reports: A `List` is not written by index, because a write could leave a gap in its keys.
  docs/spec.md:931 expects no error, and the checker reports: Method `getornull` does not exist on `List<int>`.
  docs/spec.md:932 expects a compile error, and the checker accepts it
FAIL §12 map-string-keys (docs/spec.md:984)
  docs/spec.md:985 expects no error, and the checker reports: A method without `public`, `protected` or `private` is not supported yet in PHP#.
FAIL §12 set-variable-arguments (docs/spec.md:1064)
  docs/spec.md:1066 expects a compile error, and the checker accepts it
FAIL §12 iterables (docs/spec.md:1135)
  docs/spec.md:1138 expects no error, and the checker reports: Cannot find type `Iterable`.
  docs/spec.md:1147 expects no error, and the checker reports: Cannot find type `Iterable`.
  docs/spec.md:1149 expects no error, and the checker reports: Attempting to access a property on a non-object type (`Any?`).
  docs/spec.md:1150 expects no error, and the checker reports: Attempting to access a property on a non-object type (`Any?`).
  docs/spec.md:1158 expects no error, and the checker reports: Cannot find type `Iterable`.
  docs/spec.md:1159 expects a compile error, and the checker accepts it
FAIL §12 yield (docs/spec.md:1171)
  docs/spec.md:1187 expects no error, and the checker reports: Parse error encountered during parsing Expected one of `Semicolon`, `CloseTag`, found `DotDotDot`
  docs/spec.md:1195 expects a compile error, and the checker accepts it
  docs/spec.md:1196 expects a compile error, and the checker accepts it
FAIL §17 map-values (docs/spec.md:1792)
  docs/spec.md:1793 expects no error, and the checker reports: Method `values` does not exist on `Map<string, Plan>`.
```

- `index-rules` needs C18, C19 and C21.
- `map-string-keys` needs rule 13 (C29).
- `set-variable-arguments` needs C34.
- `iterables` needs C39, C42, C43 and C45.
- `yield` needs C46 to C53. Its parse error hides every other line, so lines 1195 and 1196 show as accepted.
- `map-values` needs C41.

## C17: what a `List` read after `is`, `as` and `?.` runs

`QuietReads.sharp` holds `lines[0] is Line`, `lines[0] as Line`, `lines[0]?.name` on a `List<Line?>`, and the bare `lines[0].name`. The engine compiled them as:

```text
tested:  T2 = FETCH_DIM_IS CV0($lines) int(0)   T3 = COALESCE T2 0005   T3 = QM_ASSIGN null   … INSTANCEOF T4 string("App\\Line")
cast:    T2 = FETCH_DIM_IS CV0($lines) int(0)   T3 = COALESCE T2 0005   T3 = QM_ASSIGN null   … INSTANCEOF
safe:    T1 = FETCH_DIM_IS CV0($lines) int(0)   T2 = JMP_NULL T1 0005   T2 = FETCH_OBJ_R T1 string("name")
bare:    T1 = FETCH_DIM_R CV0($lines) int(0)    T2 = FETCH_OBJ_R T1 string("name")
```

On an empty `List`, `Indexing.sharp` ran `lines[0]` to "OutOfRangeException: Undefined array key 0", and `lines[0] is Line`, `lines[0]?.name` and `lines[0] as Line` with no throw. The `COALESCE` comes from `php_shape`'s `tested`, which wraps any index in `?? null`, and the `?.` read from `null_safe_object`'s `ZEND_DIM_IS`.

# What changes

```text
sharp/mago/crates/
├── syntax/
│   ├── src/cst/cst/yield.rs              <- Yield::Spread(YieldSpread), ported
│   ├── src/parser/internal/yield.rs      <- `yield ...expr` in a .sharp file, ported
│   ├── src/cst/cst/type_hint.rs, src/parser/internal/type_hint.rs
│   │                                     <- TupleHint: `[Money, int]` and `[Money total, int count]` (Q1)
│   ├── src/cst/cst/local_declaration.rs, src/parser/internal/local_declaration.rs, statement.rs
│   │                                     <- LocalTarget: `const [a, b] = t` unpacks a tuple (Q1)
│   ├── src/cst/node.rs, src/walker/mod.rs, src/utils/mod.rs
│   │                                     <- the new nodes; yields found inside for-of and match, ported
│   ├── src/utils/pattern.rs              <- tested: `?? null` on a Map index only (decision 3)
│   └── tests/sharp.rs                    <- parse rows for each new form
├── names/src/lib.rs                      <- INLINED_COLLECTION_METHODS gains (List, getOrNull) and (List, toSet);
│                                            CHANGING_COLLECTION_METHODS loses "set" (Q2)
├── names/src/internal/walker.rs          <- the new nodes in each walk, ported
├── codex/
│   ├── src/scanner/ttype.rs              <- Iterable<T>, ported; a tuple type as a TList with tuple_names (Q1)
│   ├── src/scanner/function_like.rs      <- scan_method: a .sharp method without a modifier is private (decision 8)
│   ├── src/ttype/atomic/                 <- TList.tuple_names; TIterable.holds_map_entries, PHP#'s Iterable<T> (Q1)
│   └── src/ttype/comparator/             <- a List and a Set are contained by Iterable<T>; a Map by
│                                            Iterable<[K, V]>
├── prelude/assets/extensions/sharp.php   <- ListMethods gains getOrNull(int): ?T and toSet(): Set<T>, loses get
│                                            and set (Q2); MapMethods gains values(): list<V>; IterableMethods,
│                                            ported
├── semantics/
│   ├── src/internal/checker/sharp.rs     <- check_yield and check_iterator_returns, ported; is_slice_method
│   │                                        loses the modifier refusal; the collection key rule takes a
│   │                                        collection of keys with ==; a tuple past four elements
│   │                                        suggests a struct, and holds no `void` (Q1)
│   └── tests/sharp.rs                    <- rows for each
├── analyzer/
│   ├── src/expression/yield.rs           <- each yielded value checked against T; `yield ...` named, ported
│   ├── src/expression/array_access.rs    <- `x[i] ?? fallback` and `x[i] ??= v` on a List refused, naming
│   │                                        getOrNull (C19)
│   ├── src/expression/assignment/array_assignment.rs
│   │                                     <- the "not written by index" refusal deleted (C21)
│   ├── src/utils/expression/mod.rs       <- analyze_member_object: the List index before ?. keeps its type
│   │                                        (decision 3)
│   ├── src/invocation/arguments.rs       <- a Set parameter takes a List literal only; toSet() help (C34)
│   ├── src/resolver/method.rs            <- an Iterable place resolves on IterableMethods, ported
│   ├── src/resolver/property.rs          <- `summary.total` reads a tuple element by its name (Q1)
│   ├── src/statement/mod.rs              <- `const [a, b] = t` gives each local its element type (Q1)
│   ├── src/statement/loop/mod.rs         <- a single loop variable over a Map is [TKey, TValue]; `[a, b]`
│   │                                        unpacks each two-element tuple of any other collection (Q1)
│   ├── src/utils/names.rs                <- Iterable<T> and tuples in messages, ported
│   └── tests/sharp.rs                    <- one test per rule, forms as rows
└── sharp-bridge/
    ├── src/lower.rs                      <- element_write lowers a List index write to set (decision 2);
    │                                        null_safe_object and read_collection lose ZEND_DIM_IS;
    │                                        collection_call lowers getOrNull and toSet; YIELD, a FOREACH of YIELD
    │                                        and the generator flag, ported; one \Sharp\Sequence::from per
    │                                        chain, ported; method() gives a missing modifier
    │                                        ZEND_ACC_PRIVATE; a Map entry and a tuple as a PHP list, a
    │                                        named element as its index, a Map in an Iterable place as
    │                                        \Sharp\Sequence::from (Q1)
    ├── src/lower/types.rs                <- call_target finds the lazy operations, ported; the type text
    │                                        `[A, B]`
    └── tests/lower.rs                    <- one test per lowering form

ext/sharp/
├── sharp.c                               <- Sharp\Sequence, ported; Sharp\Collection::values;
│                                            sharp_type_accepts wraps a Traversable for Iterable<T> in a
│                                            checking Sequence (decision 7); sharp_collection_read_dimension
│                                            loses its BP_VAR_IS read; the tuple type `[A, B]`; a Sequence
│                                            reads a Sharp\Map's entries; carried Iterable types (Q5)
├── php_sharp.h                           <- SHARP_TYPE_TUPLE (Q1)
├── sharp.stub.php, sharp_arginfo.h       <- Sharp\Sequence and Collection::values; regenerated
└── sharp_unit.h                          <- regenerated by sharp-bridge/build.rs
Zend/
├── zend_compile.c                        <- zend_compile_sharp_receiver and zend_delayed_compile_dim lose their
│                                            ZEND_DIM_IS arms for a PHP# index
└── tests/sharp/
    ├── sequence.inc, sequence.phpt, sequence_jit_function.phpt, sequence_jit_tracing.phpt   <- ported
    ├── Import.sharp, yield.phpt                                                             <- ported
    ├── Grid.sharp, IndexRead.sharp, Larder.sharp, Point.sharp, index_changes.{inc,phpt}     <- a missing index
    │                                     before ?. throws; the index write and +=, ++ and --
    ├── CollectionMethods.sharp, collection_methods.{inc,phpt}, collection_methods_jit_*.phpt
    │                                     <- getOrNull, toSet, values; get and set moved (Q2)
    ├── Crossings.sharp, collection_crossings.phpt
    │                                     <- a lazy plain PHP source checked as it is read; a PHP list
    │                                        entering a tuple; a Map entering an Iterable as its entries
    ├── Collections.sharp, Collections.inc, collections.phpt, opcodes.phpt
    │                                     <- a tuple's named read, unpack and loop, with their PHP twin
    ├── ControlFlow.sharp, ControlFlow.inc, Members.sharp, Members.inc, for_of.phpt, harness/Prices.inc
    │                                     <- a loop over a Map reads its key and value; a value-only loop
    │                                        reads a List
    ├── StructKeys.sharp, struct_keys.phpt
    │                                     <- a List, Map and Set key, two equal keys in two orders
    └── the other sources that call List get or set (Q2): EnumKeys, Inbox, MixedKeys, Operators, Roster,
                                          StaticCollections, TypeArgumentsChecks
sharp/composer/library/Sharp/Text/Text.sharp
                                          <- string.parseCsv() (Q6)
docs/
├── spec.md                               <- §17 gains the ?? continue line (Q4); the index-rules write claim;
│                                            the tuples example runs in Order and reads its fields with this.
├── decisions/026-collection-operations.md, 033-iterable.md
│                                         <- match the spec (decision 10)
└── architecture/slices/12-collections.md <- this note
sharp/baseline/spec/                      <- §12 index-rules, iterables, map-string-keys, set-variable-arguments,
                                             yield, §17 map-values and §30 tuples deleted
```

# Public names this Slice adds or changes

team-lead approves these. Everything not here is tactical.

- **PHP# names, all from the spec:** `List.getOrNull(int index): T?`, `List.toSet(): Set<T>`, `Map.values(): List<TValue>`, `Iterable<T>` with `filter`, `map`, `take` and `toList`, `yield value;`, `yield ...other;`, and section 30's tuple types. Question 2 deletes `List.get` and `List.set`. Question 6 adds `string.parseCsv(): List<string>`.
- **Engine names plain PHP can see:** the final class `Sharp\Sequence`, which plain PHP gets back where a PHP# `Iterable` it holds reaches it, and `Sharp\Collection::values()`. `Sharp\Collection` has a private constructor, and so does `Sharp\Sequence`.
- **mago names:** the CST nodes `YieldSpread`, `TupleHint`, `TupleElementHint`, `LocalTarget` and `LocalUnpack`, with their `NodeKind.php` entries, `ForOfTarget::Unpack` and its `ForOfUnpackTarget`, with the fields `first` and `second`, in place of `ForOfTarget::KeyValue` and `ForOfKeyValueTarget`, `LocalDeclaration.target` in place of `LocalDeclaration.name`, codex's `TList.tuple_names` and `TIterable.holds_map_entries`, and the prelude class `Sharp\IterableMethods`, beside `Sharp\ListMethods`, `Sharp\MapMethods` and `Sharp\SetMethods`.

The slate for the engine class, called as `\Sharp\Sequence::from($orders)->filter($f)->take(100)->toList()`:

- `Sharp\Sequence` — Kotlin's lazy collection; "the sequence of the orders"; the lazy branch's name, with its tests
- `Sharp\LazyIterable` — what the caller gets; "a lazy iterable of the orders"; long, and repeats the PHP# type's name
- `Sharp\Stream` — Java's word; "the stream of the orders"; PHP calls files and sockets streams
- `Sharp\Enumerable` — C#'s word; "the enumerable of the orders"; an adjective as a noun
- `Sharp\Pipeline` — the stages a chain builds; "the pipeline of the orders"; Laravel's `Pipeline` means middleware

Recommended: `Sharp\Sequence` — Kotlin's `Sequence` is the precedent decision 033 cites, and the branch's tests already use it.
Runner-up: `Sharp\Stream` — Java's lazy chain, at the cost of PHP's own stream meaning.

# The tests

Each test proves one rule and fails on a real bug in it, with its forms as rows. Each is seen failing before its code. The existing test for a rule grows a row before a new test is added.

**mago, `crates/analyzer/tests/sharp.rs` and `crates/semantics/tests/sharp.rs`:**

- C17: `is`, `as`, `match` and `?.` on a `List` index keep the element type, and `?.` on a non-null element is the needless null check.
- C18 and C19: `getOrNull` types `T?`; `x[i] ?? fallback` and `x[i] ??= v` are refused on a `List<int>` and a `List<int?>`, and allowed on a `Map`.
- C21: `=`, `+=`, `++` and `--` on a `List` index of a local, a parameter, a property, a hooked property and a nested `List` compile.
- C30: `List`, `Map` and `Set` keys of `int` compile, and of a class without `==` are refused with the new list of kinds.
- C34: a list literal to a `Set` parameter compiles; a `List` variable as an argument, a local, a return and a property is refused with `toSet()`; `toSet()` types `Set<T>`.
- C39 to C45, ported and grown: `Iterable` places, a `List`, a `Set` and a `Map` landing in one, `rows[0]` refused, the lazy operations' types, a plain PHP class keeping its methods.
- C46 to C52, ported: `yield` in each place it stands and is refused, with each message.
- Q1: a tuple type, literal, unpacking and named read, and a single loop variable over a `Map` typed `[TKey, TValue]`.
- Q4: section 17's `continue-in-condition` example runs `?? continue` in the condition of a `while`, a `do` and a `for`, and counts the passes.
- Q5: `is Iterable<Order>` reads the type a PHP# generator, a check stage and a map stage carry, and a plain PHP `Traversable` is only an `Iterable<Any?>`.
- Rule 13: a method without a modifier is private, and a call from outside its class is refused.

**mago, `crates/sharp-bridge/tests/lower.rs`:** one test per lowering form: the index write through `set` on each kind of place, with the index read once; `getOrNull`; `toSet`; `values`; a `List` index before `?.`, `is` and `as` read bare; `yield` and `yield ...`; one `\Sharp\Sequence::from` per chain; a private method; a tuple and a `Map` entry.

**php-sharp, engine behavior in all four opcache modes:**

- `index_changes`: a missing index before `?.`, `is` and `as` throws; a null element still skips; the index write replaces on a local, a property and a hooked property, whose `set` runs once, and throws past the end and at `-1`.
- `collection_methods`: `getOrNull` past the end and at `-1`, `toSet` deduplicating, `values` in insertion order.
- `sequence` and `yield`, ported: reads counted on 10000 rows, a source that fails, `toList` numbering, a body that runs only at the loop.
- `collection_crossings`: a plain PHP generator held as an `Iterable<Order>` throws at its third element and not before; a PHP list enters a tuple only at its length with its element types; a `Map` enters an `Iterable<[string, int]>` as its entries, from plain PHP and from PHP#.
- `collections`: a tuple's named read, its unpack, and a loop that unpacks each tuple.
- `struct_keys`: a `List`, a `Map` and a `Set` as keys, two equal `Map` keys written in two orders, and an original changed after it was stored.

`sharp/bin/test --spec` then shows every section 12, 17 and 30 example passing, and a plain PHP project's analysis stays byte-identical, because every change is in the `.sharp` dialect.

# What the Slice refuses as "not supported yet", and why

Nothing in sections 12, 17 and 30.

# Found outside the Slice

- **Ruling 7.6 of `12-collection-methods.md` no longer holds.** Its source lines ("The same holds for a `List` index") left the spec in `cc3258a319`. Decision 3 deletes what it built, and that note's ruling stays as the record of why it was built.
- **`sortedBy` refuses an enum key:** "`sortedBy` orders numbers, strings, and classes and enums that declare `operator <=>`, and `Status` is none of them." Section 19 decides whether an enum orders, not section 12.
- **Section 5's default for properties, constants and named constructors** stays refused as "not supported yet" (question 7).
- **A plain PHP `array` is a `Map<int|string, Any?>`,** so one loop variable over it is the entry `[int|string, Any?]`, as over any `Map`, and `for (const [i, item] of rows)` reads its keys and values. A plain PHP result documented `list<T>` is a `List<T>`, whose loop variable is each element.
