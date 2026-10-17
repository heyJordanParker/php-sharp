# Slice: generics R3 to R5

R1 and R2 are php-sharp #102 and mago-sharp #152. This note covers what the one R3–R5 PR per repo adds, and fixes the names other Slices build against before the code lands.

## R3c: the one check of a value against a type

Queue 19 rules that PHP# has one check of a value against a type: the engine walk in `sharp_type_accepts`. `is`, `as`, a `match` arm, a generic `catch`, every entry from plain PHP and struct `parse` all call it. It answers "is this value of this type". `sharp_type_fits` stays separate and answers "is this type within that type", by variance. `sharp_type_accepts` hands an object's type arguments to `sharp_type_fits` and never compares them itself, so every type-argument rule lives in one function.

### What the walk checks

- A `List<T>`: an array that `array_is_list` holds, and every element by `T`.
- A `Map<K, V>`: every key by `K` and every value by `V`. A `string` key also takes an int key, since PHP turns `"5"` into `5`. A backed enum key takes its backing value.
- A `Set<T>`: every value by `T`.
- `T?`, a union and an intersection, by their members, nested to any depth.
- An object of a class: as R2 checks it, its type arguments through `sharp_type_fits`.
- An empty array is every `List`, `Map` and `Set`.
- An `int` where a `float` is expected becomes a `float`: a parameter, an element of a `List<float>`, a value of a `Map<K, float>`, an element of a `Set<float>`. The walk separates an array only when it finds an int to convert. `is` never converts, and an int is never `is float`.

### The C function

```c
/* The bad values one check found. The walk adds each one under the path to it, in the order it meets them. */
typedef struct {
	/* An array: the path to a bad value, then its problem, `expected int, got string 'abc'`. */
	zval *problems;
	/* The path to the value the walk is at, as `addresses[2]`. The walk appends to it and restores it. */
	smart_str path;
} sharp_type_problems;

/* Whether `value` is of `type`. An int it accepts for a `float`, at any depth, becomes a float in place. With no
 * `problems`, it stops at the first bad value. With `problems`, it walks every value and adds each bad one. False when
 * a lazy proxy's initializer throws, with the exception left in EG(exception). */
bool sharp_type_accepts(const sharp_type *type, zval *value, sharp_type_problems *problems);
```

`value` loses its `const`, since the walk converts in place. Today's callers keep their behaviour with `problems` NULL:
- `sharp_type_check_arguments`, the parameter check.
- `sharp_type_verify`, a plain PHP call's result.
- `sharp_property_accepts`, a property write.

A value that holds without converting an int is taken in one walk. Only a value with an int in a `float`'s place is walked again to convert it. Each `sharp_type` carries the built-in type it names, so the walk reads no type text.

### Where a value from plain PHP is walked

