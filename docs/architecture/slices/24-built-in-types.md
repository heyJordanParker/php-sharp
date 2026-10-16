# Slice 24: Built-in types and `Any`

Spec section 24, and section 5's rule for a member with no access modifier. This note is the design only. team-lead approves it before anyone builds.

## The answer first

Most of section 24 already holds on master: integer overflow, `**`, `/`, the int-to-float compile errors, the plain PHP int-to-float conversion at entry, the casts and their refusals, `null` defaults, unions, and the `Any` checks. Six examples fail, and probing every rule line found nine more gaps:

- **`Object` is refused everywhere** with "This type is not supported yet in PHP#.", and a member read on it is only PHP's warning. Section 10's `struct-parse-object` fails on the same refusal.
- **`Json.encode` does not exist.** The engine's `Sharp\Map` and `Sharp\Set` already serialize as JSON object and array, so `global.json_encode` gives every value section 24 claims, `{"counts":{}}` included. Section 23's `standard-library-import` fails on the same missing method.
- **`int|string|null` is refused as "not supported yet"** instead of the error that names `(int|string)?`.
- **A property, a constant and a named constructor with no access modifier are refused as "not supported yet".** So are a field and a constant that declare several names.
- **No naming warning exists.** `TotalIn`, `due_date`, `maxLines` and `case paid` compile with no warning. `mago lint` skips `.sharp` files, and `mago compile` prints no warnings.
- **`Int`, `Bool` and `Void` are silently accepted as types.** `capitalInt(Int value)` compiles, and the engine then stops with "Cannot use "Sharp\Int" as a type name as it is reserved". `Void` runs as `void`.
- **`Int.parse` and `Float.parse` take `Any?`,** so `Int.parse(raw)` on an unchecked `Any?` compiles. Line 3042 says they take a `string`.
- **Plain PHP passes `null` into `Any`** unchecked: a parameter, a property write and a `List<Any>` element all take it.
- **An unchecked `Any?` holding `1.0` is not `== 1`,** and `Any? == list` is refused as "not supported yet". Line 3098 says both compare by value.
- **A plain PHP function's `int` result is not converted** where a function type declares `float`: `f() == 2.0` is false for `fn () => 2` passed as `Function<float()>`. This is my reading of the leftover from #131.

Three of the six examples also need a change outside the checker. Decisions 11 and 12 list them.

The Slice builds all of it in one PR, after #135 and #136.

## Decisions team-lead approves

1. **`Object` is a type wherever a type goes.** The Slice ports the `Object` part of mago-sharp `feat/slice-lazy-and-types` commit `6cb10255b9` onto master's files: `is_slice_type` takes `Hint::Object` written `Object`, and any other spelling (`object`, `OBJECT`) is "PHP# writes `Object` for PHP's `object`.". In a `.sharp` file, `ambiguous-object-property-access` and `ambiguous-object-method-access` are errors, where PHP keeps them as warnings. The bridge lowers `Object` to PHP's `object`. The engine's `SHARP_BUILT_IN_OBJECT` already tests it in a collection and in `is`.
2. **A misspelled built-in type is an error that names the PHP# spelling.** Section 24 line 2879 makes the built-in types lowercase and every other type capitalized. The semantic check reports:
   - `Void`, `Int`, `Float`, `Bool`, `String`, `Null` and `any`: "PHP# writes the type `int`." (each with its own spelling). `Int`, `Float` and `Bool` resolve to the standard library's static classes today, so the check runs where a type names `Sharp.Int`, `Sharp.Float` or `Sharp.Bool`.
   - `array`: "PHP# has no `array`: write `List<T>` or `Map<TKey, TValue>`." and `callable`: "PHP# has no `callable`: write `Function<R(P)>`." Both are "This type is not supported yet" today. `mixed` keeps its existing error, and `iterable` is #135's.
   - Question 3 decides `null` alone, `never`, `false` and `true`.
