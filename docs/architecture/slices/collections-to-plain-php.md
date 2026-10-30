# Slice: collections to plain PHP

A fix found by moving part of a real Laravel app to PHP#. Plain PHP code and the framework read the moved classes, and a PHP# `Map` or `Set` reaches each reader as a `Sharp\Map` or `Sharp\Set` object. team-lead's rule, which Jordan can correct: a PHP# collection reaches plain PHP as a PHP array at every crossing, the mirror of the approved rule that an int-keyed plain PHP array arrives in PHP# as a `List`. A second crossing breaks too: a PHP# interface method that returns `Any?` lowers to `: mixed`, which no untyped plain PHP method fits.

**Superseded in part by [decision 83](../../decisions/083-php-through-the-standard-library.md):** `global.` does not exist in PHP# code, and a bare call of a PHP function or a bare PHP constant names nothing, as a misspelled name does. Every `global.` call in user code below is the error an unknown name gets, and every message that names a member to write in place of a PHP function is gone, with the checker's table of replacements (`REPLACEMENTS`, `WRAPPED_CLASSES`) and the functions that read it.

Every state below was observed on master `754ab7ce08`, with the engine image `php-sharp:754ab7ce08` and a Linux release `mago` built from a clean checkout of `754ab7ce08` (checked out 14:37, built 14:48).

## The answer first

**A `Map` and a `Set` leak at every crossing, and the return crossing does not work on master.** Plain PHP calling `$payment->payloadOf()` gets `Sharp\Map`. The "direct call" that converts is PHP# calling plain PHP's `new Label(…, config: [...])`, whose parameter is declared `array`. That is the argument crossing, and it converts only into a parameter that names `array`.

**A `List` already crosses as a PHP array everywhere,** because a `List` runs as one. A `List` that holds a `Map` or a `Set` leaks those elements. No `Sharp\List` class exists.

**A `Map` constant cannot load.** `public const LIMITS = ["card": 100];` compiles, and the engine stops at class load: "New expressions are not supported in this context". A typed `public const Map<string, int> LIMITS` is refused as "not supported yet".

**One engine function builds the array, and every crossing reuses it.** It does what the bridge's `\iterator_to_array` crossing does today, a `Set` without its positions and a backed enum key as its backing value, and it also converts each collection nested inside. The bridge lowers the argument, attribute and constant crossings through it. The engine runs it for a property read, a return and reflection, because plain PHP code is never lowered by the bridge.

**A PHP# read of its own property costs nothing at run time.** The engine decides once per opline, when the opline fills its cache slot, whether the reading code is plain PHP. A PHP# opline keeps today's slot read.

**The interface probe needs more than the `Any?` fix** (question 7). After `getKey()` fits, PHP stops at the next method: `Declaration of R::getMorphClass() must be compatible with L::getMorphClass(): string`.

## Each crossing on master

The probe project holds a PHP# `Payment` and `Ledger`, a plain PHP `Probe` that reports what it receives, and a plain PHP attribute `Label(string $text, array $config = [])`. The report is `is_array`, `array_merge($value, [])` and the reflected type.

| Crossing | PHP# side | What plain PHP received | Reflected type |
|---|---|---|---|
| property read | `public Map<string, string> payload { get; set; }` | `Sharp\Map`; `array_merge` throws | `Sharp\Map` |
| property read | `public Map<string, string>? extra { get; set; } = null` | `null`, a `Sharp\Map` once set | `?Sharp\Map` |
| property read | `public Set<string> codes { get; set; }` | `Sharp\Set` | `Sharp\Set` |
| property read | `{ get; }`, a computed body, a `set` body, `Map<string, Set<int>>`, `Map<Status, int>` | `Sharp\Map` for each | `Sharp\Map` |
| property read | `public static Map<string, int> counts { get; set; }` | `Sharp\Map` | `Sharp\Map` |
| property read | `public List<string> tags { get; set; }` | `array` | `array` |
| property read | `public List<Map<string, int>> rows { get; set; }` | `array` whose element is `Sharp\Map` | `array` |
| other property readers | `get_object_vars`, `(array)`, `foreach` over the object, `ReflectionProperty::getValue`, a plain PHP subclass's `$this->payload` | `Sharp\Map` for each; `json_encode` gives `{"id":"p1"}` | |
| return | `public Map<string, string> payloadOf()`, `Set<string> codesOf()` | `Sharp\Map`, `Sharp\Set` | `Sharp\Map`, `Sharp\Set` |
| return | `public List<string> tagsOf()` | `array` | `array` |
| argument | into `$value` untyped, `mixed $value` | `Sharp\Map` | |
| argument | into `array $value` | `array` | |
| argument | into `iterable`, `\Countable` | `Sharp\Map` | |
| argument | a `List<Map<string, int>>` into `array $rows` | `array` whose element is `Sharp\Map` | |
| argument | through `Function<string(Map<string, string>)>` holding a plain PHP closure | `Sharp\Map` | |
| constant | `public const LIMITS = ["card": 100];` | the class fails to load: "New expressions are not supported in this context" | |
| constant | `public const KINDS = ["card", "bank"];` | `array` | |
| attribute argument | `[Label("paid", config: ["variant": "success"])]` | `getArguments()` holds a `Sharp\Map`; `newInstance()` throws `TypeError: App\Label::__construct(): Argument #2 ($config) must be of type array, Sharp\Map given` | |
| parameter type | `int accept(Map<string, string> given)` | an array argument arrives as the `Map` | `Sharp\Map\|array` |
| return | `public Map<Limit, int?> limits()`, keyed by a backed enum, read by `foreach ($plan->limits() as $limit => $max)` | each key is the `Limit` case, so `Limit::from($limit)` throws `TypeError: App\Limit::from(): Argument #1 ($value) must be of type string\|int, App\Limit given` | |
| return | `public Map<string, int> sizes()` read by `===` and `ksort` | `$sizes === ['b' => 2, 'a' => 1]` is `false`; `ksort(): Argument #1 ($array) must be of type array, Sharp\Map given` | |
| property read | a struct's `public Map<string, string> headers { get; init; }`, read by plain PHP into `array $headers` | `TypeError: App\Mailer::send(): Argument #1 ($headers) must be of type array, Sharp\Map given`; PHP# passing `envelope.headers` there gives the array | |