- A parameter of a method plain PHP calls, as R2 checks an object.
- The result of the project's own plain PHP function or method, such as a `list<int>` a Laravel docblock claims. Queue 29 rules that a docblock is a claim no engine checks. PHP's internal functions and the `Sharp\` library are trusted, because the engine and the checker already guarantee their types. A result whose type only a docblock can write, such as `array<array-key, int>`, is not walked, since the engine reads the check as PHP#.
- A whole value written to a property, as the next section says.

A 10,000-element `List<int>` from plain PHP costs about 88 instructions per element in a release build, interpreter, opcache and tracing JIT alike. That is 0.87 times one plain PHP `foreach` that sums the list in the interpreter, and 2.0 times under the tracing JIT. A `List<float>` that holds ints costs about twice that, since it is walked again to convert.

### A property's check (Queues 31, 33 and 34)

Every PHP# property checks through PHP's typed property path, one path for every property. The bridge wraps a property's PHP type, a promoted parameter's included, in a `SHARP_TYPE_ARGS` with its PHP# type text, whatever the property's visibility, and the compiler keeps the text in `zend_property_info.sharp_type` and sets the property's `ZEND_ACC_SHARP_TYPED` flag. The text may name the class's type parameters as `$i`, as `Box<$0>?` does.

- PHP's own `zend_verify_property_type` and its inline twins are upstream's again, and know nothing of PHP#. Every PHP# check sits behind one test of `ZEND_ACC_SHARP_TYPED`, which a plain PHP property fails first, and only then finds the object.
- `zend_verify_object_property_type(info, value, strict, object)` is the entry for a write that has its object, or NULL: `zend_std_write_property`, `object_properties_init_ex`, `zend_update_static_property_ex` and `ReflectionClass::setStaticPropertyValue`. The VM's `zend_assign_to_typed_prop` and its compound and increment twins find the object from the slot after the flag test.
- The JIT's six typed property helpers test the flag inside the test of `info->flags` they already make for readonly and asymmetric visibility. A PHP# property leaves there for the helper's `zend_jit_sharp_*` twin, so the helper a plain PHP property runs is upstream's, instruction for instruction, save the mask.
- A write with no object, such as through PHP's API, accepts any value for a type that names a type parameter.
- `sharp_property_accepts` spells the text for the object's class and type arguments and walks the whole value, so an int in a `float`'s place becomes a float. It throws its own `TypeError`, which names the type it spelled: "Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?".
- A write from a `.sharp` file is never checked, since the checker proved it. A hook writing its own property's storage counts as the code that set the property, so a write through a `set` body is checked on the value it stores.
- The set-hook check that `VERIFY_TYPE_ARGS [1]` marked is gone.

A reference to a property whose type names a type parameter is refused, since the reference would hold no type to check a later write against: "Cannot take a reference to `Checks\Rack::$own`: its type depends on the object's type arguments". `sharp_property_refuses_reference` runs wherever the engine makes a property a reference:
- `&$rack->own`, in the VM's and both JITs' fetch.
- `$rack->own = &$box`.
- `foreach ($rack as &$value)`, over a plain object and over one with hooks.
- `foreach (new ArrayIterator($rack) as &$value)`.
- `array_walk($rack, …)`.
- `unserialize` of an `R:` back-reference to the property, or of a property that already holds a reference.

A closed type's reference keeps PHP's typed reference check through `sharp_type`.

`unserialize` checks each typed property it fills, as PHP has since 7.4, through `zend_verify_prop_assignable_by_ref` in `process_nested_object_data`. A closed PHP# type checks there. A type that names a type parameter checks once the object's last entry is read, since `serialize` writes the type arguments after the properties: `sharp_object_open_properties_accept` spells each such property's type for the object and refuses "Cannot assign App.Box<Checks.Customer> to property Checks\Rack::$own of type App.Box<App.Order>?".

### What a property's check leaves unchecked

These are boundaries, accepted in Queue 34:
- An append or an element write, `$rack->items[] = $x`. It writes an element, not the property.
- `ReflectionProperty::setRawValue`, which skips PHP's own typed property check.
- A write through a reference to a closed collection type. PHP's typed reference check runs, and the walk converts an int to a float in the referenced value itself.
- `foreach (new ArrayObject($rack) as &$value)`. PHP itself gives this reference no type source, so upstream PHP lets it write a string into a plain `int` property and fails an assertion in a debug build. PHP# leaves it as PHP has it.

### Cost on plain PHP

Instructions per pass, release build, median of 5 runs, this head over the round 2 head. Both heads were measured the same hour.

```text
typed: an object written to a    interpreter  486.2/486.3 = 1.000
  typed property and a typed     opcache      486.1/486.3 = 1.000
  static                         tracing JIT  409.4/413.5 = 0.990
scalar: `$p->x = $i` and         interpreter  366.1/371.2 = 0.986
  `$p->y += 1` on int            opcache      366.2/371.1 = 0.987
  properties                     tracing JIT  248.3/252.6 = 0.983
```

Under the tracing JIT, each typed property write calls a helper. Its plain path matches round 2's instruction for instruction, save the mask constant `0x5c80` for `0x1c80`, and saves one register pair fewer. Which code accounts for the rows under 1.000 is not traced.

### opcodes.phpt

`opcodes.phpt` proves that PHP# compiles to the opcodes its PHP twin compiles to. Queue 29 rules that a `SHARP_RECV_TYPE_ARGS` with no result is a difference the design intends: it checks the parameters of a call from plain PHP that the twin's types can't. The test drops exactly those lines, renumbers the ops after them, and pins each dropped check text, one line per method. A row proves that a second opcode beside a check still differs from the twin. A `SHARP_VERIFY_TYPE_ARGS` on a plain PHP result is pinned line by line, as before.

`sharp_type_list_holds`, which `is`, `as`, `match` and `catch` call, runs the same walk with conversion off. `value is List<int>`, `value as Map<string, float>`, a `Set<string>` arm, `value is Any` and `value as int|string` run as `instanceof` a name spanning the type, as a generic class's test does, so the checker narrows the value to the type written and the bridge lowers the engine's test. A catch takes only a class, so a collection there stays an invalid catch type.

Two types have no test:
- `Class<T>`, since a class value is a string.
- A function type. A function value keeps neither its result type nor a parameter type the checker inferred, so no test tells `Function<int(int)>` from `Function<string(int)>`. The checker refuses the test, alone or in a union, and says why.

An `as`, or an `is` of a type, that the value always passes says so in the words the code writes: "`order` is already an `Order`, so `as Order` changes nothing." It replaces PHP's report on the conditional the test runs as.

### The function generated PHP calls

```php
namespace Sharp\Internal;

