# 70. Reified generics in the engine

## Decision

The running program carries every type argument ([section 11](../spec.md#11-generics), decisions 7 and 29). Five slices build it on typed compilation (decision 29):

- **R1:** objects carry their type arguments from `new`, through `clone`, serialize and unserialize. Plain PHP reads
  them through `ReflectionObject::getTypeArguments()`.
- **R2:** a generic method's own type arguments ride on its call.
- **R3:** `is`, `as`, `match`, `catch` and `typeof(TItem)`.
- **R4:** static members per type argument, and variance.
- **R5:** class values.

Each part below states the approved design, then what each slice decided inside it. A later slice reads this record
before it changes the engine or the bridge.

### A. One interned descriptor per runtime type

- **The bridge prints each type the program needs as `type_text`:** full dotted names, `List<T>`, `T?`, sorted
  unions, `Function<R(P)>`. `type_text` lives in Mago's sharp-bridge `lower/types.rs` and is the one type printer.
- **A type text is a plain ZVAL string child of the node that uses it.** The parent's kind tells the engine to parse
  it. There is no attr flag. The ABI changes only through new node kinds, which `SHARP_UNIT_ABI` hashes. Type texts
  stay in the texts section, never in facts.
- **Written type arguments come from the CST. Inferred ones come from `AnalysisArtifacts.inferred_type_arguments`,**
  through `Types::type_arguments(span)` in `types.rs`. `--assert-types` guards each answer.
- **The engine parses each text once into an immutable `sharp_type` descriptor,** interned in a per-process table.
  - The table lives in persistent memory and is safe under ZTS. Descriptors outlive the request, and `run_time_cache`
    slots point into them.
  - Equal types are one pointer.
  - A descriptor holds class names, not `zend_class_entry` pointers. It resolves a class only when a check needs that
    class. Parsing `PaginatedList<Order>` never autoloads `Order`.
  - A text that names a type parameter is open. It is substituted, then interned.
  - Each site caches its resolved descriptor in its `run_time_cache` slot.

#### R1 decisions

- **`type_text(r#type, codebase)` is a free function.** It reads the codebase only for each class's declared spelling,
  as the analyzer's `display_sharp_type` does. A type it cannot write is unreachable, because only a type the checker
  accepted reaches it.
- **`Types::type_arguments(span)` answers both forms.** The analysis records the type arguments of every generic `new`
  and call by span, the written ones included (`seed_type_arguments` seeds them from the CST). So the bridge asks one
  question for both, and prints each answer with `type_text`.
- **A `new` resolves its type arguments the same way whether or not its class has a constructor.**
  `analyze_class_instantiation` resolves them after the constructor's call, from what the constructor's arguments
  bound and what `seed_type_arguments` seeded. Only a `new` that passes arguments to a class without a constructor, an
  error, records none. Before this, a class that declares and inherits no constructor recorded `mixed` for each, so
  `new Box<int>()` made a `Box<Any?>`.
- **`--assert-types` does not exist yet.** No answer has a guard. The first slice that builds the flag guards
  `type_arguments` with the others.
- **The text R1 interns is an argument list,** written as it appears between `<` and `>`: `App.Order`, or
  `App.Order, int`. The descriptor of a list is a `sharp_type` of its own kind, whose members are the interned
  argument descriptors. The hidden slot and `serialize` hold that one list.
- **The descriptors and the intern table live in `ext/sharp/sharp.c`,** behind declarations in
  `ext/sharp/php_sharp.h`. `Zend/` and `ext/standard` already include that header for the collection runtime. No new
  file.
- **The table is a persistent `HashTable` keyed by the text, guarded by a mutex under ZTS.** A site locks it once per
  request, the first time it runs. Descriptors are never freed until module shutdown.
- **A text interns for the process only when the program's source bounds it:** a closed text of a `new`, and a class's
  bounds and header texts (part B). Two kinds of text intern in two per-request tables, `SHARP_G(request_types)` and
  `SHARP_G(request_type_lists)`, which the module's post-deactivate hook frees:
  - a text first read from input, which `unserialize` reads
  - a list `sharp_type_list_of_this` spells from this's type arguments, and the list a subclass's header maps them to.
    A generic class can nest its own type parameter deeper on every call, as `Node<List<T>> deeper()` does, so these
    are bounded by how deep a request goes, not by the source. Before this rule, four requests nesting 400 levels grew
    a worker by about 1.25 MB each.
- **Every lookup reads the process table first, then the request's.** So a type the process already holds is still one
  pointer, and a new one lives for the request. `sharp_type_is_persistent()` tells them apart by the text's
  `IS_STR_PERSISTENT` flag, and only a persistent descriptor goes into a `run_time_cache` slot.
- **Untrusted input can no longer grow the process table, and its depth is capped.** `unserialize` reads a type text
  nested at most `SHARP_TYPE_INPUT_DEPTH`, 64, types deep, counting the outermost. Each nested type interns with its
  whole text, so a text's cost grows with the square of its depth: 8000 levels exhausted a 128 MB memory limit. The
  deepest text the bridge writes in `Zend/tests/sharp` is `$0, List<$0>?`, 2 deep, across 19 texts. Code's texts and
  substitutions keep no cap.
- **Two lists are one type when they are one pointer, or when either lives for the request and their canonical texts
  match.** Under ZTS one thread can intern a text for its request while another interns the same text for the process,
  so `sharp_type_equals()` compares the texts unless both live for the process.
- **An open text's site keeps a monomorphic cache keyed by this's class and this's own type arguments.** The list it
  spells depends on nothing else, so the site reads the cache before it maps anything through a header, and a call on
  the class it last spelled for costs two pointer compares. A call on another class misses, spells again and takes the
  cache. The site keeps a result only when this's own list, if any, and the result both live for the process. A result
  that lives for the request is spelled again on each call, an arena, a parse and a lookup, and leaves the cache as it
  was. A run that reads a file from the opcache file cache compiles nothing, so a closed text lives for the process only
  once its own `new` runs. Over 200000 calls on the debug build, best-of-7 CPU time, `make()` in `OrderMaker : Maker<Order>` went from
  about 2300 ns to about 420 ns, against about 460 ns for `make()` on a `Maker<Order>`.
- **The engine reads every form a type text has:** a name with its type arguments, `$i`, `T?`, `A|B`, `A & B`,
  `(A|B)?`, `(A & B)?` and `(A & B)|C`. An intersection holds classes only, sorted. Its text inside a union stands in
  parentheses, and the union sorts its members by their bare text, as Mago's `type_text` sorts them. A type text parses
  whole into an arena before the table's lock is taken.
- **A text that names a type parameter of the method's class writes it as `$` and its index, `$0` for the first.**
  - Mago's `type_text` keeps writing a type parameter by name, for the bounds. Only a `new`'s type arguments write
    `$i`, through `Parameter::Index` in `lower/types.rs`.
  - The compiler marks such a site open (part F). At run time, `sharp_type_list_of_this` replaces each `$i` with
    this's type argument i and spells the result as code would. A union flattens a union or nullable type it is given,
    `null` lifts into one `?`, `Any` takes in a whole union, members dedupe and sort, and `T??` is `T?`.
  - The indexes count the type parameters of the method's class. When this is of a subclass, its class's header
    metadata (part B) maps this's type arguments up to the method's class first, so `make()` in `OrderMaker :
    Maker<Order>` makes a `Box<App.Order>`. That map parses and interns only when the site's cache misses.
  - The new object gets its class's bounds, as one plain PHP creates, when this is not of the method's class, or when
    the text names an index this has no type argument at. A lambda bound by `Closure::bind` to another class's object
    reaches both.
  - A text read from input, and a closed text the compiler interns, never accept `$i`.

### B. Objects carry their type arguments

- **An object of a generic class has one hidden property slot,** holding an `IS_PTR` to the interned argument list. It
  costs 16 bytes per object.
- **The slot's property info carries a new flag, `ZEND_ACC_SHARP_HIDDEN`.**
- **Ruling G.1:** an object that plain PHP creates without type arguments has its bounds as its type arguments, and is
  checked on entry. PHP# never creates one. Under `PaginatedList<TItem : DatabaseEntity>`, a raw page is a
  `PaginatedList<DatabaseEntity>`: it passes a parameter of `PaginatedList<DatabaseEntity>`, and a parameter of
  `PaginatedList<Order>` throws `TypeError` (decision 67).

#### R1 decisions

- **The slot is a public declared property named `"\0<sharp>\0types"`,** the serialize key. Public, so a generic
  subclass's own declaration reuses the parent's slot instead of adding a second one. Its name starts with `\0`, so no
  PHP code can name it, and `zend_get_property_offset` refuses it by its flag.
- **Its default value is null.** An object plain PHP creates keeps null, and the first read stores the `IS_PTR` of
  its class's interned bounds in the slot, such as `App.DatabaseEntity`, or `Any?` for a type parameter without a
  bound, so the bounds are looked up once (ruling G.1). A lazy object's UNDEF slot reads as the bounds and is left
  as it is. `new` in PHP# stores the `IS_PTR` before the constructor runs. The default is never a pointer, because
  opcache stores the class in shared memory and a descriptor pointer belongs to one process.
- **A PHP# class, interface or enum with type parameters, or whose header gives type arguments, carries its class
  metadata, two type texts.** The bridge writes them as the last member of the class-like, a
  `ZEND_AST_SHARP_TYPE_ARGS(header, bounds)`, either text `NULL`. A class-like with neither text has no such member:
  - **The header** is the type arguments its header gives each generic parent and interface, sorted, each of its own
    type parameters written `$i`. `Sub<T> : Base<List<T>>, Query<Order>` writes `App.Base<List<$0>>,
    App.Query<App.Order>`. Mago's `Types::header` reads it from the codex's direct header type arguments.
  - **The bounds** are the bounds of its type parameters, `App.DatabaseEntity` or `Any?`, an interface's as a class's.
- **The compiler stores both on the class,** as `zend_class_entry`'s `info.user.sharp_bounds` and
  `info.user.sharp_header`, each `NULL` when the class-like has none. A generic class, never an interface, also
  declares the slot from the member.
- **A class is PHP#'s when its file name ends in `.sharp`,** as `sharp_is_sharp_file` and `zend_is_sharp_type_class`
  already tell PHP# code apart. `sharp_class_is_sharp` reads `info.user.filename`, so a PHP# class without type
  parameters takes none, and a class plain PHP declares takes any (below).
- **The metadata lives on the class.** Opcache persists both strings as it persists the class's file name. Each
  metadata text interns once in the intern table of part A, since the source bounds it.
- **Plain PHP classes, and internal ones, take type arguments at any arity.** Plain PHP writes a class's type parameters
  in docblocks only, which the engine never reads. So `unserialize` accepts `PlainBag`, `PlainBag<int>` and
  `PlainBag<int, string>` alike. A built-in type takes its fixed number, and a PHP# class exactly as many as its bounds.
- **`OrderPage` inherits the slot as PHP inherits every property, and never fills it.** It declares no type parameter,
  so `getTypeArguments()` on an `OrderPage` returns `[]`. A class's own type arguments are those of the slot it
  declares: the engine reads the slot only when the slot's property info belongs to the object's own class.
- **The header metadata maps a class's type arguments up to any class it extends or implements.**
  `sharp_type_node_ancestor` follows the header entry that leads to that class and replaces each `$i`, and a class the
  header gives no type arguments starts from its bounds. Two readers use it in R1:
  - an inherited method's open text (part A)
  - a bound with type arguments, so `IntBox : Box<int>` fits the bound `Box<int>` and `StringBox` does not
- **The slot holds null, an `IS_PTR`, or UNDEF in a lazy object.** None is refcounted, so `clone`, the garbage
  collector and object destruction need no change.
- **A class that declares the slot carries `ZEND_ACC_SHARP_GENERIC` in its `ce_flags`.** Every reader asks
  `sharp_type_arguments(object)`, which tests that flag first, so an object of a plain class costs one flag test in
  `==`, `serialize` and Reflection.
- **`new` with type arguments throws an `Error` when the class takes another number of them.** PHP# checks a `new`
  against the class it saw, but at run time an alias or an autoloader can bind that name to a plain class, or to a
  class of another arity:
  - `Class PlainPair declares no type parameters, so new cannot give it <int, string>`
  - `Class Aliased\Twin declares 2 type parameters, so new cannot give it <int>`
  - `ZEND_NEW` asks `sharp_class_takes()` before it creates the object. It counts the members of the class's bounds
    text, the commas outside `<>` and `()`, so a `new` takes no lock and hashes nothing.
- **Lazy objects:**
  - A lazy proxy has the type arguments of its real instance. Reading them initializes the proxy first, as `==` and
    `serialize` already do, and an initializer that throws hands its exception on.
  - A real instance of a parent class has as many type arguments as the parent has type parameters, so the proxy then
    reads its own slot, which holds its class's bounds.
  - A lazy object whose class has no lazy property is initialized at once, as PHP does, and the slot is never lazy. So a
    generic class without a declared property never stays a proxy.
  - A ghost's slot is never lazy. Reading its type arguments leaves it uninitialized, and an UNDEF slot reads as the
    bounds.
  - Resetting an object as lazy keeps its type arguments.

### C. A generic method's own type arguments ride on the call (R2)

- **`ZEND_SHARP_TYPE_ARGS` resolves them into a TMP right before the `DO_*CALL`, after the SENDs.** The `DO_FCALL`,
  `DO_UCALL` and `DO_FCALL_BY_NAME` op1 carries that TMP.
- **The callee's `ZEND_SHARP_RECV_TYPE_ARGS` sits at `opcodes[num_args]`,** right after the RECVs and never first.
  `i_init_func_execute_data` skips the first `num_args` opcodes of a function without type hints. In that position,
  `zend_try_inline_call` never inlines a generic method.
- **It reads `EX(prev_execute_data)->opline->op1` into a hidden local only when all three hold:**
  - its frame is not `ZEND_CALL_TOP`
  - the previous frame is user code
  - the previous opline is one of the three `DO_*CALL`s with a TMP op1
- **Otherwise the call has no type arguments.** Plain PHP called it: the arguments are the bounds, and generic
  parameters are checked on entry (ruling G.2).
- **The descriptor is `IS_PTR` and is never freed.**
- **The JIT gets searched for every `op1_type == IS_UNUSED` assumption on the `DO_*CALL`s,** and the operand is
  registered in the mark register (`Zend/zend_compile.h`). Plain PHP pays nothing.

#### R1 decisions

- **R1 builds `ZEND_SHARP_TYPE_ARGS` and leaves the `DO_*CALL`s alone.** Its TMP feeds `ZEND_NEW` (part F), so R2
  adds only the call side.
- **A `new` whose type arguments name a type parameter of the method itself lowers without them in R1.**
  `Repository.list<TItem>` writes `new PaginatedList<TItem>(…)`: R1 has no call type arguments to substitute, so the
  object holds its class's bounds, as ruling G.2 gives a call plain PHP makes. R2 substitutes a method's type
  parameters.
- **A `new` whose type arguments name a type parameter of the class takes it from `this`.** `new
  PaginatedList<TItem>([])` in a method of `PaginatedList<App.Order>` makes a `PaginatedList<App.Order>`, through the
  open text of part A. In a method a subclass inherits, the header metadata of part B gives the method's class
  its type arguments.
- **A `new Self` or an open text in a lambda that runs without `this` throws the `Error` `$this` throws there.** A lambda
  around such a lambda never uses `this`, as in PHP, so `Closure::bind($outer, null, …)` unbinds it, and the inner
  lambda runs without `this`. `ZEND_SHARP_TYPE_ARGS` dispatches to `zend_this_not_in_object_context_helper`.

#### R2 decisions

- **A method's own type parameter i is spelled `#i` in open texts. A class's stays `$i`.** The spelling never leaves
  the compiled unit. `#i` stands for the method's type argument at index i, `$i` for this's.
- **The bridge writes `SHARP_TYPE_ARGS(null, text)` as the last child of a PHP# generic call's `ARG_LIST`,** the type
  arguments the checker found, written or inferred.
- **The bridge writes `SHARP_TYPE_ARGS(bounds, parameters)` as the last child of a PHP# method's `PARAM_LIST`:**
  - `bounds` is the bounds of the method's own type parameters, or `NULL` when it declares none.
  - `parameters` is one expected type per parameter, `Any?` for one that is not checked, or `NULL` when none is.
  - A method declared in plain PHP gets nothing.
- **The caller emits `ZEND_SHARP_TYPE_ARGS` right before the `DO_*CALL`,** after the SENDs, `CHECK_UNDEF_ARGS` and
  `EXT_FCALL_BEGIN`. The `DO_FCALL`, `DO_UCALL` or `DO_FCALL_BY_NAME` takes that TMP as its op1.
  `zend_compile_call_common` strips the trailing node from the argument list before it compiles the arguments.
- **The callee's `ZEND_SHARP_RECV_TYPE_ARGS` sits right after the RECVs,** at `opcodes[num_args]`, or one later after a
  `RECV_VARIADIC`. It comes before `GENERATOR_CREATE`, never first. `zend_compile_func_decl_ex` strips the trailing node
  from the parameter list before `zend_compile_params`, which emits the opcode after the RECVs.
  - op1 is the CONST bounds text, or UNUSED. op2 is the CONST parameters text, or UNUSED.
  - The result is the method's hidden local, a CV, when op1 is CONST.
  - extended_value holds four cache slots per CONST text.
- **It copies the caller's descriptor into the hidden local only when all three hold:**
  - its own frame is not `ZEND_CALL_TOP`
  - the previous frame is user code
  - the previous opline is a `DO_*CALL` with a TMP op1
- **Otherwise the call has no type arguments, and the bounds stand in (ruling G.1).** A NULL descriptor in the TMP,
  which an open text spells when this has no type argument at an index, also gives the bounds.
- **The descriptor is an interned `IS_PTR` and is never freed.** Neither the TMP nor the hidden local is refcounted, so
  no live range, `FREE` or destructor needs it.
- **Entry from PHP# holds when all three of these do:**
  - the frame is not `ZEND_CALL_TOP`
  - the previous frame is user code compiled from a `.sharp` file, which `sharp_is_sharp_file` tells
  - the previous opline is a `DO_*CALL`
- **Every other entry is from plain PHP:** plain PHP callers, `call_user_func`, internal callbacks such as `array_map`,
  error handlers and property hooks. On that entry each parameter whose expected type is not `Any?` is checked by one
  engine function, `sharp_type_accepts(descriptor, value)`, which R3's `is` reuses.
  - An `Any?` type argument accepts anything.
  - Every other type argument must match exactly. Variance waits for R4.
  - A class with type arguments matches an object of a subclass through the header metadata of part B, as
    `sharp_type_node_ancestor` maps it.
- **Values of `List`, `Map`, `Iterable`, `Class` and `Function` get only PHP's own type check at this boundary.**
  `sharp_type_accepts` accepts every value for them. Their element checks belong to the element-check slice that typed
  compilation (decision 29) plans, not to R2.
- **A failed check throws PHP's `TypeError` wording:** `m(): Argument #1 ($page) must be of type
  App.PaginatedList<App.Order>, App.PaginatedList<App.Customer> given, called in %s on line %d`. The expected type is
  its type text. A given object of a generic class is its dotted class name and type arguments, and any other value is
  what PHP's `zend_zval_value_name` names.
- **The hidden local is a CV named `"\0<sharp>\0types"`,** the string `sharp_type_arguments_key` already holds. Its name
  starts with NUL, so no PHP variable names it.
  - `zend_rebuild_symbol_table`, `zend_attach_symbol_table` and `zend_detach_symbol_table` skip it, so
    `get_defined_vars` never hands an `IS_PTR` to user code.
  - A closure's debug info, `ReflectionFunction::getStaticVariables()`, `getClosureUsedVariables()` and the bound
    variables of `ReflectionFunction::__toString()` skip it too.
- **A `ZEND_SHARP_TYPE_ARGS` whose text names `#i` takes the hidden local as its CV op1.** A text that names only `$i`
  keeps op1 UNUSED with op1.num `ZEND_SHARP_TYPE_ARGS_OPEN`. Every open text, either kind, has five cache slots: this's
  class, this's own type arguments, the method's type arguments, the list they spelled, and the class the text is
  written in. A text that names only `#i` needs no `this`, so a static generic method spells it.
- **A lambda captures its method's hidden local by value,** through the existing capture path. When a lambda's body,
  or a lambda inside it, holds a text that names `#i`, `zend_compile_func_decl_ex` adds the hidden local to the lambda's
  binds: `compile_implicit_lexical_binds` emits its `ZEND_BIND_LEXICAL`, and `zend_compile_implicit_closure_uses` its
  `ZEND_BIND_STATIC`. The scan runs only for a file whose name ends in `.sharp`, so plain PHP pays nothing.
- **The mark register in `Zend/zend_compile.h` lists op1 of the three `DO_*CALL`s,** upstream UNUSED. Its static
  assert pins `IS_UNUSED == 0`, the value `init_op` leaves in an opline, which is why no upstream `DO_*CALL` carries a
  TMP op1. `sharp/bin/census` checks every write of it.
- **The JIT:**
  - `zend_jit_do_fcall` and its second spread test in `ext/opcache/jit/zend_jit_ir.c`, and the frame setup in
    `zend_jit_trace.c`, step back from the call op over `EXT_FCALL_BEGIN`, `TICKS` and now `ZEND_SHARP_TYPE_ARGS`, to
    find a `SEND_UNPACK`, `SEND_ARRAY` or `CHECK_UNDEF_ARGS`.
  - `zend_jit_trace_execute` records no op1 type for the three `DO_*CALL`s, nor for a `ZEND_SHARP_TYPE_ARGS`, since an
    `IS_PTR` is no PHP type.
  - `zend_jit_escape_if_undef` addrefs the previous opline's TMP op1 only when it is refcounted, so an `IS_PTR` is safe.
- **The optimizer:**
  - `zend_try_inline_call` inlines only a function whose `opcodes[num_args]` is a `RETURN`, so it never inlines a
    method that has `ZEND_SHARP_RECV_TYPE_ARGS`.
  - The `DO_FCALL` to `DO_UCALL` rewrite in `optimize_func_calls.c` changes only the opcode, and keeps op1.
  - DCE and `zend_may_throw_ex` treat `ZEND_SHARP_RECV_TYPE_ARGS` as their default does: it may throw and has side
    effects. An open `ZEND_SHARP_TYPE_ARGS` of either kind keeps its side effects.
  - Inference gives `ZEND_SHARP_RECV_TYPE_ARGS`'s result `MAY_BE_CLASS` with no class entry, as R1 gives
    `ZEND_SHARP_TYPE_ARGS`, for the reasons the inference mask table below lists.

#### R2b decisions

- **One `ZEND_SHARP_RECV_TYPE_ARGS` carries both halves.** op1 is the CONST bounds text or UNUSED, and op2 the CONST
  parameters text or UNUSED. It writes the hidden local only when op1 is CONST. Four cache slots follow each CONST
  text, op1's first. A method gets the opcode when it has either half.
- **`zend_compile_params` emits it, before the promoted property assignments.** A constructor with a promoted checked
  parameter would otherwise assign the wrong argument to its property before the check throws. The opcode still sits
  at `opcodes[num_args]`, or one later after a `RECV_VARIADIC`.
- **Entry from PHP# is tested with `sharp_is_sharp_file` on the caller's file name,** not with an op_array flag. The
  check runs only in a method with a checked parameter, and the suffix test costs one compare of six bytes, so no
  new mark enters the register.
- **`sharp_type_accepts(type, value)` takes one type of the parameters list,** after `sharp_type_list_of_frame`
  spelled the list for the frame: `$i` from this, `#i` from the method's type arguments or bounds.
  - A class type needs an instance of the class. Its type arguments for that class, through the header metadata,
    must equal the type's own, except where the type's argument is `Any?`.
  - A built-in type needs a value of its PHP type: `int`, `string`, `bool`, `null`, `Any` (not null) and `Object`.
    `float` also takes an int, as PHP's own `float` parameter does.
  - A nullable type takes null, a union any member, and an intersection every member.
  - `List`, `Map`, `Set`, `Iterable`, `Class` and `Function` take every value. A variadic parameter is a list, so its
    elements are not checked either.
- **The message names the method as PHP's own TypeError does,** class and method: `Checks\Pages::take(): Argument #1
  ($page) must be of type App.PaginatedList<App.Order>, App.PaginatedList<Checks.Customer> given, called in %s on line
  %d`. A call from an internal function, such as `array_map`, has no "called in", as in PHP.
- **A bound writes a type parameter of its own class as `Any?`.** `Sorted<TItem : Comparable<TItem>>` has the bounds
  `Demo.Comparable<Any?>`, the type arguments of an object plain PHP creates, and serialize keeps them. Every type text
  `Types::bounds` writes spells a type parameter that way, so `Parameter::Any` replaced the name spelling.
- **A generic method called on a `List` carries no type arguments in R2.** Every `List` method is an internal method of
  `Sharp\Collection`, which never runs `ZEND_SHARP_RECV_TYPE_ARGS`, so nothing could read them. Extension methods are
  PHP# methods, and the existing generic-call path carries their type arguments with no List-specific code.
- **Operators on a bounded type parameter move to R2c.** The generics branches hold no PHP# operators yet. R2c's
  merge of master brings them, and then `get_instance_class` and `receiver_classes` resolve a `TAtomic::GenericParameter`
  through its bound.

#### R2c decisions

- **A value of a type parameter runs the operators of its bound.** The analyzer's `get_instance_class` takes a type
  parameter's one class from its bound, as `comparands` already does, so `a + b` on two `T : Money` runs
  `Money::op_Addition`, and `-a`, `a < b` and `a == b` run `Money`'s other operators. The bridge's `receiver_classes`
  gives a type parameter its bound's classes, so the same lowering writes the static call.
  - `receiver_classes` serves four more callers: method values, property calls, inlining and a generic call's type
    arguments. Each now sees the bound's classes on a `T` receiver, so a generic method called on a `T` carries its type
    arguments.
  - PHP files never run PHP# operators, so a templated PHP object keeps PHP's `+` and its `TypeError`.
- **A generic call stays pending across a Fiber suspension, and is tested there.** PHP# has no `yield` yet: spec
  section 12 specifies it, and Mago's checker refuses it as an expression it does not support. So `f<Order>(yield)`
  waits for `yield` to land in Mago. A Fiber suspension in a later argument, `Repository.pick<Order>(order,
  Inspector.pause(1))`, is the suspension a PHP# call can wait across today.
  - `ZEND_SHARP_TYPE_ARGS` runs after the SENDs, right before the `DO_*CALL`, so no type argument waits in the
    suspended frame. A `yield` in an argument suspends before it runs too.
  - The test runs without the JIT and under both JITs.
- **A lowercase type parameter needs no second check.** `check_type_parameters` takes only `T`, or `T` and a capital
  letter, so it already refuses `Box<t>` once, in a class, a method, an interface, an interface method and an enum
  method. `check_capitalized` would add a second error on the same name.
- **`Set` in type arguments moves to the final master merge.** Mago's `Set` (#101) is not in the generics branch. That
  merge adds `Set` to `built_in_generic_arity`, a `TArray::Set` arm to `atomic_text`, and `Set` with one type argument to
  the engine's type-text parser.

### D. Readers

- **`is`, `as` and `match` compare descriptors, with a cached check per site.**
- **All catches of one base class lower to one PHP `catch` with an is-chain,** which rethrows only when no arm matches.
- **Ruling G.3:** `typeof(TItem)` works for any type argument, including `int`. It is a `Class<TItem>` that can create
  objects only when the bound is a class.
- **Ruling G.5:** plain PHP reads type arguments with `ReflectionObject::getTypeArguments()`, and through
  `ReflectionClass` for class values. There is no `Sharp\Type` class.
- **Ruling G.6:** each type argument gets its own static members, as in C#. This lands in R4, and G1's static
  refusals go then.

#### R1 decisions

- **`ReflectionObject::getTypeArguments(): array` returns a list of type texts,** one per type parameter of the
  object's own class, such as `['App.Order']`. An object plain PHP created returns its bounds. An object whose class
  declares no type parameter returns `[]`.
- **Every view skips the slot,** because the object's property table never holds it:
  - `rebuild_object_properties` and `zend_std_build_object_properties_array` skip a `ZEND_ACC_SHARP_HIDDEN` property,
    so `var_dump`, `print_r`, `var_export`, `debug_zval_refcount`, `get_object_vars`, `(array)`, `foreach` and
    `json_encode` never see it.
  - Reflection's property listings, `get_class_vars()` and `property_exists()` skip the flag too.
- **`==` and `<=>`:** `zend_std_compare_objects` compares the two objects' argument lists with `sharp_type_equals()`
  after it checks their classes (part A). Different lists are uncomparable, so `==` is false and `<=>` returns 1, as PHP does for objects of
  different classes. Nothing orders by address. Equal lists go on to compare properties, with the slot skipped.
- **`serialize`** writes the list's type text under `"\0<sharp>\0types"` after the properties, in the default form, the
  `__serialize` form and the `__sleep` form, so a class's own choice of form never drops its type arguments:

  ```text
  O:17:"App\PaginatedList":2:{s:5:"items";a:0:{}s:14:"\0<sharp>\0types";s:9:"App.Order";}
  ```

- **`unserialize`** takes the key out before it assigns any property, reads the text, and fills the slot. For a class
  with `__unserialize`, the key is removed from the array and the slot filled before the method runs. `unserialize` is
  an entry point, so the text is untrusted:
  - Each class the text names resolves as `unserialize` resolves an object's class. It must pass `allowed_classes`,
    and it loads through `zend_lookup_class_ex` under `BG(serialize_lock)`, so an autoloader that unserializes leaves
    the outer read intact.
  - The text is respelled with each class's declared name, its unions and intersections sorted again, and a duplicate
    member refused. So `app.ORDER` reads as `App.Order`, and equals the type code spells.
  - Every level is checked against what it names. A built-in type is given its fixed number of type arguments, and a
    PHP# class-like exactly as many as it declares, each within its bound. A class plain PHP declares, and an internal
    class, take any number (part B). A bound with type arguments holds a class whose header gives it those.
  - A text nested deeper than 64 types fails (part A).
  - A text that fails any check, a key on a class without the slot, or a value that is not a string fails `unserialize`
    with its warning, as malformed data does.
  - The text interns for the request only (part A).
  - An incomplete class keeps the entry apart from its properties, only when its value is a string. `unserialize`
    reads it without a reference number, as it reads any class's entry, so every `r:` and `R:` after it names the value
    it meant. `serialize` writes it after the other entries, where it wrote it for the object the class stands for, so
    the bytes round-trip.
- **`serialize` writes the key once.** The `__serialize` form skips the array's own copy of the key, and writes the
  engine's after the other entries.

### E. Class values (R5)

- **A class value with type arguments is the interned descriptor's own string holding the plain class name.** PHP#
  recovers the descriptor from that string's address. Plain PHP sees only the plain name, and `new $x` makes a raw
  object.
- **`==` between class values compares descriptors.**
- **A class value with type arguments can't be a `Map` key.** The checker refuses it, because both would share the
  plain class name.
- **The descriptor-string identity check is written test-first in R5.**

### F. Costs and plumbing

- **A closure's PHP# signature is its type text,** stored as a literal of its op array and resolved through the intern
  table. It is never stored in `op_array->reserved[]`.
- **Each new opcode gets a result type in inference,** for example `MAY_BE_BOOL` for an `is`.
  - A descriptor producer uses `MAY_BE_CLASS` only after every optimizer and JIT path that reads `MAY_BE_CLASS` is
    listed and none of them treats the value as a `zend_class_entry`.
  - If any path does, add a dedicated bit instead.
  - Test whichever you choose.
- **compact_literals covers each new opcode's literal and cache slot from the first commit,** as it does for
  `INSTANCEOF`.
- **VM handlers run through the JIT's default handler call.**
- **The census and `--passes` gate everything.**

#### R1 decisions: the lowering of `new`

`new` takes its type arguments as an operand of `ZEND_NEW`:

```text
new PaginatedList<Order>(rows)            new Self(rows) in a generic class

V1 = FETCH_CLASS "App\PaginatedList"      T1 = SHARP_TYPE_ARGS            (op2 unused: this's list)
T2 = SHARP_TYPE_ARGS "App.Order"          V2 = NEW (static), T1
V3 = NEW V1, T2                           SEND rows
SEND rows                                 DO_FCALL
DO_FCALL
```

- **The bridge writes `ZEND_AST_SHARP_TYPE_ARGS(new, text)` around the `ZEND_AST_NEW`.** The kind is named for the
  opcode it compiles to. A `NULL` text means the arguments of `this`, which is `new Self`: `Self` is `static`, so the
  new object is of `this`'s class and takes `this`'s list whole, a subclass's included.
- **`ZEND_NEW` gains `TMP` as an op2 type.** With it, the handler stores the list in the new object's slot after
  `object_init_ex` and before it pushes the constructor's frame, so the constructor sees its type arguments. Plain
  PHP's `NEW` keeps op2 `UNUSED` and its own handler, so it pays nothing.
- **A class named by a constant goes through `FETCH_CLASS`,** because `NEW` keeps a constant class's cache slot in
  op2.num. That is upstream's own shape for `new $name`. `zend_optimizer_update_op1_const` refuses to fold it back into a
  constant while op2 is in use.
- **Every engine use of an opcode field is in the mark register:** op2 of `ZEND_NEW`, upstream `UNUSED` with a cache
  slot, is a TMP only with `ZEND_SHARP_TYPE_ARGS` before it.
- **`ZEND_SHARP_TYPE_ARGS`:** op1 unused, op2 `CONST` (the text) or unused (`this`), the descriptor's cache slot in
  extended_value, result a TMP holding `IS_PTR`. compact_literals gives its literal and cache slot their own cases, as
  `INSTANCEOF` has. The tracing JIT records no type for `NEW`'s op2, as it records none for `INSTANCEOF`'s.
- **An open text sets op1.num to `ZEND_SHARP_TYPE_ARGS_OPEN`** (`Zend/zend_compile.h`). Class name literals after the
  text name the class it is written in, because `Closure::call()` and `Closure::bind()` change a lambda's scope and
  never that class. Its four cache slots hold this's class, this's own type arguments, the list they spelled (part
  A), and the class it is written in, which the site looks up once without autoloading. compact_literals counts four
  slots for it and keeps the text and the two names together.
- **Every site that reads `this` sets `ZEND_ACC_USES_THIS` on its function:** `new Self` and an open text. So a closure
  around one keeps its `this`, and PHP refuses to unbind it.
- **Only a closed `CONST` text is free of side effects.** Reading `this`'s type arguments runs a lazy proxy's
  initializer, which can throw, so DCE and `zend_may_throw` keep the `UNUSED` and open forms.

#### R1 decisions: the inference mask

`ZEND_SHARP_TYPE_ARGS`'s result is `MAY_BE_CLASS`, with no class entry, the mask `FETCH_CLASS` gives its result. Every
optimizer and JIT read of `MAY_BE_CLASS`, from `trace grep "MAY_BE_CLASS[^_]" Zend ext main sapi`:

| File | Function | Read | Finding |
|---|---|---|---|
| Zend/Optimizer/zend_inference.c | `_zend_update_type_info` | `t1`, `t2`, `RES_USE_INFO()` and `OP1_DATA_INFO()` against `MAY_BE_ANY\|MAY_BE_UNDEF\|MAY_BE_CLASS` | Only asks whether the operand has any type, to tell reachable code. Never dereferences. |
| Zend/Optimizer/zend_inference.c | `_zend_update_type_info`, `ZEND_DECLARE_ANON_CLASS` and `ZEND_FETCH_CLASS` | `UPDATE_SSA_TYPE(MAY_BE_CLASS, …)` | Writes, not reads. |
| Zend/Optimizer/zend_inference.c | `_zend_update_type_info`, `ZEND_NEW` | `(t1 & MAY_BE_CLASS) && ssa_var_info[op1_use].ce` | Reads op1 only, and only its `ce`, which `ZEND_SHARP_TYPE_ARGS` leaves `NULL`. The descriptor is op2. |
| Zend/Optimizer/zend_dump.c | `zend_dump_type_info` | `info & MAY_BE_CLASS` | Prints `class`, and a class name only when `ce` is set. |
| Zend/zend_verify_type_inference.h | `zend_verify_type_inference` | `type_mask == MAY_BE_CLASS` | Skips the check, because the zval's type byte is not a PHP type. Right for an `IS_PTR` too. |
| ext/opcache/jit/*.c | none | none | The JIT reads only `MAY_BE_CLASS_GUARD`, a different bit (1<<27). |

No path treats the value as a `zend_class_entry`, so no dedicated bit is needed. `opcodes.phpt` and a JIT test pin
the choice in every mode.

## Options

### Chosen: type arguments ride on the object and on the call

```csharp
const page = new PaginatedList<Order>(rows);      // runs: the object's hidden slot holds the interned App.Order
const first = repository.first<Order>(rows);      // runs: the call's op1 hands App.Order to the method's hidden local
```

```php
(new ReflectionObject($page))->getTypeArguments();   // ['App.Order']
```

Each runtime type is one interned descriptor, so equal types are one pointer, a site caches its descriptor, and plain
PHP pays one flag test on a plain object and nothing on a plain call.

### Rejected: class metadata in a per-process table keyed by the class

Opcache serves a class from shared memory or the file cache to processes that never compiled it, so a table filled at
compile time would be empty there. The metadata lives on the class instead, and the per-process table is the intern
table, keyed by text.

### Rejected: an opline between `NEW` and the constructor call

It can reach the new object only through `EX(call)->This`, which a class without a constructor leaves empty, and its
operand would be a second use of `NEW`'s VAR, which the live-range calculation in `zend_opcode.c` does not allow.

## Precedent

- **Chosen, the object:** C#, whose runtime builds one type per set of type arguments and keeps it on every object.
- **Chosen, the call:** C#'s shared generic code and Swift's generic functions, which both take their type arguments
  as a hidden argument of the call.
- **Earlier decisions:** decision 7 keeps type arguments at runtime, decision 29 carries inferred ones too, and
  decision 67 gives an object plain PHP creates its bounds.

## Spec

- [Section 11, Generics](../spec.md#11-generics)