PHP# reading its own `payload` got `Sharp\Map`. A plain PHP write `$payment->payload = ['id' => 'p2']` crossed in, and PHP# then read `Sharp\Map {"id":"p2"}`. A plain PHP index write `$payment->payload['extra'] = 'x'` changed the `Map` in place. A plain PHP write of a static, `Ledger::$counts = ['b' => 2]`, crossed in too.

The `Any?` return lowers to `mixed`, and so do two others. Reflected return types of a PHP# `interface Finder<T>`:

```text
key(): mixed      // Any? key();
value(): mixed    // Any value();
find(): mixed     // T find();
kind(): string    // string kind();
```

## How the states were observed

The probe project is a copy of the `map-attribute-argument-to-array-parameter` probe with its own `app/` files, run as that probe's runner runs it:

```sh
docker run --rm -v "$probe":/p -v "$mago":/usr/local/bin/mago:ro -w /p php-sharp:754ab7ce08 \
  sh -c 'mago --no-version-check --colors never compile && php run.php'
```

The two named probes printed what their `command.txt` records as observed: `getArguments()` holding a `Sharp\Map` and the `TypeError` above, and `Fatal error: Declaration of App\Record::getKey() must be compatible with App\Labeled::getKey(): mixed`. `php -r` on the same image gave `Declaration of R::getMorphClass() must be compatible with L::getMorphClass(): string` for an untyped parent method under an interface method declared `: string`.

# The mechanism

## One array form

The engine gains `sharp_array_of(zval *result, zval *value)`, and plain PHP's name for it, the internal function `\Sharp\Internal\arrayOf($value)` (decision 1). It gives:

- a `Sharp\Map` as a keyed array, a backed enum key as its backing value, as `Lowering::backed_key_array` writes it today;
- a `Sharp\Set` as a list of its elements, as `\iterator_to_array($set, false)` gives;
- a PHP array, which is a `List`, with each element converted, and the array itself when no element needs it;
- any other value as it is.

Each value inside a `Map`, a `Set` or a `List` converts the same way, so `Map<string, Set<int>>` gives `['g' => [1, 2]]`. A `Map` whose keys have no array form is question 1.

The bridge's three forms of the argument crossing collapse into this call. `Lowering::array_from`, `Lowering::array_of_each`, `Lowering::backed_key_array` and `Lowering::iterator_to_array` are deleted, and `Lowering::array_of` becomes the one call:

```diff
-/// `\is_object($m) ? \iterator_to_array($m) : $m` over the three reads of one value, and
-/// `\iterator_to_array($s, false)` for a `Set`, whose positions are no keys.
-fn array_from(&mut self, line: u32, set: bool, [first, second, third]: [u32; 3]) -> u32 {
-    let is_object = self.global_call(line, b"is_object", &[first]);
-    let array = self.iterator_to_array(line, set, second);
-
-    self.node(SHARP_AST_CONDITIONAL, 0, line, &[is_object, array, third])
-}
+/// `\Sharp\Internal\arrayOf($value)`, the PHP array plain PHP receives for `value`, spec section 12.
+fn array_of(&mut self, line: u32, value: u32) -> u32 {
+    self.global_call(line, b"Sharp\\Internal\\arrayOf", &[value])
+}
```

## The argument crossing: bridge

`Lowering::crossing_arguments` crosses a value whose type holds a `Map` or a `Set`, at any depth, into every plain PHP parameter that takes an array: no declared type, `mixed`, `array`, `iterable`, or a union that names one of them. Today it crosses only into one whose type names `array`. A parameter whose type takes no array keeps the object (question 2). A value that is itself what plain PHP gave still passes as it is. A call through a function value is question 5.

## The attribute argument and the constant: bridge

PHP evaluates both as constant expressions, which can call no function, so the bridge writes the array itself. A `Map` literal there lowers to a PHP array literal, `["variant": "success"]` to `['variant' => 'success']`, and a `Set` literal to a list. A backed enum key lowers to `Status::Paid->value`, which PHP takes in a constant expression.