/**
 * Whether $value is of the PHP# type $type. An int it accepts for a float, at any depth, becomes a float in $value.
 * Each bad value adds one entry to $problems: its path, which starts with $path, then its problem.
 */
function accepts(string $type, mixed &$value, string $path, array &$problems): bool;
```

Code only the bridge calls lives in `Sharp\Internal\`, as `requireNative` does, so no public name is taken. `ext/sharp/sharp.stub.php` declares it beside the events Slice's `emit`, and startup registers both. `sharp/bin/native` writes only the native library's functions.

- `$type` is a type text as the bridge prints it, such as `List<App.Address>` or `int?`. It is spelled for the calling frame, as `ZEND_SHARP_VERIFY_TYPE_ARGS` spells its text, so a generic struct's `parse` can pass `List<$0>`.
- `$path` is where `$value` sits in the input, `addresses` for a parameter. The walk adds `[2]` for a `List` element and `["vip"]` for a `Map` value.
- `$problems` is the `Map<string, string>` that `Sharp\InvalidFields` holds, path to problem. The walk only adds to it.
- It returns false when it added a problem.

Struct `parse` calls it once per parameter. A parameter whose type holds a struct, as in `List<Address>`, gets each element through `Address.parse` with the path `addresses[2]` first, as structs decision 5 reads nested structs. The walk itself takes only a struct object where a struct is expected.

### The problem text

The walk writes the text that structs' standard-library choice 2 proposes, so `parse` builds none of its own:
- `expected <type>, got <value>`, with each class by its full dotted name, as `$type` and every engine message spell it: `int`, `string?`, `List<App.Address>`.
- The value is `null`, `bool true`, `int 5`, `float 1.5`, `string 'abc'`, `List` or `Map` for an array, and `object App.Order`. An object of a generic class adds its type arguments: `object App.Box<int>`. A string longer than 40 bytes is cut with `…`.
- An array that is no list, where a `List` is expected, is `expected List<int>, got Map`.
- A bad `Map` key is reported at its entry's path: `prices["vip"]: key expected int, got string 'vip'`.

## R4: a class with type arguments, read as an expression

`Counter<Order>.count`, `Counter<Order>.reset()`, `Counter<Order>.LIMIT` and R5's class value `Box<int>` all start with a class name and its type arguments, read where an expression goes. Queue 25 approves one syntax node for all of them. It sits in `crates/syntax/src/cst/cst/instantiation.rs`, beside `Instantiation`, and takes its two fields from it:

```rust
/// A PHP# class with its type arguments, read as an expression: the `Counter<Order>` of `Counter<Order>.count`. PHP
/// never builds one.
pub struct GenericClass<'arena> {
    pub class: &'arena Expression<'arena>,
    pub type_arguments: TypeArgumentList<'arena>,
}
```

- `Expression::GenericClass` holds it. `parse_expression_continuation` builds it where it refuses the form today: after a class name, a `<` whose type argument list a `.` follows.
- A static property read, a static call and a class constant read each take it as their `class`, as they take a class name today. The analyzer resolves its class as it resolves the name, and records its type arguments as it records `new`'s.
- R5's `typeof(Box<Order>)` names its class as a type inside `typeof`'s parentheses, not where an expression goes. So `TypeOf` keeps its type arguments beside its class, as `Instantiation` keeps `new PaginatedList<Order>`'s.

## R4: each type argument's own statics (Queue 23, Option B; Queue 36)

C# gives every closed generic type its own statics, so `Counter<Order>.count` and `Counter<Customer>.count` are two values. Option B keeps them in PHP's own static storage. It adds no opcode, and neither JIT changes: both run the VM handlers it changes.

### A static method takes its class's type arguments (part 1)

A static method of a generic class has no object to read its class's type arguments from, so it takes them as its leading own ones, as a named constructor does:
- In its body the class's `TItem` is the method's `#0`, and the method's own type parameters follow it.
- `Counter<Order>.track(order)` passes `App.Order` after the arguments, as a generic call passes its own. `Orders.track(order)` passes the type arguments the header `Orders : Counter<Order>` gives `Counter`.
- A plain PHP call of `Counter::track()` passes none, so the method runs with the class's bounds, as a generic method that plain PHP calls does.