3. **A union that holds null is an error that names the form to write.** `check_union`'s `Hint::Null` arm stops calling `report_not_supported` and reports "A union that holds null is written `(int|string)?`.", built from the union's other members. `int|null` names `int?`.
4. **A member with no access modifier is private, for properties, constants and named constructors.** This is #135's rule for methods, applied to the other three:
   - The semantic check drops the three refusals in `is_slice_property`, `is_slice_constant` and `check_named_constructor`.
   - The codex scanners give each `Visibility::Private` in a `.sharp` file outside an interface. A field already gets it (#128).
   - The bridge lowers each with `ZEND_ACC_PRIVATE`, as `Lowering::method` does after #135. One default in `modifier_flags` replaces the per-member copies where its callers allow it.
   - Question 1 decides several names in one field or constant.
5. **Names that break section 24's casing are warnings, from the semantic check.** `check_declared_name` already sees every declared name. It gains the name's kind and reports `Issue::warning` in the lint rules' message shape, "Method name `TotalIn` should be in camel case.", through `mago_casing`'s `is_class_case`, `is_camel_case` and `is_constant_case`:
   - class case: namespace parts, classes, interfaces, enums, structs, events, enum cases and type parameters
   - camel case: methods, properties, fields, parameters and locals
   - constant case: constants
   - Question 6 decides a member written `override`.

   `mago analyze` and the editor show the warnings. `mago compile` prints refusals only, so it stays silent, as it is for `unused-property` today. The alternative, running `mago lint` on `.sharp` files with only the naming rules, needs the linter to read PHP# syntax for every rule it skips, and covers no rule for enum cases, events or namespaces.
6. **`Int.parse`, `Float.parse`, `Int.tryParse` and `Float.tryParse` take a `string`,** as line 3042 says. Their `is not a string` branches go. `Checkout.sharp` passes a `string?` to `Int.tryParse`, so it narrows first. `parse.phpt`'s rows for `null`, `12`, `1.5`, `true`, `['12']` and `stdClass` become PHP's `TypeError`. `Bool.tryParse` keeps `Any?`, since section 24 does not name it.
7. **`Any` refuses null where it enters from plain PHP.** `Types::checked_text` treats `Any`, which is a `mixed` that excludes null, as a type its entry check tests. So a parameter, a property write and a `List<Any>` element get the walk that already tests `SHARP_BUILT_IN_ANY`, and null throws `TypeError`. `Any?` stays unchecked.
8. **`==` with an unchecked `Any?` side runs section 19's `==` at runtime.** `Lowering::equality` lowers it to `\Sharp\Internal\equals(lhs, rhs)`, which today compares class values and is `===` otherwise. Its `===` branch becomes `sharp_equal`, the engine's existing section 19 equality for keys, which compares an int with a float by value, a `List` element by element, and a `Map` or `Set` by entries. The analyzer then accepts `Any? == List<…>`, `Map` and `Set`, as line 3098 says, and keeps refusing an object and another unchecked `Any?`. The refusal for an object reads "`raw` is unchecked: is it the same object as `order`, or an equal value? Check it with `is` first.", where today it names `App.DatabaseEntity.op_Equality`.
9. **A call through a function type whose result holds `float` checks its result,** as a call into plain PHP does. `Types::call_check` returns the result's type text for a call of a function-typed value when that text holds `float`, and `Lowering::verified` converts the `int` to `float` as at any other entry. A PHP# lambda already returns a `float`, so the check finds nothing to convert.
10. **`Json.encode` is a standard library method.** `Sharp.Json.Json` gains `public static string encode(Any? value)`, which runs `global.json_encode(value, global.JSON_THROW_ON_ERROR | global.JSON_PRESERVE_ZERO_FRACTION | global.JSON_UNESCAPED_SLASHES | global.JSON_UNESCAPED_UNICODE)`. The flags keep `2.0` a float in the text, and write `/` and `é` as themselves, as JavaScript's `JSON.stringify` does. Today's probe gave `[1,2.5]` for `[1.0, 2.5]` and `"https:\/\/x.io\/é"`. `REPLACEMENTS` gains `json_encode`, so a bare call names `Json.encode`. Section 10 line 673's key rule comes with it:
    - The engine's `sharp_table_json_key` takes a struct key's text from its `toString()`. Today a struct key throws "A JSON object key is text, and App\Username is not".
    - The analyzer refuses `Json.encode` of a value whose type holds a `Map` keyed by a struct that declares no `toString()`, as line 673 says. Below an `Any`, only the engine sees the key, and it throws `JsonException`.
    - A class instance encodes as PHP encodes it, by its public properties. A pure enum case throws PHP's `JsonException`.
11. **Spec text the PR changes, none of it a rule:**
    - `int-to-float`'s marker becomes `statements alone 1-4,6`. With `alone` on every commented line, line 3003 runs without the `put` lines and gives `false`, or never runs.
    - `object-type` lines 3123 and 3124, and decision 035, write `global.spl_object_id(value)`. Section 8 line 498 calls a PHP built-in through `global`, and master already reports the bare call as `bare-function-call`. With `Object` built and the bare call left in, line 3123 still fails.
    - Section 24 needs no other text change.
12. **`naming-cases` needs one change to `sharp/bin/spec`, in its own PR before this one.** Its `totalIn` returns `Money`, and its body is `const lineCount = …;` then a line holding only `…`. `sharp/bin/spec` drops that line, so the checker reports "Missing return statement" on line 2906. The proposed rule: a line holding only `…` that ends a method body becomes `throw new Elided();`. `event-declarations` keeps its `…` line dropped, because a statement follows it. The alternative is spec text that returns a value. `return …;` does not work: PHP# refuses `return throw new Elided();` with `never-return`.
13. **Harness changes:**
    - `naming-cases` declares `Invoice<TLine : Line>` and `InvoicePaid(…, Money amount)` in `App.Billing`, which has no `Line` and no `Money`. The harness gains `app/Billing/Line.sharp` and `app/Billing/Money.sharp`, two empty public classes. `LineTotals.sharp` and `PriceTotals.sharp` import `App.Line` and `App.Money`, and the Slice checks that the import still wins in those files.
    - `sharp/spec-self-test.md`'s `refusal` example writes a property with no access modifier after #135. Decision 4 makes that compile, so the example writes a by-reference parameter, which stays "not supported yet".
14. **A class cast gets a parse message that names `as`.** `(Admin) user` is a refusal today, but its message is "Parse error encountered during parsing" and "A PHP# field needs a type, as in `private int count = 0;`". The parser reports "PHP# has no class casts: write `user as Admin`." where a parenthesized class name is followed by an expression, as line 3044 says.

## Questions for Jordan

Each one decides what users write. The note recommends and decides none.

1. **Can a field or a constant declare several names?** `private int a = 1, b = 2;` and `public const int C = 1, D = 2;` are refused today as "not supported yet". Sections 2, 6 and 24 say nothing. Recommended: a permanent compile error, "Declare each field on its own line.", because TypeScript class fields and Kotlin properties take one name each. The alternative builds them as PHP and C# do.
2. **Line 3042: does `Int.parse(" 12 ")` give 12?** It does today, and so do `" -12 "` and `"+007\t"`, because the library trims ASCII whitespace. The line says `parse` throws "on a string that holds anything but a number". Recommended: throw, as Kotlin's `toInt`, Rust's `parse`, Go's `Atoi` and Swift's `Int(_:)` do. C# alone trims by default.
3. **Line 2879: what does PHP# say for `null` alone, `never`, `false` and `true` as a type?** Each is "This type is not supported yet" today. Recommended: `null` alone is an error that names `T?`. `never`, `false` and `true` are errors that say PHP# has no such type, until a section gives one.
4. **Line 3098: does `raw == true` compile on an unchecked `Any?`?** The line lists a struct, an enum, a string, a number and a collection, and not a `bool`. Today it is an error, "compares by value only with a string, a number or an enum case". Recommended: it compiles, as a plain value does.
5. **Line 3110: does `raw === order` compile?** Today it is "`===` cannot compare `Any?` with `Order`: it tests whether two class instances are the same object." The spec's own message for `raw == order` asks "is raw the same object, or an equal value?", which reads as if `===` answers it. Recommended: it compiles, since identity needs no check, and gives false for a value that is not an object.
6. **Line 2922: does a member written `override` get the naming warning?** A PHP# class that overrides a plain PHP parent's `get_items()` must keep the parent's name. Recommended: no warning on a member written `override`.

## How the states were observed

- **mago:** release, built from master `cb3f0940bf` with `CARGO_PROFILE_RELEASE_LTO=off`, at `sharp/build/darwin-arm64/mago/release/mago` in slot 1.
- **Engine:** `sharp/bin/build` at `cb3f0940bf`, PHP 8.5.11 NTS DEBUG.
- **Spec run:** `sharp/bin/spec` with that pair: "Checked 200 examples, ignored 1, and 32 fail", equal to `sharp/baseline/spec`. Section 24 has 14 examples. `null-defaults`, `union-types`, `int-power`, `integer-division`, `plain-php-float-arguments`, `conversions`, `any-checks` and `unchecked-any` pass. The six listed fail:

```text
FAIL §24 naming-cases (docs/spec.md:2888)
  docs/spec.md:2902 expects no error, and the checker reports: A constant without `public`, `protected` or `private` is not supported yet in PHP#.
  docs/spec.md:2903 expects no error, and the checker reports: Cannot find type `App.Billing.Line`.
  docs/spec.md:2906 expects no error, and the checker reports: Cannot find type `Money`.
  docs/spec.md:2919 expects no error, and the checker reports: Cannot find type `Money`.
FAIL §24 nullable-unions (docs/spec.md:2953)
  docs/spec.md:2955 expects a compile error, and the checker reports: This union that holds null is not supported yet in PHP#.
FAIL §24 int-to-float (docs/spec.md:2994)
  docs/spec.md:3003 never ran
FAIL §24 array-object-replacements (docs/spec.md:3056)
  line 7 of the generated app/Example.sharp, from the marker's setup or wrapper: This type is not supported yet in PHP#.
  docs/spec.md:3059 expects no error, and the checker reports: Method `Json.encode` does not exist.
FAIL §24 json-encoding (docs/spec.md:3070)
  docs/spec.md:3072 expects no error, and the checker reports: Method `Json.encode` does not exist.
  (and 3074, 3076, 3078 the same)
FAIL §24 object-type (docs/spec.md:3120)
  docs/spec.md:3123 expects no error, and the checker reports: This type is not supported yet in PHP#.
  docs/spec.md:3126 expects no error, and the checker reports: This type is not supported yet in PHP#.
  docs/spec.md:3128 expects a compile error, and the checker accepts it
```

- **Probes:** the checker probes are in `/Users/jordan/.claude/jobs/6affb5f6/tmp/slice-built-in-types/ws/app/Probe/`, a copy of `sharp/bin/spec`'s harness project, compiled by `compile.sh ws` (`mago compile`, or `mago analyze` for warnings) with `MAGO_REPORTING_FORMAT=emacs`. The engine probes are in `run/app/Probe/`, compiled the same way and run by `run.php run probes/<name>.php` on the engine. Every message and value below is their output.

# Every rule of section 24 and section 5's member rule, its state and its mechanism

The states are **works**, **refused** (rejected, with the message noted), **silently accepted** (code the spec refuses compiles), and **wrong** (it compiles and runs differently from the spec).

## Built-in types and names

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2879 | `int`, `float`, `bool`, `string`, `void` are lowercase | Works for the lowercase spellings | None new |
| 2879 | `Int`, `Float`, `Bool` are not types | Silently accepted: `capitalInt(Int value)` compiles, and the engine stops at load with "Cannot use "Sharp\Int" as a type name as it is reserved". `Float` gets "expected `Float`, but found `float`" | Semantics (decision 2) |
| 2879 | `Void` is not a type | Silently accepted: `public Void capitalVoid()` compiles and runs as `void` | Semantics (decision 2) |
| 2879 | `String`, `any` are not types | Refused as a missing class: "Cannot find type `String`.", "Cannot find type `any`." | Semantics names the spelling (decision 2) |
| 2879 | `object`, `array`, `callable` are not PHP# types | Refused: "This type is not supported yet in PHP#." | Semantics names the PHP# type (decisions 1 and 2) |
| 2879 | `mixed` is not a PHP# type | Works: "PHP# has no `mixed`: write `Any?`, or `Any` for a value that is never null." | None new |
| 2879 | `null` alone, `never`, `false`, `true` | Refused: "This type is not supported yet in PHP#." | Question 3 |
| 2879, 2747 | A declared type starts with a capital letter | Works: "`badCase` must start with a capital letter: …" for a class, an enum and an event | None new |
| 2883 | Type parameters are PascalCase | Works as an error: "A type parameter's name is `T`, or `T` and a capital letter, as in `TItem`." | None new |
| 2883–2885, 2922 | A name that breaks the casing is a warning | Silently accepted: `NamingBad` with `TotalIn`, `PendingLines`, `pending_lines`, `due_date`, `maxLines`, `Max_Lines`, `LineCount`, `line_count`, `the_currency`, and `Bad_Case` with `case paid`, give no warning under `mago analyze` | Semantics warnings (decision 5, question 6) |

## `null` and unions

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2924 | A type holds null only with `?` | Works: `string s = null`, a `null` return, `take(null)` and `take(maybe)` are errors | None new |
| 2926 | A `null` default needs `?` | Works: `null-defaults` passes, and a property default and a parameter default are errors | None new |
| 2935 | A union is written inline | Works: `union-types` passes | None new |
| 2946 | `is` narrows a union | Works | None new |
| 2947 | `List`, `Map` and `Set` can't share a union | Works: "`List<int>` and `Map<string, int>` both run as a PHP array, so a union can't hold both." | None new |
| 2948 | Domain alternatives stay enums and sealed interfaces | Prose | None |
| 2950 | `int\|string\|null` is a compile error that names `(int\|string)?` | Refused: "This union that holds null is not supported yet in PHP#." on a return, a local, a field and `List<int\|null>` | Semantics (decision 3) |
| 2954 | `(int\|string)?` compiles | Works | None new |

## Numbers

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 2958 | Integer overflow throws `ArithmeticError` | Works: `+`, `-`, `*`, unary `-`, `++`, `--`, `+=`, `*=` and `2 ** 64` each throw "Integer overflow" | None new |
| 2958 | `<<` drops bits, a negative shift throws | Works: `1 << 64` is 0, `MAX << 1` is -2, `1 << -1` and `1 >> -1` throw "Bit shift by negative number" | None new |
| 2960 | `int ** int` is an `int`, a negative exponent throws, a float gives a float | Works: `int-power` passes, `2 ** -1` throws at runtime, `2.0 ** -1` is 0.5 | None new |
| 2974 | `/` on two ints truncates toward zero | Works: `integer-division` passes, `-7 / 2` is -3 | None new |
| 2983 | Division by zero throws `DivisionByZeroError` | Works for `/`, `/=` and `%` | None new |
| 2984 | `PHP_INT_MIN / -1` throws `ArithmeticError` | Works, through `/` and `/=` | None new |
| 2986 | `/` follows the operand types the checker sees | Works: `int\|float / int` with 7 gives 3.5 | None new |
| 2988 | An `int` where a `float` is expected is a compile error | Works for an initial value, a constant, a return, a default, a local, a property write, an argument, a list and map element, `Box<float>.put`, `float?`, and a lambda for `Function<float()>`. `Set<float> set = [1]` is "Invalid assignment to `set`: it is declared as `Set<float>`." | None new |
| 3003 | `cell.get() == 2.0` is true | Not reached: "docs/spec.md:3003 never ran" | Marker (decision 11) |
| 3006 | A literal `0` is an `int` | Works: "`int` is not `float`: write 0.0" | None new |
| 3007 | Mixed arithmetic gives a `float` | Works: `float m = count * 1.5` and `a += count` on a float compile | None new |
| 3008 | A plain PHP `int` becomes a `float` | Works for a parameter, `float?`, a `List<float>`, a `Map<string, float>`, a `Set<float>`, a `List<float\|string>`, a property write, a `List<float>` property and `Box<float>.put(2)` from plain PHP. `int\|float` keeps the int, as PHP picks a union member | None new |
| 3008 | The same through a function type | Wrong: `callResultIsTwo(fn () => 2)` with `Function<float()>` gives false | Bridge (decision 9) |
| 3018–3020 | `plain-php-float-arguments` | Passes, but its harness `Cell` is plain PHP, so line 3019 runs no PHP# code | Engine test with a PHP# generic class |

## Converting values

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 3040 | `(int)`, `(float)` between numbers, `(string)` of a number | Works: `conversions` passes. `(int) text`, `(float) text`, `(string) flag`, `(int) flag`, `(int) raw`, `(string) order` and `(string) text` are each "`(int)` converts only a number, but this is `string`." | None new |
| 3041 | `(int)` truncates, and throws for a too-large float or NaN | Works: `(int) -2.9` is -2. `1e20`, `NAN` and `INF` throw "The float NAN is not representable as an int" | None new |
| 3042 | `Int.parse` and `Float.parse` take a `string` and throw on anything but a number | Wrong: they take `Any?`, so `Int.parse(raw)` and `Int.parse(request.input("qty"))` compile. They throw on `"abc"`, `"12abc"`, `""` and `"1.5x"`, and trim whitespace: `" 12 "` gives 12 | Library (decision 6, question 2) |
| 3043 | `tryParse` takes a `string` and gives null where `parse` throws | Wrong as above. Gives null on `"abc"` and `"1.5"` | Library (decision 6) |
| 3044 | A class narrows only with `as` or `as … ?? throw` | Works. `(Admin) user` is refused with "Parse error encountered during parsing" and "A PHP# field needs a type" | Parser message (decision 14) |
| 3045 | No user-defined conversion operators | Works: "`operator int` cannot be declared: only `+ - * / % **`, unary `-`, `==` and `<=>` can." | None new |
| 3046, 3047 | `(bool)`, `(array)`, `(object)` and the cast aliases don't exist | Works: each names its replacement, as "PHP# has no `(array)`: write a struct's `parse(order)` for an object, or `List.wrap(order)` for a value." | None new |

## Replacing `(array)` and `(object)`, and JSON

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 3053 | `Object row = Rows.withId(1)` | Refused: "This type is not supported yet in PHP#." | Decision 1 |
| 3057 | A struct's `parse` reads an object | Works in section 10. Not reached here | None new |
| 3059 | `Json.encode(payload)` | Refused: "Method `Json.encode` does not exist." | Library (decision 10) |
| 3064 | A JSON object is a `Map` literal or a struct | Works at runtime: `global.json_encode` of a struct gives `{"id":1,"number":"A-1"}` | None new |
| 3065 | A `Map` is an object and a `List` an array wherever it is | Works at runtime: `{}`, `[]`, `{"counts":{}}`, `{"counts":[]}`, and `{"0":"a","1":"b"}` for `[0: "a", 1: "b"]`. An enum key gives `{"paid":1}` | `Json.encode` (decision 10) |
| 10:673 | A struct key's JSON text is its `toString()`, and no `toString()` is a compile error | Wrong: "JsonException: A JSON object key is text, and App\Username is not" at runtime, and no compile error | Engine and analyzer (decision 10) |

## `Any` and `Object`

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 3081 | `Any` holds no null | Works in PHP#: `Any value = record.untyped(null)` is an error | None new |
| 3081 | The same where plain PHP passes a value | Wrong: `take(null)`, `takeList([null])` and `held = null` from plain PHP all succeed | Bridge (decision 7) |
| 3081 | `Any` must be checked before use | Works: a property read, a method call and an index on `Any?` and `Any` are each an error. `any-checks` passes | None new |
| 3097 | `x ?? y` gives `Any` | Works | None new |
| 3098 | `==` against a string or an enum compiles and compares by value | Works | None new |
| 3098 | The same against a struct | Works: true for an equal `Username` | None new |
| 3098 | The same against a number | Wrong: an `Any?` holding `1.0` is not `== 1`, and one holding `1` is not `== 1.0` | Bridge and engine (decision 8) |
| 3098 | The same against a collection | Refused: "`==` on a collection is not supported yet, so it cannot compare `Any?` with `List<int>`." | Decision 8 |
| 3098 | The same against a `bool` | Refused: "Left operand of `==` is `Any?`, which compares by value only with a string, a number or an enum case." | Question 4 |
| 3099 | `==` against an object is an error | Works, through `App.DatabaseEntity.op_Equality`'s argument check for a class with `operator ==`, and "`User` declares no `operator ==`" otherwise | Analyzer message (decision 8) |
| 3099 | `==` against another unchecked `Any?` is an error | Works | None new |
| 3100 | `${x}` is an error until checked | Works for `Any?`, `Any` and `Object` | None new |
| 3110 | `raw === order` | Refused: "`===` cannot compare `Any?` with `Order`" | Question 5 |
| 3115 | Plain PHP `mixed` or untyped arrives as `Any?` | Works: `getAttribute()`, an untyped method and an untyped property each need `as` | None new |
| 3117 | `Object` holds any object, compiles to `object`, `Object?` adds null | Refused in every position | Decision 1 |
| 3117 | `Object` has no members until `is` | Silently accepted: `source.id` and `source.run()` are PHP's warnings only | Analyzer (decision 1) |
| 3117 | `Object` passes where `Any` is expected | Not reachable | Decision 1 |
| 3117 | A plain PHP `object` arrives as `Object` | Works in messages: "expected `Object`, but found `Any`" | None new |
| 3123 | `spl_object_id(value)` compiles | Refused by section 8's rule: "A plain PHP function is called through its namespace: write `global.spl_object_id(…)`." | Spec text (decision 11) |
| 3133 | `Ids.identity(5)` is an error | Works: "expected `Object`, but found `int`" | None new |

## Section 5: a member with no access modifier is private

| Line | Rule | State on master | Mechanism |
|---|---|---|---|
| 5:155 | A field | Works: `int plain = 0;` and `static int counter = 0;` compile as private (#128) | None new |
| 5:155 | A method | Refused on master, built by #135 | None here |
| 5:155 | A property and a computed property | Refused: "A property without `public`, `protected` or `private` is not supported yet in PHP#." | Decision 4 |
| 5:155 | A constant | Refused: "A constant without `public`, `protected` or `private` is not supported yet in PHP#." | Decision 4 |
| 5:155 | A named constructor | Refused: "A named constructor without `public`, `protected` or `private` is not supported yet in PHP#." | Decision 4 |
| — | A field or a constant that declares several names | Refused: "A field declaring several names is not supported yet in PHP#.", and the same for a constant | Question 1 |

## Crossings a real app makes

- **`Any?` from Eloquent's `getAttribute()`** needs `as` on every read. That is line 3081's rule working, and `record.getAttribute("name") as string ?? ""` gives `'Ada'`.
- **A plain PHP `array` assigned to a typed local** is a compile error: `Map<string, Any?> attributes = record.toArray();` gives "Invalid assignment to `attributes`: it is declared as `Map<string, Any?>`." `as Map<string, Any?>` is the form that compiles.
- **An empty or int-keyed plain PHP array entering a `Map<string, Any?>`** is checked and converted, as decision 027 says: `count([])` is 0, `[0 => 'x']` arrives with the key `'0'`, and `['a', 'b']` arrives as a `Map` of two entries. The probe the task cites predates #123.

# Existing work, and what the Slice takes from it

- **mago-sharp `feat/slice-lazy-and-types`, commit `6cb10255b9` (unmerged).** It holds `Object`: `is_slice_type`, the "PHP# writes `Object`" error, the two ambiguous-access errors in `.sharp` files, and five analyzer tests, two semantics tests and one bridge test. #135 ports the same branch's `yield` and `Iterable` and leaves `Object` out. The Slice ports the `Object` part and its tests.
- **php-sharp `feat/slice-lazy-and-types`, commit `a4fb216fa3` (unmerged).** `object.phpt` with `Ids.sharp` and `Ids.inc`. The Slice ports them as the engine test of `Object`. `typeof_value.phpt` is section 25's, which `Class.of` replaced.
- **#135, `feat/collections` (open).** A method with no access modifier is private: `scan_method`'s `Visibility::Private`, the dropped refusal in `is_slice_method`, and `Lowering::method`'s `ZEND_ACC_PRIVATE`. Decision 4 applies the same three changes to the other member kinds. #135 also rewrites `sharp/spec-self-test.md`'s `refusal` example, which decision 13 rewrites again.
- **mago-sharp #128 and php-sharp #94 (merged).** A field with no modifier is private: the property scanner's `None if is_sharp => Visibility::Private` and `Lowering::property`'s default. Decision 4 follows them.
- **mago-sharp #129 and php-sharp #92, #95 and #100 (merged).** The int-to-float compile errors and decisions 073 and 074. Everything in "Numbers" that works comes from them.
- **#102 (merged).** `sharp_type_accepts`, which converts a plain PHP int at entry, and `SHARP_BUILT_IN_ANY` and `SHARP_BUILT_IN_OBJECT` in the type walk. Decisions 7 and 9 reuse them.
- **#123 (merged).** `Sharp\Map` and `Sharp\Set` as engine objects with `jsonSerialize`, and `sharp_equal`. Decisions 8 and 10 reuse both.
- **#131 (merged).** `sharp_type_function_fits`, which decides `is Function<…>`. Decision 9 leaves it as it is.
- **mago-sharp #16, #26, #3 and #32, php-sharp #46, #42, #33 and #50 (merged).** Unions, `Any`, the number casts with `Int.parse`, and the refusals of the removed casts. The `int|string|null` refusal decision 3 replaces comes from #16.
- **Decisions 009, 035, 039, 048, 059, 073 and 074.** The rulings this Slice builds. Decision 009 says `Int.parse` takes `Any?`, and line 3042 since says `string`. The spec decides.

# Boundaries

- **#135, collections.** It owns `iterable` and `Iterable<T>`, the method default, and section 19's `==` between two collections. This Slice adds `Any? == collection` through the runtime `sharp_equal`, and #135 does not touch it. Both change `semantics/…/sharp.rs`, `analyzer/tests/sharp.rs`, `sharp-bridge/src/lower.rs` and `sharp/spec-self-test.md`, so this PR merges `origin/master` after #135 lands.
- **#136, hosts.** It changes `docs/spec.md`, `ext/sharp/sharp.c` and `analyzer/tests/sharp.rs` in unrelated places. A merge only.
- **Section 10, structs.** Line 673's JSON key rule lands here with `Json.encode` (decision 10). `struct-parse-object` leaves `sharp/baseline/spec` here, because it fails only on `Object`.
- **Sections 8 and 23.** `standard-library-import` fails only on `Json.encode`, so it leaves the list here. `standard-library-calls` also needs `Math.max`, so it stays.
- **`Json.decode`** is "specified with the standard library" (line 3115). It gives a PHP array for a JSON object, so `Json.encode(Json.decode("{}"))` gives `[]`. The Slice does not change it.

# Public names this Slice adds or changes

| Name | Where | What it is |
|---|---|---|
| `Json.encode(Any? value)` | `sharp/composer/library/Sharp/Json/Json.sharp` | The spec's own name. Decision 10 |
| `Int.parse`, `Int.tryParse`, `Float.parse`, `Float.tryParse` | `sharp/composer/library/Sharp/{Int,Float}.sharp` | Each parameter becomes `string`. Decision 6 |
| `\Sharp\Internal\equals` | `ext/sharp/sharp.c` | Same name and signature. Its non-class-value branch runs `sharp_equal`. Decision 8 |
| Warning messages | `semantics` | "Method name `TotalIn` should be in camel case.", and the same shape for each kind. Decision 5 |
| `App.Billing.Line`, `App.Billing.Money` | `sharp/harness/app/Billing/` | Two new harness classes. Decision 13 |

No new issue code, CST node, crate or engine opcode. The semantics crate gains a dependency on the workspace crate `mago-casing`.

# Files

```text
docs/
├── spec.md                                   <- int-to-float's marker; global.spl_object_id on lines 3123-3124 (decision 11)
├── decisions/035-object.md                   <- global.spl_object_id
└── architecture/slices/24-built-in-types.md  <- this note
sharp/mago/crates/
├── syntax/src/parser/…                       <- "PHP# has no class casts: write `user as Admin`." (decision 14)
├── semantics/
│   ├── Cargo.toml                            <- mago-casing
│   └── src/internal/checker/sharp.rs         <- Object; misspelled built-in types; the nullable-union error;
│                                                property, constant and named constructor without a modifier;
│                                                question 1's several names; naming warnings in check_declared_name
├── codex/src/scanner/
│   ├── property.rs                           <- a property with no modifier is Private
│   ├── class_like_constant.rs                <- a constant with no modifier is Private
│   └── function_like.rs                      <- a named constructor with no modifier is Private, after #135's method rule
├── analyzer/src/
│   ├── resolver/{method,property}.rs         <- a member of Object is an error in a .sharp file
│   ├── expression/binary/comparison.rs       <- Any? == collection compiles; the Any? == object message
│   ├── expression/call/function_call.rs      <- REPLACEMENTS row json_encode
│   └── invocation/…                          <- Json.encode of a Map keyed by a struct without toString()
└── sharp-bridge/src/
    ├── lower.rs                              <- Object lowers to object; private constants and named constructors;
    │                                            == with an unchecked Any? side lowers to \Sharp\Internal\equals
    └── lower/types.rs                        <- checked_text checks Any; call_check checks a float result of a function value
ext/sharp/sharp.c                             <- Sharp\Internal\equals runs sharp_equal; a struct key's JSON text is toString()
sharp/composer/library/Sharp/
├── Json/Json.sharp                           <- encode
├── Int.sharp                                 <- parse and tryParse take a string
└── Float.sharp                               <- parse and tryParse take a string
sharp/harness/app/Billing/{Line,Money}.sharp  <- new: naming-cases' types
sharp/spec-self-test.md                       <- the refusal example writes a by-reference parameter
sharp/baseline/spec/                          <- §24's six entries, §10/struct-parse-object and §23/standard-library-import removed
Zend/tests/sharp/                             <- added: Ids.sharp and Ids.inc, object.phpt, any_entry.phpt, any_equality.phpt,
                                                 float_function_result.phpt, members_private.phpt;
                                                 changed: library_json.phpt (encode), parse.phpt and Checkout.sharp (string only)
```

`docs/spec.md` changes only where decision 11 says, and where Jordan's answers change a line.

# The tests

Each rule gets one test, with each form as a row, watched failing before its code.

- **`crates/semantics/tests/sharp.rs`:**
  - `Object` and `Object?` in every type position, and `object` and `OBJECT` as errors (ported)
  - one row per misspelled built-in type: `Void`, `Int`, `Float`, `Bool`, `String`, `Null`, `any`, `array`, `callable`
  - the nullable union on a return, a parameter, a local, a field and a type argument, `int|null`, `null|int`
  - a property, a computed property, a constant and a named constructor without a modifier compile
  - question 1's form
  - one warning row per name kind, and one row each that the rule's own casing gives no warning
- **`crates/codex` scanner test:** a property, a constant and a named constructor without a modifier are `Private` in a `.sharp` file and `Public` in a `.php` file.
- **`crates/analyzer/tests/sharp.rs`:**
  - `Object`'s member read and call are errors, `is` narrows it, a plain PHP `object` arrives as `Object`, `Object` passes to `Any`, `5` to `Object` is an error (ported)
  - reaching a private property, constant and named constructor from another class is an error
  - `Any? ==` a collection compiles, and `==` an object gives the new message
  - `Int.parse(raw)` on an `Any?` is an error
  - `Json.encode` of a `Map` keyed by a struct without `toString()` is an error, and with one compiles
  - a bare `json_encode(…)` names `Json.encode`
- **`crates/sharp-bridge/tests/lower.rs`:** `Object` lowers to `object`; a constant and a named constructor without a modifier carry `ZEND_ACC_PRIVATE`; `==` with an unchecked `Any?` side lowers to `\Sharp\Internal\equals`; a call of a `Function<float()>` value is verified; an `Any` parameter carries its check.
- **`Zend/tests/sharp/`, each in every opcache mode:**
  - `object.phpt`: `Ids.identity`, `either` and `describe` from plain PHP, and `TypeError` for `5` and `null`
  - `any_entry.phpt`: `null` from plain PHP into an `Any` parameter, an `Any` property and a `List<Any>` element throws `TypeError`, and `Any?` takes it
  - `any_equality.phpt`: an `Any?` holding `1.0` is `== 1`, one holding `[1, 2]` is `== [1, 2]`, a `Map` is `==` regardless of order, a struct by value
  - `float_function_result.phpt`: a plain PHP `fn () => 2` passed as `Function<float()>` gives `2.0`, and `f() == 2.0` is true
  - `members_private.phpt`: plain PHP reading a private property or constant, or calling a private named constructor, gets PHP's `Error`
  - `library_json.phpt`: every value of `json-encoding` and `array-object-replacements`, a numbered `Map`, an enum key, a struct key through `toString()`, a struct key without it throwing `JsonException`, `2.0`, `/` and `é`
  - `parse.phpt`: the string rows unchanged, the non-string rows as `TypeError`, and question 2's rows
- **Plain PHP:** `mago analyze` of `build/gen_stub.php`, `run-tests.php` and the SDK's sources gives the same issues before and after, as #135 measured it.
- **Spec:** `sharp/bin/spec` passes every section 24 example, and `sharp/bin/spec --completes` passes on `Completes: §24`.

# The PR

One php-sharp PR on `feat/built-in-types`, after #135 and #136, and after decision 12's `sharp/bin/spec` PR. It merges `origin/master` once they are in. Its body says `Completes: §24` and lists the two harness files. It removes eight entries from `sharp/baseline/spec`: section 24's six, `§10/struct-parse-object` and `§23/standard-library-import`.