`Lowering::attribute_arguments` and `Lowering::constant` lower their values this way. A PHP# read of a `Map` or `Set` constant, `Payment.LIMITS`, makes the object once through `Lowering::crossed`, the arrival a plain PHP array already takes into a `Map` place, and keeps it under the constant's own key: `\Sharp\Internal\keep('App\Payment::LIMITS#constant') ?? \Sharp\Internal\keep('App\Payment::LIMITS#constant', static fn () => …)`. Every later read shares that object, as it shares any `Map`. Inside a constant expression, as a constant's value, a literal element, an attribute argument or a default, the read is the bare `\App\Payment::LIMITS`, its PHP array, since PHP takes no call there. A property's or a parameter's crossing makes the object of that array. A PHP# attribute class gets the array at a `Map` parameter, which lowers to `\Sharp\Map|array` and makes the `Map` before its body runs, as it does for any plain PHP caller.

## The return crossing: engine

A PHP# function whose declared result can hold a `Map` or a `Set` gives plain PHP the array: a `Map`, a `Set`, a collection that holds one, `Any`, `Any?`, a type parameter or an `Iterable`. A `Map` whose key type has no PHP array form is no such result, and plain PHP gets it as the `Map`. The bridge marks the result's type with `ZEND_SHARP_RESULT_CROSSES`, the attr of its `SHARP_TYPE_ARGS`, and declares a `Map` result `\Sharp\Map|array`, so a plain PHP class can implement or override it. The compiler keeps the mark for the function it compiles, and `zend_compile_return` and `zend_compile_yield` compile each return and each yielded value through a new opcode (decision 2). No `fn_flags` bit carries it. The opcode runs `sharp_array_of` when the frame's caller is not PHP# code, with the test `ZEND_SHARP_RECV_TYPE_ARGS` already makes of its caller. An internal caller, such as `array_map` calling a PHP# lambda, counts as plain PHP. Reflection reports the result's type as `array` (question 4).

A plain PHP class that implements a PHP# interface's member gives its result as plain PHP's value, a PHP array for a `Map` or a `Set`. The PHP# call checks it against the member's type, and a local that holds it or a member that reads it makes it the object, as each makes a plain PHP call's result, so every member runs on the object and a `Map`'s keys keep their PHP# types.

## The property read crossing: engine

A read of a crossing property, made by plain PHP code, gives the array of the value the property holds. The bridge decides which properties cross when it compiles them, as it decides results: a property whose type can hold a `Map` or a `Set`, as a result's can, carries `#[\Sharp\Crossing(plan)]`, and the engine flags it `ZEND_ACC_SHARP_CROSSING` in the property's own flags. A `Map` whose key type has no PHP array form carries `#[\Sharp\Crossing(plan, false)]`: its plan still crosses a PHP array in, and plain PHP reads the `Map`. An `Any?`, a type parameter or an `Iterable` carries a `null` plan, since nothing it holds crosses back.

- `zend_get_property_offset` gives such a property, when the executing code is plain PHP and the access is a read, the slow path it gives a hooked property. A PHP# opline keeps the slot offset in its cache slot.
- `zend_std_read_property` runs `sharp_array_of` on the value it read, after a `get` hook when the property has one. A computed property and one with a `get` body are crossed after their PHP# body runs.
- A plain PHP write and index write keep today's path: `$payment->payload = [...]` crosses in through the plan, and `$payment->payload['k'] = 'v'` changes the `Map` in place.
- A PHP# index write, `this.payload[k] = v`, changes the `Map` the slot holds, inline as today. A slot that holds plain PHP's reference leaves the inline path: `zend_handle_fetch_obj_flags` and the JIT's copy of it call `sharp_property_for_write`, which gives the write the `Map` rebuilt from the reference's array, so its keys keep their PHP# types. The `Map` then writes itself back through `write_property`, which stores its array form in the reference, so plain PHP sees the write.
- A `ref` member, `this.payload.remove(k)`, fetches the slot by reference. Where it holds plain PHP's reference, `sharp_property_for_write` puts the `Map` rebuilt from the reference's array into the reference, and the member changes it in place. The unset fetch after the call finds the `Map` there, and the reference takes its array form with the typed-reference assignment, so plain PHP sees the change.
- A static read, `Ledger::$counts`, crosses the same way in `zend_fetch_static_property_address`'s read.
- `zend_fetch_prop_type` in the optimizer types a plain PHP op_array's read of such a property as an array, and the JIT's `zend_get_known_property_info` leaves it to the VM handler, so a plain PHP subclass's `$this->payload` is never read inline from the slot. The inference keeps such a read's result counted, `MAY_BE_RC1`, unless it resolves the property and finds no crossing, so the JIT frees the array each read builds.

Which other plain PHP readers cross is question 3. Beyond its answer, `empty()`, `get_mangled_object_vars`, `get_class_vars`, `array_walk` and `ArrayIterator` over the object, and `ReflectionClass::getStaticPropertyValue` and `getStaticProperties` cross too.

## The reflected property type: engine