### Storage

- **One hidden static property per generic class:** `"\0<sharp>\0statics"`, flagged `ZEND_ACC_SHARP_HIDDEN`, as the instance slot `"\0<sharp>\0types"` is. The class declares no other static property for its static fields.
- **Its value is an array keyed by the type argument text:** `"App.Order"`, `"List<App.Order>"`, `"App.Order, int"`, nested type arguments included. Each entry maps a static field's name to its value.
- **The defaults live under the key `""`:** each static field's constant initial value, or null for a `T?` field with none.
- **The first use of a key copies the `""` entry,** collections included, as C# runs a closed type's static initializer on its first use. PHP separates an array on its first change, so two keys never share a collection after a change to either.

### `\Sharp\Internal\statics`

```php
// Returns the entry of $key in $class's store by reference, copying the defaults in first when the key is new.
function &statics(string $class, string $key): array {}
```

- It throws when `$class` is no generic PHP# class that declares static fields.
- `ext/sharp/sharp.stub.php` declares it beside `emit` and `accepts`. Plain PHP can call it as it can call `emit`: `Sharp\Internal\` is the one trusted boundary for both.

### Lowering

A static field access lowers to the field in its entry: `\Sharp\Internal\statics(\App\Counter::class, <key>)['count']`.
- **The key is a `SHARP_TYPE_OF` node in list mode,** `ZEND_SHARP_TYPE_OF_LIST` in its attr. Over the list text `App.Order`, `#0` or `$0, int` it gives the interned list's text, the one text `new` and part 1 spell, so equal type arguments always meet one key.
- **PHP compiles every write through the returned reference:** `=`, `+=`, `++`, `??=`, `[]=` and a nested `["k"]["j"] =`. PHP# writes no `[]=` and no nested index, since ruling 7 refuses both, so the engine rows write those two in PHP.
- **A method call on a collection field binds it to a reference first:** `(${'receiver#1'} = &…['items'])->add($x)`. The engine makes the `Sharp\Collection` receiver of a reference by keeping the reference, so the change reaches the field. A receiver it copied would lose the change.
- **In `class Orders : Counter<Order>`, `Orders.count` is `Counter<Order>.count`:** the access passes the header's type arguments, and `Orders.items` has the type `List<Order>`.

### Why the store needs no typed slot

A typed static property checks each write PHP makes. The store needs none of that:
1. The checker proves every PHP# write: each access has the field's type with its type arguments substituted, so a write of a wrong type never compiles.
2. Plain PHP can't name the storage: the static property lookup refuses a hidden property, so `Counter::$count` and the store's own name are undeclared, and no view lists the store.
3. Plain PHP can reach the store only by calling `\Sharp\Internal\statics`, the same trusted boundary as `emit`.

### Plain PHP

- A `.php` access `Counter::$count` is a non-existent property in Mago, and an undeclared static property in the engine.
- `ReflectionClass::getStaticProperties()`, `getProperties()`, `hasProperty()`, `getStaticPropertyValue()`, `get_class_vars()` and `property_exists()` neither show nor find the store.

### Rows

Each row reads back a value that only the write it tests puts there:
- `Zend/tests/sharp/type_arguments_call.phpt` runs every row twice, so the tracing JIT runs its compiled code the second time. Each round uses a type argument no code used yet, so each round sees a first use:
  - `=`, `+=` and `++` on `count`, and `??=` twice on `limit`.
  - `items.add(item)` directly and through `track`.
  - `byId["k"] = 3` on one key and `4` on `List<T>`'s, each read back from its own key.
  - `Counter<List<T>>.count` and `.items` keep their own values and the copied defaults.
  - `Orders.count += 100`, `Orders.items.add` and `Orders.track` change `Counter<Order>`'s entry, and `Orders.own()` reads it.
  - `[]=` and a nested `[0][1] =` in PHP, and `List<…>`'s `grid` keeps its default.
- `type_arguments_call_plain.phpt`: a plain PHP `Counter::track()` changes the bounds' key, `Any?`.
- `type_arguments_reflection_properties.phpt`: the views above, and `Counter::$count` and the store's name are undeclared.