`ReflectionProperty::getType()`, `hasType()` and `getSettableType()` report a flagged property's `Sharp\Map` or `Sharp\Set` type as `array`, and `?array` when it is nullable. A property the bridge did not flag, such as a `Map` keyed by an enum without backing values, reports `Sharp\Map`. A parameter or a result reports `array` where its type is `\Sharp\Map|array`. The engine keeps `Sharp\Map` as the property's declared PHP type, which every write and the optimizer still read.

## The `Any?` return: bridge

A method's return type that lowers to `mixed` lowers to no PHP return type: `Any?`, `Any`, and a type parameter with no bound, since PHP lowers all three to the same `mixed`. `Lowering::method_declaration` gives `Lowering::function_type` a `NULL` PHP type for them, and the PHP# result text the engine carries stays, as a lambda's does. An override that declares a narrower type, such as `override int getKey()`, keeps `: int`, which PHP allows under a parent with no return type.

```diff
 let (start, return_type) = match &method.return_type_hint {
-    Some(return_type_hint) => (return_type_hint.span(), self.hint(&return_type_hint.hint)),
+    Some(return_type_hint) => (return_type_hint.span(), self.return_hint(&return_type_hint.hint)),
     None => (method.name.span, NULL),
 };
```

## The spec

```diff
-- Plain PHP receives the `Map` itself, or a PHP array where its parameter's declared type names `array`. A PHP array's keys are `int` or `string`, so a `Map` keyed by a backed enum passes each case's backing value as its key, as an array crossing the other way gives each backing value as its case. Passing a `Map` keyed by anything else to such a parameter is a compile error that names the parameter and the key type. `counts.count()` counts any `Map`, whatever its keys.
+- Plain PHP receives a `Map` or a `Set` as a PHP array wherever it gets one: a result, a value a generator yields, an argument, a property it reads, a constant and an attribute's argument, and so does each `Map` and `Set` a `List` holds. That holds where the declared type is `Any`, `Any?`, a type parameter or an `Iterable` too. […]
+- A PHP array's keys are `int` or `string`, so a `Map` keyed by a backed enum gives each case's backing value as its key, as an array crossing the other way gives each backing value as its case. A `Map` keyed by anything else, a class, a struct, a collection or an enum without backing values, reaches plain PHP as the `Map` itself, the one collection plain PHP receives as the object. Passing it to a plain PHP parameter that names `array` is a compile error that names the parameter and the key type. `counts.count()` counts any `Map`, whatever its keys.
+- Every other `Map` crosses as a PHP array, as one keyed by `int|string` or by a backed enum beside its backing type does. When two of its keys would become one PHP array key, as `5` and `"5"` do, or a backed enum case and its backing value, the crossing throws a `ValueError` that names both keys […]. It never keeps one entry and drops the other.
```

## The colliding keys check: engine

`sharp_table_array` builds a `Map`'s PHP array for every crossing. When the array holds fewer entries than the `Map`, two keys became one, and `sharp_table_refuse_collision` walks the `Map` again to name the first two and throws. A `Map` with no such keys pays one count compare. Every crossing reaches it through `sharp_array_of`, which then leaves the `Map` itself in its result, so each caller frees what it holds as it does for any other exception.

Decision 027's two sentences that say plain PHP receives "the `Sharp\Map` itself" change the same way.

# What it costs a PHP# read of its own property

**Nothing at run time.** `this.payload` and `payment.payload` in a `.sharp` file compile to the same `FETCH_OBJ_R` as today. Its cache slot holds the slot offset, and the hit path is unchanged. The new test, whether the reading code is plain PHP, runs only on a cache miss, once per opline and class.

The other costs:

- **A PHP# method that returns a `Map` or a `Set`** runs the new opcode at each return: one load of the caller frame and a 6-byte compare of its file name's end. `ZEND_SHARP_RECV_TYPE_ARGS` makes the same test today at each call of a method whose parameters it checks.
- **A PHP# read of a `Map` or `Set` constant** builds the `Map` from the array once, linear in its entries, since a PHP class constant holds no object, and each later read is one lookup of the kept object.
- **A PHP# index write of a `Map` property** with no reference in its slot pays one more compare of the slot's type in the VM. Under either JIT it pays none, since the JIT folds the reference test into the compare it made on master. A loop of 1,000,000 `this.counts["k"] = i` writes on debug builds, as the median of 10 alternating runs of 7, took 77.0 ms against master's 75.7 ms with no JIT, 45.1 ms against 45.5 ms under the function JIT, and 40.5 ms against 41.6 ms under the tracing JIT.
- **Plain PHP pays one array build per read,** linear in the entries and the collections inside them. A plain PHP loop that reads `$payment->payload['id']` 1000 times builds the array 1000 times. Its copy is plain PHP's own, as a PHP array's always is.

Two shapes were weighed and left:

- A PHP `get` hook that builds the array makes every PHP# read pay that linear build.
- A hook over a hidden backing property that PHP# reads directly costs PHP# nothing. Every PHP# read, collection method receiver and engine path that names the property then moves to the hidden name, and `get_object_vars` lists the hidden property to plain PHP.

# Questions team-lead answered

Each decides what a user writes or what plain PHP receives. Each has its proposal and its answer, which the rest of this note follows.

1. **A `Map` whose keys have no PHP array form,** keyed by a class, a struct, a collection or an enum without backing values. A property read or a return of one has no array to give. Today passing one to an `array` parameter is a compile error.
   - **Proposed:** plain PHP receives the `Sharp\Map` itself for such a `Map`, the one exception, with a spec line that says so. The compile error into an `array` parameter stays.
   - A compile error on every public or protected property, public method result, constant and attribute argument whose type holds such a `Map`. PHP#-only code could then not expose one.
   - A list of `[key, value]` pairs, as `var_dump` shows a `Map` today.
   - **Answered: as proposed,** stated in the spec line. The review first added another exception decided by the key type, a `Map` keyed by `int|string` staying the `Map`. Team-lead reversed it, because a plain PHP array is a `Map<int|string, Any?>` in PHP#, so it refused passing any PHP array back to plain PHP. Such a `Map` crosses as its array, and throws where two of its keys would be one PHP array key.
2. **A plain PHP parameter whose type takes no array,** such as `\Countable`, `\Traversable`, `object`, `\JsonSerializable` or an interface. PHP throws `TypeError` for an array there.
   - **Proposed:** the object, as today, with a spec line that names it.
   - A compile error that names the parameter and its type.
   - **Answered: as proposed.** `iterable`, `mixed`, an untyped parameter and `array` get the array.
3. **Which plain PHP property readers cross.**
   - **Proposed:** `->`, `get_object_vars`, `(array)`, `foreach` over the object and `ReflectionProperty::getValue`, since each gives plain PHP the value to use. `serialize`, `var_export`, `var_dump`, `print_r` and `debug_zval_refcount` keep the object, so serialized data still round-trips into the `Map`.
   - `->` alone.
   - **Answered: as proposed.** A separate fix makes `serialize` of a `Map` work.
4. **The reflected types of a method.** `ReflectionParameter::getType()` reads `Sharp\Map|array`, and `ReflectionMethod::getReturnType()` reads `Sharp\Map`. Laravel's container reads parameter types to build a class.
   - **Proposed:** both report `array`, as the property does.
   - Only the property, as the rule lists.
   - **Answered: as proposed.**
5. **A call through a function value,** whose target the bridge cannot see. `applied(Function<string(Map<string, string>)> each)` called with a plain PHP closure gives it `Sharp\Map`.
   - **Proposed:** the argument crosses as an array whenever the function type's parameter holds a `Map` or a `Set`. A PHP# target makes the `Map` again at its parameter, so a PHP#-to-PHP# call through a function value builds the collection twice.
   - The object, as today.
   - **Answered: as proposed, except that a PHP# target is never given the array,** so PHP# code never builds a collection twice. The function value is a `\Closure`, so the crossing reads the closure's function and leaves the value as it is when that function is in a `.sharp` file.
6. **A typed collection constant.** `public const Map<string, int> LIMITS = ["card": 100];` is refused: "A PHP# constant has … an optional type of `int`, `float`, `bool`, `string`, `Any` or a class". The untyped form compiles and fails at class load, which this Slice fixes.
   - **Proposed:** the typed form compiles, since spec section 2 lets a constant hold any value known before the code runs.
   - The typed form stays refused, with a message that names the untyped form.
   - **Answered: as proposed.** v1 leaves no "not supported yet".
7. **The interface probe's `string getMorphClass()`.** `Invoice : Record, Labeled` takes `getMorphClass()` from the plain PHP `Record`, untyped as Eloquent's is. `Labeled.getMorphClass()` lowers to `: string`, so after the `Any?` fix the probe stops at `getMorphClass()` and prints nothing. The rule covers `Any?` only.
   - **Proposed:** the bridge gives a PHP# class a forwarding method for each method of a PHP# interface that a plain PHP parent implements with a PHP signature the interface's refuses: `public function getMorphClass(): string { return parent::getMorphClass(); }`. Kotlin and Java write such bridge methods. The forwarder checks the parent's result against `string`. This Slice builds it, so the probe prints `record:7`.
   - mago refuses `Invoice` with an error that names `Record::getMorphClass()` and `Labeled.getMorphClass()`. The probe then prints that error, which its `command.txt` also accepts.
   - Neither, and the probe's verification is dropped from this Slice.
   - **Answered: the forwarding method,** for every PHP# class whose plain PHP parent's method implements a typed PHP# interface method. PHP checks the forwarder's result at run time.

# Decisions team-lead approved

1. **The engine function `\Sharp\Internal\arrayOf`,** called as `\Sharp\Internal\arrayOf($payment->payload)` in lowered code. `Sharp\Internal` already holds `accepts`, `equals` and `statics`.
   - `\Sharp\Internal\arrayOf()` — what the caller gets; "the array of the payload"; the bridge's own `Lowering::array_of` names it today
   - `\Sharp\Internal\phpArray()` — the spec's words; "the PHP array of the payload"
   - `\Sharp\Internal\plain()` — the reader; "the payload for plain PHP"; an adjective as a function
   - `\Sharp\Internal\outward()` — the direction; "the payload outward"; needs the crossing in mind
   - `\Sharp\Internal\crossOut()` — the crossing; "cross the payload out"; the engine's `cross` means the other direction today
   Recommended: `arrayOf` — the name the bridge already gives this job. Runner-up: `phpArray` — the spec's own words, at the cost of a `php` prefix no other name has.