## How long a run-time type lives

- **The process's tables keep each type code spells,** which its source bounds: `sharp_types`, `sharp_type_lists` and `sharp_class_values`, under `SHARP_TYPES_LOCK`. They are the engine's one process-wide mutable state, by design. Per-thread tables would intern each code type once per thread and lose the pointer equality `sharp_type_equals` takes for two persistent types.
- **The request's tables keep each type first read from input or made by a substitution,** as `unserialize` reads one and `Node<List<T>>` makes one: `SHARP_G(request_types)`, `SHARP_G(request_type_lists)` and `SHARP_G(request_class_values)`. They live as long as the PHP request, which is the app: one HTTP request under php-fpm, the whole worker under FrankenPHP's worker mode or Octane. C# keeps each generic instantiation its runtime makes for the process's life the same way. The program bounds them, and a worker reuses them on its next request.
- **Only input that invents new type texts could grow them without limit, so the first two hold at most 65,536 types read from input,** `SHARP_TYPE_INPUT_LIMIT`, which `SHARP_G(request_input_types)` counts. The third indexes the class values among them. Past it, a new type text fails in `unserialize` as other bad input does, and each type they hold keeps working. A type code or a substitution spells always interns, since the source and the program bound those. `sharp_type_intern` takes which of the three made the text, as a `sharp_type_source`. `type_arguments_limit.phpt` and the `/flood` and `/words` requests of `sharp/bin/hosts` show it.

## R5: a class value that carries its type arguments

`typeof(PaginatedList<Order>)` is a `Class<PaginatedList<Order>>`, and `new (pages)()` creates a `PaginatedList<Order>`, spec section 25. Plain PHP receives the plain class name `App\PaginatedList`, spec section 11.

### Identity

- **The value is the descriptor's own class name.** Each interned class type with type arguments, `App.PaginatedList<App.Order>`, holds its own interned `class_name` string, and `typeof` returns that string, as it did in R3c.
- **One table maps the string's address to its descriptor.** `sharp_type_intern` adds each class type with type arguments: a persistent one to `sharp_class_values` under `SHARP_TYPES_LOCK`, a request-lived one to `SHARP_G(request_class_values)`. `sharp_class_value_type` reads them.
- **The value keeps its type arguments wherever plain PHP passes it,** in an array, a closure or a property, since PHP copies an interned string by its pointer. `=== 'App\PaginatedList'` is true, and so is `typeof(Box<Order>) === typeof(Box<Invoice>)`.
- **A name plain PHP builds carries none,** such as `'App\\' . 'PaginatedList'` or a literal `'App\PaginatedList'`: no descriptor holds that string.

### `new (value)(…)`

- **Without written type arguments,** the bridge marks the `NEW` with `ZEND_SHARP_NEW_CLASS_VALUE` in its attr. `zend_compile_new` compiles a constant class as a named `NEW`. Any other value goes in op2 as a TMP, with `ZEND_FETCH_CLASS_SHARP_TYPE` in op1, and the handler calls `sharp_new_class`. It takes the descriptor's class and type arguments, a plain name's class with none, or the list `new TItem` passes.
- **`zend_optimizer_update_op2_const` refuses a `NEW`,** so neither SCCP nor the block pass turns its TMP op2 into a constant the handler would not read.
- **`new (any)<Order>()`** lowers as `new PaginatedList<Order>()` does, a `SHARP_TYPE_ARGS` around the `NEW`, so written type arguments win over those the value carries.
- **A plain name gives the class its bounds,** as an object plain PHP creates has.

### What the checker refuses

- `new (any)()` of a `Class<PaginatedList>`: "`PaginatedList` needs its type argument."
- `new (kind)()` of a `string`: "`kind` is a `string`, not a `Class`: `new (…)` creates an object from a class value."
- `new (type)()` where `TItem`'s bound holds a type that is no class: "`TItem` can stand for a type that is not a class."
- A class without a `required` constructor: "`new (type)(…)` needs a `required` constructor: the class value can hold any subclass of `Loose`, …"
- A named constructor through a class value: "`new` through a class value calls the class's constructor, so it names no other one."
- `typeof(TItem<int>)`: "A type parameter takes no type arguments: write `typeof(TItem)`."

### A class value keys no `Map` (Queue 38)