2. **A new opcode for the return crossing,** run as `ZEND_X result, value` before `ZEND_RETURN`.
   - `ZEND_SHARP_RETURN_ARRAY` — the result plain PHP gets
   - `ZEND_SHARP_PLAIN_RESULT` — the reader
   - `ZEND_SHARP_CROSS_RESULT` — the crossing; the engine's `cross` means the other direction today
   Recommended: `ZEND_SHARP_RETURN_ARRAY`. Runner-up: `ZEND_SHARP_PLAIN_RESULT`. The other shape is a call of `\Sharp\Internal\arrayOf`'s sibling at each return, which tests its caller's caller. It adds no opcode, and costs each such return one internal call.
3. **The tests grow the existing crossing test.** `collection_crossings.phpt` and its `Crossings` fixture gain the rows, and one new file, `collection_crossings_preload.phpt` with its `.inc`, runs them preloaded.

Approved: `\Sharp\Internal\arrayOf`, `ZEND_SHARP_RETURN_ARRAY`, following `ZEND_SHARP_RECV_TYPE_ARGS`'s precedent with rows under the function JIT, the tracing JIT, preloading and the opcache file cache, and the test files.

Also approved into this Slice: the first "found, not fixed" row. A plain PHP write of a list of arrays into a `List<Map<string, int>>` property makes each element the `Map`, so the crossing into PHP# converts nested collections as `arrayOf` does on the way out.

# What changes

```text
sharp/mago/crates/sharp-bridge/
├── src/lower.rs                  <- crossing_arguments crosses into every parameter that takes an array, through
│                                    arrayOf; array_from, array_of_each, backed_key_array and iterator_to_array
│                                    deleted; attribute_arguments and constant write a Map or Set literal as a PHP
│                                    array; a constant read arrives through crossed; method_declaration lowers a
│                                    `mixed` return to none; a result that can hold a Map or Set carries
│                                    ZEND_SHARP_RESULT_CROSSES and a `\Sharp\Map|array` type; the forwarding method
│                                    (Q7)
├── src/lower/types.rs            <- PlainParameter::takes_array; whether a type holds a Map or Set at any depth;
│                                    the plain PHP parent methods an interface's signature refuses (Q7)
└── tests/lower.rs                <- every iterator_to_array row moves to arrayOf; a row per new lowering
ext/sharp/
├── sharp.c                       <- sharp_array_of; Sharp\Internal\arrayOf; the return opcode's crossing
├── php_sharp.h                   <- sharp_array_of
├── sharp.stub.php, sharp_arginfo.h
│                                 <- arrayOf; regenerated
└── sharp_unit.h                  <- regenerated by sharp-bridge/build.rs
Zend/
├── zend_object_handlers.c        <- zend_get_property_offset sends a plain PHP read of a crossing property to the
│                                    slow path; zend_std_read_property crosses it; get_object_vars and (array) (Q3)
├── zend_execute.c                <- a plain PHP static read crosses
├── zend_compile.c                <- each return and yield of a marked result runs the new opcode
├── zend_vm_def.h                 <- the new opcode; zend_vm_execute.h, zend_vm_opcodes.{c,h} regenerated
├── Optimizer/zend_inference.c    <- the opcode's result, and a plain PHP read of a crossing property, may be an
│                                    array
└── tests/sharp/
    ├── Crossings/Crossings.sharp, Crossings/*.sharp, harness/*.inc
    │                             <- the probe's forms as fixture types, one type per file
    ├── collection_crossings.phpt <- a row per crossing; today's outward rows change to arrays
    ├── collection_crossings_preload.{phpt,inc}
    │                             <- the same rows, preloaded
    ├── Lambdas.inc, Sets/Tagged.inc
    │                             <- the PHP twins write arrayOf where the bridge now does
    └── opcodes.phpt              <- a fixture whose method returns a Map shows the new opcode
ext/opcache/jit/zend_jit.c        <- zend_get_known_property_info leaves a crossing property in plain PHP code to
                                     the VM
ext/reflection/php_reflection.c   <- getType, hasType and getSettableType report array (Q4)
docs/
├── spec.md                       <- section 12's plain PHP line; question 1's and 2's lines
├── decisions/027-map-keys.md     <- its two "the Sharp\Map itself" sentences
└── architecture/slices/collections-to-plain-php.md
                                  <- this note
```

# The tests

Each row is seen failing on master before its code.

**php-sharp, `collection_crossings.phpt`, without opcache and preloaded,** with `USE_ZEND_ALLOC=0 MallocScribble=1`. Each row runs `is_array`, `array_merge($value, [])` and, for a property, `ReflectionProperty::getType()` on what plain PHP receives:

- a property read of a `Map`, a `Map?`, a `Set`, a `List<Map>`, a `Map<string, Set<int>>`, a `Map` keyed by a backed enum, a computed property, one with a `get` body and a static;
- a return of a `Map`, a `Set` and a `List<Map>`, from a plain PHP caller and from `array_map`;
- an argument into an untyped, `mixed`, `array` and `iterable` parameter, and a `List<Map>` into `array`;
- a `Map` constant and a `Set` constant;
- an attribute argument read with `getArguments()` and `newInstance()`;
- a PHP# method reading its own `Map` property, which still gets a `Map`, after a plain PHP read and after a plain PHP write;
- a return of `Map<Limit, int?>` read by `foreach` and `Limit::from`, a returned `Map` read by `===` and `ksort`, and a struct's `Map` property read by plain PHP into `array $headers`;
- a plain PHP write of a list of arrays into a `List<Map<string, int>>` property, read back by PHP# as `Map`s.

The return rows also run under the function JIT, the tracing JIT and the opcache file cache.

**mago, `crates/sharp-bridge/tests/lower.rs`:** an argument into each parameter kind, a `Map` literal in an attribute argument and in a constant, a constant read, and an `Any?`, `Any` and unbounded `T` return lowered without a type.

**The two probes** print what their `command.txt` expects: `attribute config is array`, and `record:7` under question 7's answer. `sharp/bin/test --spec` and a plain PHP project's analysis stay unchanged, because every bridge change is in the `.sharp` dialect.

# Built here from the first round's "found, not fixed"

**A plain PHP write into a `List<Map<string, int>>` property leaves each element a PHP array.** The property's PHP type is `array`, which carries no `#[\Sharp\Crossing]` plan, so nothing crosses the elements in. PHP# then reads an `array` where its type says `Map`.

```csharp
public List<Map<string, int>> rows { get; set; } = [["a": 1]];
public string firstRowType() => global.get_debug_type(this.rows[0]);
```

```php
$ledger->rows = [['b' => 2]];
echo $ledger->firstRowType();   // array; expected Sharp\Map
```

# Built here from the second round

Six places still gave plain PHP the object after #163. Each now gets the array form `sharp_array_of` gives, through #163's crossing, and PHP# code still reads its own `Map`, `Set` or `List` with no copy. The rows below run in `collection_readers.phpt` on this fixture, under no JIT, the function JIT and the tracing JIT, each with and without preloading.

```csharp
public class Carton
{
    public Map<string, int> payload { get; set; } = ["b": 2, "a": 1];
    public Set<string> codes { get; set; } = ["b", "a"];
}
```

**1. A by-reference read of a crossing property.** Plain PHP's `ksort($box->payload)`, `$r = &$box->payload`, `foreach ($box->payload as &$v)` and `array_walk($box->payload, …)` threw or changed nothing.

- The first plain PHP reference to an instance property turns its slot into a typed reference to the array form. `sharp_property_takes_reference` does it at each place PHP makes a property reference: `zend_handle_fetch_obj_flags` and the JIT's copy of it, `zend_jit_create_typed_ref`, `FE_FETCH_RW`, `array_walk`, `ArrayIterator` and a hooked object's by-reference iterator.
- It converts only when the property's `#[\Sharp\Crossing]` plan rebuilds every `Map` and `Set` the value can hold. A plan part that may hold a collection no plan rebuilds is `false`: the values of a `Map<string, Any>` are. A top-level `null` plan, as an `Any?`, a type parameter or an `Iterable` carries, rebuilds nothing. Such a property's reference holds the PHP# value itself, so the value is never left an array for good.
- A whole-array write through the reference is checked against the property's PHP# type, and the reference keeps the array form. `$r = 'x'` throws the `TypeError` that names `Map<string, int>`.
- PHP# reads the property while plain PHP holds the reference, and gets the `Map` made from the array. `sharp_reference_holds` checks the array first, so an element plain PHP wrote through the reference that does not fit throws at each read: "Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got string 'x'". A key no `Level` case backs throws the same way and never reaches the enum's `from`. Once plain PHP drops the reference, the next PHP# read puts the `Map` back into the slot, so later reads share one object again. `sharp_property_from_reference` does both. The VM's and the JIT's inline reads send a reference slot of a crossing property to `zend_std_read_property`, which calls it.
- A `clone` or a `with` PHP# code makes copies a reference slot of a crossing property as the PHP# value `sharp_property_from_reference` gives, so the copy never shares plain PHP's reference. `zend_objects_clone_members` does it. Plain PHP's `clone` shares a reference something else holds, as stock PHP's does, and copies one only the slot holds into a reference of its own, where stock PHP takes the value, so PHP# code still checks what plain PHP wrote when it reads the clone.
- A PHP# index write while plain PHP holds the reference runs as "The property read crossing: engine" says. An enum key after `ksort` and the keys `5` and `"5"` of a `Map<int|string, string>` keep their PHP# types, and a `Map<string, Set<string>>` given `this.tags["a"] = ["x"]` leaves `$r['a']` an array.

```php
ksort($box->payload);
echo $box->payloadText();   // Sharp\Map a,b=1,2
```