- **One check, `is_map_key` in the analyzer,** refuses a class value as a key, with or without type arguments, beside a class or an enum without a backing value. A `Map` type, a `Map` literal, and the `Map` that `groupBy` and `associateBy` infer from their key function all call it, and all say: "A `Map`'s keys are `int`, `string` or a type with an `int` or `string` backing value, and `Class<Order>` has none."
- **The semantic checker leaves a `Class<…>` key to the analyzer,** as it leaves a named key, so the type has one report.
- **A type written as a type argument in a body** gets the checks a local's type gets: `seed_type_arguments` runs `report_hint_type_arguments` on each. `new Box<Map<Class<Order>, int>>()`, `new Box<Map<Order, int>>()`, `new Box<Pair>()`, `new Box<Pair<int>>()`, `Made.boxed<Pair>()` and `typeof(Box<Pair>)` compiled before and are refused now.
- A class value is still a `Map`'s value: `Map<string, Class<Element>>`, spec section 25.

### A static member through a class value

`type.own()` and `type.count` through a `Class<Counter<Order>>` take `Order`, as `Counter<Order>.own()` does. `generic_class_type` in the analyzer reads the type arguments from a `GenericClass` or a class value alike, so a static call and a static property have one path.

**A write through a class value (Queue 38)** writes the static field of the class the value holds: `type.count = 1`, `+=`, `++`, `??=`, and a collection method such as `type.items.add(order)`. `StaticProperty::from_property_access` gives the assignment and a collection method's write-back the same `StaticProperty` the read takes, for a receiver whose type is a class value. Through a `Class<Counter<Order>>` the write is the `Counter<Order>` entry, and the bridge binds `type.items` by reference before `add`, as it binds `Counter<Order>.items`. A static read through a class value is not memoized, as a read through `Counter<Order>` is not, so a second read takes its type arguments again. The memoized read used to panic on the local's name.

### The entry check

A parameter, a plain PHP result and a property write of `Class<PaginatedList<Order>>` are checked where the value enters from plain PHP, as a `PaginatedList<Order>` object is. PHP's `string` type checks no class. `Types::checked_text` gives such a type its check text, and `sharp_class_value_is` in the walk takes a string that names `PaginatedList` or a class that extends it, whose type arguments for `PaginatedList` match: those the value carries, or else its class's bounds through its header. `App\OrderPage`, whose header is `PaginatedList<Order>`, enters. `App\PaginatedList`, a `Class<PaginatedList<SharedOrder>>` and a missing class throw `TypeError`: "must be of type Class<App.PaginatedList<App.Order>>, Class<App.PaginatedList<App.SharedOrder>> given". A `Class<PaginatedList>` and a `Class<Order>` keep PHP's `string` check.

### Rows

`type_arguments_call.phpt` runs each row twice, so the tracing JIT runs its compiled code the second time:
- `typeof(PaginatedList<Order>)` reads as `App\PaginatedList` in plain PHP, equal to `typeof(PaginatedList<SharedOrder>)`.
- `new (type)([])` creates `PaginatedList<Order>` from the value plain PHP passed back, and `PaginatedList<SharedOrder>` through a generic method's `Class<PaginatedList<T>>`.
- `new (type)<Order>([])` of a `Class<PaginatedList<SharedOrder>>` creates `PaginatedList<Order>`.
- `new (typeof(PaginatedList<TItem>))([])` and `new (typeof(TItem))(8)` in `Inspection<Order>`.
- `type.own() + type.count` through `typeof(Counter<T>)` reads `Counter<Source<Order>>`'s statics.
- `type.count = 5`, `+= 2`, `++` and `type.items.add(…)` through `typeof(Counter<Map<string, T>>)` write `Counter<Map<string, Order>>`'s statics and leave `Counter<Map<int, Order>>`'s alone: `8, 8, 1, 0`, then `8, 8, 2, 0` in the second round.
- The entry check: `App\OrderPage` enters, and a carried `SharedOrder` value, `App\PaginatedList`, `App\Missing` and `7` throw. A `Class<PaginatedList<Order>>?` property takes `typeof(PaginatedList<Order>)` and refuses `typeof(PaginatedList<SharedOrder>)`: "Cannot assign Class<App.PaginatedList<App.SharedOrder>> to property Calls\Shelf::$pages of type Class<App.PaginatedList<App.Order>>?".

### Left as they are

- `Class<…>` has no `is` test, as R3c says, and `==` between class values compares their names until Jordan rules on it.
- A `ReflectionType` prints PHP's erased type: `string` for a `Class<PaginatedList<Order>>` parameter, `App\Box` for a `Box<Order>` return.