**2. A plain PHP parent appends to a property its PHP# subclass declares with `get => field`.** Eloquent's `$this->hidden[] = 'secret'` threw "Indirect modification". A property whose only body is `get => field` or `get { return field; }`, with no attribute, now lowers to plain storage, as an auto-property does, so PHP changes the array in place. It stays a hook when a parent class declares a `get` hook for the property, since PHP copies the parent's hook into a child that declares none: `Quiet : Shouter` overrides `word { get => field; }` and reads `b`, where storage alone would run `Shouter`'s `strtoupper` and read `B`. `Types::inherits_getter` walks the parents.

**3. A PHP array into a union parameter that holds a `Set` or a `Map`.** `Set<string>|int` lowered to `\Sharp\Set|int`, which refused the array. It now lowers to `\Sharp\Set|int|array`, as a lone `Set` lowers to `\Sharp\Set|array`, and the parameter's crossing makes the `Set`. A union that holds a `List` is the union as written, since a `List` is a PHP array already. One predicate, `Lowering::takes_php_array`, decides it for a parameter, a promoted constructor parameter, a `set` body's parameter and the setter it lowers to, so `Set<string>|int picked { get; set => field = value; }` takes `['a']` and holds a `Set`.

**4. An `ArrayIterator` over a PHP# object.** `foreach`, `current()` and `getArrayCopy()` gave `Sharp\Map`. Each now gives each crossing property's array form.

**5. A lazy `Iterable` stays lazy when it crosses.** Plain PHP gets a `Traversable`, the `Sharp\Sequence`, and each element crosses by #163's rule as plain PHP reads it, so a `Map` element arrives as its array. A plain PHP source is read in step with plain PHP's loop. The bridge declares an `Iterable<T>` result `iterable`. A function that needs an array, such as `array_map`, gets one from `iterator_to_array`, as it does for a Generator. Master already behaves so, and the rows guard it. Reading a lazy `Iterable` into an array where it crosses would read a `cursor()` stream whole, and break spec section 12's rule that `is Iterable<T>` reads no element of a lazy source.

**6. `var_export` of a `Map` or a `Set`** printed `\Sharp\Map::__set_state(array())`, code that rebuilds no entry. It now prints the array form, alone or inside an exported object. This changes question 3's answer for `var_export` only. `serialize`, `var_dump` and `print_r` still keep the object.

## Accepted as is

- While plain PHP holds a live reference, each PHP# read rebuilds the `Map` from the array, so two reads give two objects.
- Once plain PHP drops the reference, the next PHP# read puts the rebuilt `Map` back into the slot with no copy, and later reads share it.
- Between a `ksort` and the next PHP# read, `var_dump`, `serialize` and `==` see the array the slot holds.
- A `Set`'s array form takes duplicates through the reference: `$r[] = 'c'` twice leaves `$r` `["b","a","c","c"]`, and PHP# reads `Set b,a,c`, as a whole-array write of `['c', 'c']` gives one `c`.

# Found, not fixed

Each probe ran on this branch and on master `27626cb117`. The results match except where an entry says otherwise.

**A static crossing property by reference still gives the object.** `sharp_property_takes_reference` skips a static property, because the static read path has no `sharp_property_from_reference` step yet. Fixing it takes that step in `zend_fetch_static_property_address` and in the JIT's static property read.

```php
ksort(Demo\Holdings::$counts);   // TypeError: ksort(): Argument #1 ($array) must be of type array, Sharp\Map given
$r = &Demo\Holdings::$counts;    // get_debug_type($r) is Sharp\Map
```

**Assigning a plain PHP variable by reference into a crossing property makes that variable a `Map`.** The assignment crosses the array in through the plan, and the `Map` replaces the array in the variable the reference shares.

```php
$array = ['q' => 1];
$box->payload = &$array;
echo get_debug_type($array);     // Sharp\Map
```

**A plain PHP element write made directly into a `Map` property is not checked against the `Map`'s value type.** A whole-array write is checked. An element write through a reference is checked at the next PHP# read on this branch, where master stored it unchecked.

```php
$box->payload['k'] = 'x';        // no TypeError on a Map<string, int>
echo $box->payloadText();        // Sharp\Map b,a,k=2,1,x
```

**A reference to an `ArrayObject` offset over a PHP# object is an untyped reference to the property.** The same reference to a plain PHP typed property takes a value of any type and aborts a debug build when the object is freed, on master too, so it is PHP's own gap in a path PHP 8.5 deprecates. On this branch PHP# then reads the property through `sharp_reference_holds`, which refuses a value that does not fit. On master that read returns the array.

```php
$view = @new ArrayObject($box);
$r = &$view['payload'];          // get_debug_type($r) is Sharp\Map
$r = ['k' => 'x'];
$box->payloadText();             // TypeError: Reference held by property Demo\Carton::$payload of type Map<string, int> holds array: ["k"] expected int, got string 'x'
// Assertion failed: (source_list->ptr == prop), function zend_ref_del_type_source, in a debug build when $box is freed
```

**`var_export` of a `Map` with no array form still prints no entry,** since question 1 keeps such a `Map` the object.

```php
var_export((new Demo\Holdings())->forecast);   // a Map<Weather, int>
// \Sharp\Map::__set_state(array(
// ))
```
