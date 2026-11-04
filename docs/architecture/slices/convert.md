# Slice: `mago convert`, plain PHP into PHP#

A design for a command that raises a project's plain PHP class files into PHP#, file by file, and proves each one before it writes it. This note is the design only. Cass approves it before any slice starts. The spike behind it is `spike/converter` at `ed784cfd2c801c1e70bba42c82f213b9c2428a98`. Line numbers refer to `docs/spec.md`.

## The answer first

`mago convert <paths>` converts a file whole or leaves it plain PHP, and it never writes a type the analysis inferred. The spike does this end to end on a Composer fixture project with an extension host and a plain PHP library: seven of its ten class files convert, three stay plain PHP with their reason, `mago compile` accepts every file, a plain PHP subclass of a converted class included, and `run.php` prints the same fourteen lines on the engine before and after.

Three findings shape the work more than the code does:

- **Bare PHP function calls still block most files that make one.** Of 1,058 class files of a production application that the converter takes by shape, 523 call a bare function, and 128 of those call only functions with a form. A library's root functions all have one, through the namespace of the classes beside them, and 350 files call one. PHP's built-ins are the blocker: 198 called in 389 files have no standard library member that runs them with PHP's meaning, as `array_map` (125 files), `in_array` (119), `array_values` (107), `array_filter` (106) and `trim` (94). The converter refuses those calls by rule and never adds a form of its own (decision 4).
- **Git's rename detection loses almost every converted file unless the rename is its own commit.** The conversion rewrites most lines, so a staged deletion and addition showed one rename of five, and none for `Order` and `Shelf` even at `-M20%`. With `git mv` first, the index holds `R100` renames and `git log --follow` follows each file (decision 8).
- **Analysis can't prove a conversion keeps behaviour.** A PHPDoc type becomes a check and a conversion where a value enters PHP#: a `TypeError` at entry (line 1076), an `int`-keyed array renumbered as a `List` (line 1514), and an `int` that becomes a `float` in a `List<float>` (decision 074). So the project's own tests, compared test by test, are a gate the user runs after converting, and the command's summary says so (decision 9).

Every difference between plain PHP and PHP#, by spec section, is in [every construct](#every-construct-by-spec-section). Every construct the language refuses names its spec line in [refused by the language](#refused-by-the-language).

## How a file converts

### Which files

The command takes a file when it is under a PSR-4 folder of `composer.json`'s `autoload`, and declares exactly one class-like, named like the file, as section 27 requires of a `.sharp` file. Every other file stays plain PHP with its reason:

- a file under no `autoload` folder is a script or a test
- a file that declares no class-like is a script
- a file that declares two types, or one named unlike the file, breaks section 27

`autoload-dev` folders are refused, as line 5380 keeps a test runner's files apart (decision 10). On the application, 1,058 of 1,144 files under `autoload` pass the shape check: 24 declare no class-like, and 62 declare one named unlike the file.

### Where the types come from

The command runs the project's analysis as `mago analyze` runs it, with the project's `mago.toml` and its extension hosts, and reads each declaration's type from the codebase after `analyze()`. Each `TypeMetadata` carries one of five origins, and the converter writes four:

- a native declaration: `from_docblock: false, inferred: false`
- a PHPDoc tag, as `@var list<T>`: `from_docblock: true`
- an extension host's refinement, which `apply_refinements` stores as `from_docblock: true`
- a type inherited from the parent, which the populator stores as `inferred: true`, for a parameter, a return and a property alike. It is declared, in the parent, and PHP#'s override rules need it
- a return the analysis takes from the method's body, which `resolve_body_returns` stores as `from_docblock: true`

The last one is inferred, and it looks like the second and third. `FunctionLikeMetadata::return_from_body` tells it apart: it holds the issue that explains the body-taken return, and is `None` for every other origin. A method with `return_from_body.is_some()` refuses its file:

```text
src/Factory.php:15:21: `make`'s return is taken from its body, which the analysis infers, and the converter writes no inferred type
```

A declaration with no type anywhere is `mixed`, which PHP# writes `Any?`. A type `get_sharp_type` returns `None` for refuses the file with the type in its reason, as `array-key`, `scalar`, `array{…}`, a bare `array`, which is `array<array-key, mixed>`, and a literal type such as the `false` of `int|false`.

### How a `new` gets its type arguments

Generics are reified (line 1073), so a type argument the analysis inferred becomes a runtime `TypeError` when it is wrong. `new Box($x)` of a generic class takes the type arguments of the declared type its value goes into: a property's, a method's return, or a parameter's. Without one, it takes the class's bounds, which plain PHP's `new Box($x)` already has (line 1127). The spike's `Crate` shows both:

```diff
-        $this->box = new Box($spec);
+        this.box = new Box<Spec>(spec);
⋯
-    /** @return Box<Special> */
-    public function special(): Box
+    public Box<Special> special()
     {
-        return new Box(new Special('crated'));
+        return new Box<Special>(new Special('crated'));
```

### How the text changes

The output is edits on the file's own text, through `mago-text-edit`, so every comment and blank line stays. A rewrite edits only the tokens its node owns, never its children's, so nested rewrites never overlap: `$this->spec->label()` is three edits, two `->` to `.` and one `$this` to `this`.

A rewrite that moves its children renders each child first, with the edits inside it, and replaces its whole span. `count($this->items)` becomes `this.items.count()`, and `'x' . $this->quantity` becomes `` `x${this.quantity}` ``. `Edits::render(source, start, end)` returns the child's text and takes its edits out of the list, so no edit is applied twice. Two edits that still overlap are a defect, and refuse the file.

PHPDoc tags whose type the declaration now writes leave the docblock: `@var`, `@param`, `@return` and the `@template` tags. Prose and every other tag stay, and a docblock left empty goes. A deleted statement, as the `parent::__construct(…)` that becomes `: super(…)`, takes its whole lines with it.

### How a name prints

`types::type_text` prints an analysis type in PHP#'s spelling, as the bridge's `type_text` prints one in PHP's. A class prints by the name the file reaches it by:

- its bare name, when it is a root class of `Builtins.sharp`, as `Throwable`, the standard exceptions, `Attribute`, `Countable` and `JsonSerializable`, or a class declared directly in `Sharp` (line 4409). A `use` line of a root class goes
- its short name, when it is in the file's own namespace
- its alias, when a `use` line names it
- a new `import` line otherwise, under its short name, or `import … as …` with the namespace's last part before it when another import takes the short name (line 4452)

A PHP built-in class outside `Builtins.sharp` refuses the file, since section 23 names it only in the standard library.

### How a bare function call converts

A call's function is the one its resolved name names, `names.get(identifier)`, or PHP's own of the written name when that is unqualified, as PHP falls back to the root namespace. Then one of three forms applies:

- **A library's function** is called through its namespace (line 4253): `Vendor\Pdf\render($html)` becomes `Pdf.render(html)` after `import Vendor.Pdf;`, and a `use function` line goes, since each call imports the namespace. A function a library declares in PHP's root namespace belongs to the namespace of the library's classes in its folder (line 4286, decision 083): the converter takes `codebase.folder_namespaces(function.span).first()`, so the fixture's `shout($this->reference)` becomes `Format.shout(this.reference)` after `import Acme.Format;`. An empty list means no form.
- **A built-in type test** of the prelude, `@return ($value is T ? true : false)`, gives `value is T`, or `value == null` for `null`, and `!is_string($x)` gives `x is not string` (line 3652). A `T` PHP# can't write refuses, as `is_array` and `is_numeric` do.
- **Any other built-in** maps only to a standard library member that runs exactly that function with PHP's meaning. The form comes from the bridge's `inline_forms`, so the library's forms have one owner: `InlineForm::php_call()` gives the PHP function a form's body calls and what it passes it, each argument a slot, a literal or a constant, and `InlineForm::in_order()` how many slots it reads in order. A member fits a call when:
  - every literal and constant it passes is the call's own, so `in_array` without `true` never becomes a strict `contains`
  - an extension member's receiver takes the call's argument
  - its return holds the prelude's return of the function, a conditional return counted by both branches, so a `false` failure value is no form
  - it feeds no by-reference parameter of the function, but a `ref` receiver (line 5289)
  - when its slots reorder the arguments, each argument is a variable, a literal, a constant or a property read, as the bridge's `in_order` rule requires

  Any other built-in refuses the call, naming the function and the difference, as `` `trim` has no PHP# form: no standard library member runs `trim` with PHP's meaning (decision 083) ``. No forwarder with PHP's meaning is added beside the spec's members, as line 2981 and decision 083 rule out.

### How a file is proven before it is written

The command analyzes the plain project once, with fresh extension hosts. It then puts every converted text in a fresh database in place of its plain file, under the `.sharp` name, marked by `mark_sharp_file` as the loader marks a `.sharp` file it reads, and analyzes that project with fresh hosts. It compares every issue with the plain project's, warnings and errors alike, keyed by file, level, code, message and line, with duplicates counted. It then lowers every file through `Server::compile`, in memory. A file stays plain PHP when:

- the converted file has any error or warning, since nothing hides a language error in a `.sharp` file (lines 5360 and 5384)
- any other file gains an error, or a warning decision 9 doesn't expect
- the compile refuses a file it accepted before, a plain PHP subclass included

A file with issues of its own stays plain PHP, and the rest are proven again. An issue in another file names no converted file, so the files are proven in halves, each on top of the ones already proven, until one file is left to keep plain PHP. A folder whose files all convert costs two analyses. The project's `ignore` entries and baseline apply to the codes of an extension host's rules only, never to a language error, as line 5360 says.

Every proof is a fresh analysis because a per-file proof cost too much and was wrong. With extension hosts, an incremental analysis of one changed file costs a whole analysis, 20 s a file on a 1,200-file application. It also reports issues a fresh analysis doesn't: a host rule's state left from the first pass, and `@mago-expect` pragmas it didn't revisit.

An extension host loads the project's own classes (line 5385), and during the proof the hosts load the plain ones. So after a run writes its files, the command compiles the project for the hosts again. When that changes what they load, fresh hosts that load the converted classes prove the project once more, and each issue they add is an error, with the plain PHP in git.

`--dry-run` prints each diff and changes nothing.

### What git sees

A tracked file moves with `git mv`, then the converted text is written over it. The index holds the rename alone, `R100`, and the conversion is an unstaged change on top:

```text
$ git status --short
RM src/Shop/Shelf.php -> src/Shop/Shelf.sharp
```

The next `mago compile` writes `src/Shop/Shelf.php` again, as decision 081's discovery file, and its `info/exclude` block hides it, so `git status --ignored` shows it as `!!`, never as the tracked file come back. An untracked file is renamed on disk only.

### What a converted file loses

`mago lint`, `mago guard` and `mago fmt` skip `.sharp` files through `skip_sharp_files`. A converted file leaves each project lint rule and each guard rule, as a layer boundary, until those tools read PHP#. The command's summary names this, and the proof lists the lint and guard issues the converted files had before.

## Every construct, by spec section

Each line gives the plain PHP form, the PHP# form, what finds it, and the slice that converts it, or "refuses". "Tree" is the syntax tree, "type" is the analyzer's type of an expression or declaration, and "both" needs both. Every refusal's message names its spec line.

### Section 1. Attributes

- `#[Name(args)]` → `[Name(args)]`, each kept with its arguments. Tree. Slice 1.

### Section 2. Variables

- `$name` → `name`, and `$let` → `@let` for a PHP# keyword. Tree. Slice 1.
- A local's first assignment → `const name = …`, `let name = …`, or `T name = …` when the value's type is not the one PHP# infers, as an empty literal. Both. Slice 1.
- `$$name`, `global`, `compact()` and `extract()` → removed (line 199). Tree. Refuses. 10 files hold `global`, 3 `compact` or `extract`.
- Superglobals → removed (section 29). Tree. Refuses. 18 files.
- A `static` local → refuses, as the converter's own refusal: its PHP# form is a private static field with an initial value (lines 441 to 443), which the converter doesn't write yet. Tree. 2 files.

### Section 3. Scope

- A local first assigned in a block and read after it → declared before the block, as `T? name = null;`. Both. Slice 2. Slice 1 refuses it.

### Section 4. Member access

- `->` and `::` → `.`, and `?->` → `?.`. Tree. Slice 1.
- A static and an instance member of one name → refuses (line 323).

### Section 5. Access modifiers

- A class → `public class`, since PHP reaches every class. Tree. Slice 1.
- A member with no visibility, or `var` → `public`. Tree. Slice 1.

### Section 6. Fields and properties

- A `private` or `protected` property → a field, `private int count = 0;`. Both. Slice 1.
- A `public` property → `public T name { get; set; }`. Both. Slice 1.
- An untyped property, which PHP fills with `null` → `Any? name = null`. Both. Slice 1.
- Two names in one declaration → one declaration per name, each on its own line (line 383). Tree. Slice 1.
- Property hooks → accessors, where `$this->name` is `field`, `$value` is `value` and a short `set => expr` is `set => field = expr`. A property with storage gains the accessor it declares no hook for, and its initial value follows the accessors. A `set` hook whose parameter is other than `$value` of the property's type, a `set` hook with no `get` on a property with no storage, and `&get` refuse. Tree. Slice 4.

### Section 7. Methods

- `public function name(int $a): string` → `public virtual string name(int a)`. Both. Slice 1.
- A static method a subclass may replace → `static virtual` (lines 1390 and 1396). Tree. Slice 4.
- Named arguments `f(name: $v)` → `f(name: v)`. Tree. Slice 1.
- Argument spread `f(...$items)` → `f(...items)`. Both. Slice 5.

### Section 8. Functions

- A file-level function or statement → refuses, since a `.sharp` file declares one type (line 5306). Tree.
- `echo`, `print`, `exit` and `die` → `Console` and `Process`, which a class receives through its constructor (line 830). Tree. Refuses until a slice injects them. 29 files.
- A bare function call → [how a bare function call converts](#how-a-bare-function-call-converts). Both. Slice 1 for type tests and library functions, slice 3 for built-in members.
- `include`, `require` and `eval` → refuses (lines 827 and 5306). 7 files.

### Section 9. Constructors

- `__construct` → the class's name. Tree. Slice 1.
- A promoted parameter → `private Spec spec` for a field, `public string reference { get; set; }` for a property. Both. Slice 1.
- A subclass constructor → `: super(…)` on every one (line 926): the arguments of the `parent::__construct(…)` its body starts with, which leaves the body, or `: super()` when no ancestor declares a constructor. Tree. Slice 4. 162 files have a parent with none.
- A parent constructor call after other statements, or none where an ancestor declares one → refuses, since `: super(…)` runs it first. Tree. 6 files.

### Section 10. Structs and `with`

- `clone $x` with no property set → `x with { }` (line 1020). Tree. Slice 4. 13 files.

### Section 11. Generics

- `@template T of Spec` → `class Shelf<T : Spec>`, and `@template-covariant` → `out T`. Type. Slice 1.
- `new Box($x)` of a generic class → `new Box<Spec>(x)`, with the declared type's arguments or the class's bounds, [never the analysis's](#how-a-new-gets-its-type-arguments). Type. Slice 1.
- `@extends Base<T>` → `: Base<T>`, and the same for `@implements` and a trait's `@use`. The tags leave the docblock. Type. Slice 4.

### Section 12. Collections

- `list<T>` → `List<T>`, `array<K, V>` → `Map<K, V>`. Type. Slice 1.
- `[]` where a `Map` is declared → `[:]`, and where a `List|Map` union is declared → refuses rather than pick one. Type. Slice 1.
- `$list[] = $value` → `list.add(value)`. Tree. Slice 1.
- `empty($items)` → `items.isEmpty()`, and `unset($map[$key])` → `map.remove(key)`. Both. Slice 2.
- `isset($map[$key])` → `map[key] != null`, since a `Map` read gives null for a missing key (line 1625) and `isset` is false for a null value. Both. Slice 2.
- `['id' => $id] = $row;` of a `Map` → one read per key, `const id = row["id"];`, each handled as a `TValue?` (lines 1625 and 1664). Both. Slice 5. 16 files.
- `array` with no PHPDoc type, and an array shape → refuses with the type. Type.

### Section 13. `readonly`

- A `readonly` property or promoted parameter → `{ get; }` (line 2095). Tree. Slice 1.
- A `readonly` class → each property `{ get; }`. Tree. Slice 4.

### Section 14. Functions as values

- `fn($x) => …` and `function ($x) use ($y) { … }` → `(T x) => …`. Both. Slice 5.
- A by-reference capture `use (&$total)` → a plain capture, since a lambda captures the variable itself (line 2200). Tree. Slice 5. 26 files.
- A by-value capture `use ($total)` → the same plain capture, only when nothing writes `total` after the lambda is made. Both. Slice 5.
- `[$this, 'name']`, `'Class::name'` and `name(...)` → `this.name`. Both. Slice 5.
- A method and a property of one name → refuses (line 2346). 51 files.
- `??` stays. `?:` → refuses (line 3764).

### Section 17. Loops

- `foreach ($items as $item)` → `for (const item of items)`, and `foreach ($map as $key => $value)` → `for (const [key, value] of map)`. Both. Slice 2.
- A body without braces → braces added (line 2903). Tree. Slice 2. Slice 1 refuses it.

### Section 18. Strings

- `.` between two strings → `+`. Type. Slice 1.
- `.` with an `int` or a `float` → a template, `` `x${count}` ``, which prints a number through PHP's own conversion. Type. Slice 1.
- `.` with a `bool`, which PHP prints as `1` or nothing, and a template as `true` or `false` (line 2940) → refuses. Type.
- `"Hi $name"` → `` `Hi ${name}` ``. Tree. Slice 6.
- Heredoc and nowdoc → a template (line 2927). Tree. Slice 6. 7 files.
- `__toString()` → `string toString()` (line 2977). Tree. Slice 4.

### Section 19. Equality, comparison and operators

- `===` and `!==` → `==` and `!=` when both sides have one scalar type, both are enums, or one side is `null`. Type. Slice 1.
- `===` where either side may hold an object → stays `===` (line 3029). Type. Slice 1.
- `===` between an `int` and a `float` → refuses, since `1 === 1.0` is false and PHP# compares them by value (line 1705). Type.
- `===` on a collection → refuses, since PHP compares keys and order and PHP# compares elements (lines 3023 and 3032). Type.
- Loose `==` → `==` when both sides have one scalar type, and refuses otherwise. Type. Slice 2.
- `instanceof` → `is`, and `!$x instanceof A` → `x is not A` (line 3652). Tree. Slice 1.

### Section 20. Enums

- A PHP enum → a PHP# enum. Tree. Slice 4.

### Section 21. Pattern matching

- A condition that is no `bool` → the comparison its type gives: `x != null` for `T?`, `!list.isEmpty()` for a collection, `x != "" && x != "0"` for a string (line 3752). Type. Slice 2. Slice 1 refuses it.
- `match` → `match`, when each arm's comparison passes the `===` rule of section 19, since PHP's `match` compares with `===` (line 3426). Both. Slice 2.
- `switch` → `match` with block arms, when no arm falls through (line 3426). Tree. Slice 6. 6 files.
- `and`, `or` and `xor` → `&&` and `||`, and `xor` refuses. Tree. Slice 2.

### Section 22. Inheritance

- `extends A implements B` → `: A, B`. Tree. Slice 1.
- An open method → `virtual`, one that replaces a parent class's → `override`, and a `final` or `private` one → nothing (line 3782). Both. Slice 1.
- A plain PHP trait a class uses → listed in the header (line 4205). Tree. Slice 4. A `use` block with `insteadof` or `as` refuses.
- A trait declaration → refuses, since PHP# declares no traits. Tree. 16 files.

### Section 23. Namespaces and imports

- `namespace A\B;` → `namespace A.B;`, and `use A\B;` → `import A.B;`. Tree. Slice 1.
- A class in code → its name as [how a name prints](#how-a-name-prints) says. Both. Slice 1.
- `use function` → no line, since each call imports the namespace. `use const Vendor\Pdf\SIZE` → `import Vendor.Pdf;` and `Pdf.SIZE` (line 4345). Tree. Slice 1.
- `new $class(…)` of a class value → `new (type)(…)`, when the class's constructor is `required` (line 4968). Both. Slice 5.

### Section 24. Built-in types and `Any`

- `mixed` → `Any?`, `?T` → `T?`, `object` → `Object`. Type. Slice 1.
- `(int)`, `(float)` and `(string)` stay, and the aliases `(integer)`, `(double)`, `(real)` and `(binary)` become them. Tree. Slice 1.
- `(bool)`, `(array)` and `(object)` → refuses (line 4717). Tree.
- `try`, `catch` and `finally` stay, and `catch (Exception $e)` → `catch (Exception e)` (line 6151). Tree. Slice 1.

### Section 25. Referring to classes

- `X::class` → `typeof(X)`, `static` → `Self`, `self` → the class's name (line 4870). Tree. Slice 1.
- A property or a method named by a variable, `$x->$name` and `$x->$name()` → refuses (line 4973, decision 030). Tree. 23 and 10 files.

### Section 27. PHP# files

- `<?php` and `declare(strict_types=1);` → removed, since PHP# is always strict (line 5295). Tree. Slice 1.
- A file without `strict_types` → the same removal, which makes its calls strict. The proof's analysis and the project's tests catch a call that relied on coercion. Tree. Slice 1.
- Magic constants such as `__DIR__` → `Position` (line 5319). Tree. Slice 6. 66 files.

### Section 29. Effects

- `getenv()`, `echo` and `exit` → `Environment`, `Console` and `Process`, which a class receives through its constructor. Refuses until a slice injects them.

### Section 30. Tuples

- `[$a, $b] = $this->pair()` of a tuple type → `const [a, b] = this.pair();`. Both. Slice 5.

### Refused by the language

Each of these refuses its file, and the message names the line:

- the `@` silence operator, since `@` marks a keyword used as a name (line 154): 5 files
- an anonymous class, since no type nests (line 2514): 2 files
- a by-reference parameter, return, `foreach` or assignment `=&`, since PHP# passes every argument by value (line 2262) and a lambda captures the variable itself (line 2200): 29, 2 and 2 files
- a method named by a variable (line 4973, decision 030): 10 files
- `include`, `require` and `eval` (lines 827 and 5306): 7 files
- a keyed `yield` (decision 042): 2 files
- `goto`: 1 file

`__invoke` (2 files) and `__destruct` (1 file) refuse too. The spec doesn't settle them, so Cass brings them to Jordan batched with the next language questions.

## The modules

```text
sharp/mago/
├── crates/converter/                     new crate mago-converter, beside crates/sharp-bridge, which lowers PHP#
│   │                                     into PHP the other way
│   ├── src/lib.rs                        pub fn convert(input: &Input) -> Result<Vec<u8>, Vec<Refusal>>
│   │                                     pub struct Input { file, program, names, codebase, artifacts, library }
│   │                                     pub struct Refusal { span, reason }
│   ├── src/edits.rs                      pub struct Edits: replace(span, text), replace_range(start, end, text),
│   │                                     insert(offset, text), delete(start, end), render(source, start, end),
│   │                                     finish(source) -> Option<Vec<u8>>
│   ├── src/types.rs                      pub fn type_text(union, codebase, imports) -> Result<String, String>
│   │                                     pub struct Imports: new(namespace), add_use(alias, name),
│   │                                     is_default(class), name_of(class) -> Result<String, String>,
│   │                                     import(name) -> String, added()
│   ├── src/library.rs                    pub struct Library: new(forms, codebase), members(function) -> &[Member]
│   │                                     pub struct Member { class, name, shape, call, in_order }
│   │                                     pub enum Shape { Static, Method, Property }
│   │                                     pub fn type_test(function) -> Option<&TUnion>
│   │                                     pub fn differs(member, function, codebase) -> Option<String>
│   │                                     pub fn short(name) -> &str
│   ├── src/declaration/                  crate-private: mod.rs holds attributes, docblocks and visibility
│   │   ├── class.rs                      the class header
│   │   ├── members.rs                    properties and methods
│   │   └── constructor.rs                parameters, promoted ones included, and what a constructor sets
│   ├── src/body/                         crate-private: mod.rs walks statements and expressions
│   │   ├── locals.rs                     variables, locals and assignment
│   │   ├── conditions.rs                 conditions from PHP's truthiness, `isset` and `empty`
│   │   ├── loops.rs                      loops, `break`, `continue` and `unset`
│   │   ├── matching.rs                   `match`
│   │   ├── access.rs                     member and constant access
│   │   ├── calls.rs                      calls, their arguments, type tests and `new`
│   │   ├── builtins.rs                   a built-in's call as the library member that runs it
│   │   ├── operators.rs                  operators and casts
│   │   ├── strings.rs                    literals and concatenation
│   │   └── collections.rs                array literals
│   └── tests/                            cases/<construct>/{input.php, output.sharp}, and project/, the
│                                         whole-project fixture, with project.sh
├── crates/sharp-bridge/src/lower/inline.rs   InlineForm::php_call() -> Option<&PhpCall>, InlineForm::in_order()
│                                         pub struct PhpCall { function, arguments }
│                                         pub enum PhpCallArgument { Slot(usize), Literal(bytes), Constant(bytes) }
│                                         PhpCallArgument::literal(literal), which both sides compare by
│                                         InlineForms::iter() -> (class, member, form)
├── crates/orchestrator/src/service/incremental_analysis/compile.rs
│                                         IncrementalAnalysisService::standard_library_forms() -> InlineForms,
│                                         and lower_library, which compile now shares
├── crates/server/src/server.rs           pub fn codebase(&self) -> &CodebaseMetadata
│                                         pub fn analyze_file_with_artifacts(&self, file_id)
│                                         pub fn standard_library_forms(&self) -> Result<InlineForms, ServerError>
├── crates/database/src/loader.rs         pub fn mark_sharp_file(file: &mut File, workspace: &Path)
├── src/commands/convert.rs               new: ConvertCommand { paths, dry_run }, execute(configuration, color_choice)
├── src/commands/compile.rs               standard_library(root, database), now pub(crate) for convert
└── src/utils/git.rs                      pub fn move_tracked(workspace, from, to) -> Result<bool, Error>
```

Each module of `declaration/` and `body/` holds one `impl Converter` for one construct family, the state of one file's conversion, so a family grows in its own file. The command alone talks to the server, the file system and git, so the crate reads nothing but its `Input`.

## Slices

Each slice ends in a fixture pair per construct and a run of the whole-project proof. Slice 1 is the smallest set that converts a real class file.

1. **Declarations.** Files, namespaces, imports and names, classes with their header, properties and fields, methods, constructors of classes with no parent class, attributes, `$` and member access, `+` and templates, `===`, `is` and `is not`, `X::class`, `new` with its type arguments, casts, `try`, named arguments, locals, type tests, and library functions. Proves a real class file converts, compiles, links to its plain PHP subclasses, and keeps git's history. The whole-project proof lands here, so every later slice runs it.
2. **Conditions and loops.** Conditions from their type, loose `==`, `match`, `empty()`, `isset()` and `unset()`, `foreach`, bodies without braces, and locals across blocks. Proves PHP's truthiness and loops convert with their meaning kept.
3. **Built-in calls.** Every member `inline_forms` gives with PHP's meaning, and each refusal that names the difference. Proves the forms grow with the standard library and nothing else.
4. **Types and inheritance.** Subclass constructors with `: super(…)`, interfaces, abstract classes, enums, class constants, statics and `static virtual`, `@extends`, header traits, property hooks, `readonly` classes, `clone` as `with { }` and `__toString()`. Proves a whole class hierarchy converts in either order.
5. **Functions as values.** Lambdas, captures, method values, argument spread, `new` of a class value, tuples and keyed destructuring. Proves capture keeps its meaning.
6. **Text and branches.** Interpolation, heredoc, magic constants and `switch`. Proves the last syntax-only constructs.

## Tests

- **A fixture pair per construct:** `crates/converter/tests/cases/<construct>/input.php` and `output.sharp`, with a `refusal.txt` instead for a refusing construct. One test runs each through the converter against a small project of the case's own files, and compares the text exactly. The first pair is `new-generic`, the spike's `Crate`: a `new` whose arguments come from a property's declared type, and one whose come from a method's declared return. A float in a template keeps a pair too.
- **The whole-project proof:** `crates/converter/tests/project/`, a Composer project with the PHP# Composer plugin, one PSR-4 folder, a plain PHP library with a root function, and the neutral refinement worker as its extension host. `crates/converter/tests/project.sh` checks, after `mago convert`: `mago compile` accepts every file, plain PHP subclasses included; `mago analyze` has no new error and only the warnings decision 9 lists; and `run.php` prints the same output on the engine. `sharp/bin/test` runs it after the PHP# suite, so every pull request runs it. The fixture has no test suite: a project's own tests are its user's gate, as decision 9 says.

## Decisions Cass approves

Decisions 1, 3, 5, 8, 10, 11 and 12 are approved as they stand. Decisions 2, 4, 6, 7 and 9 change as Cass's verdict on this PR asks, and 13 is new.

1. **Approved: the crate is `crates/converter`, `mago-converter`, and the command `mago convert <paths> [--dry-run]`.** Paths are required.
2. **The converter writes declared, PHPDoc, refined and inherited types, and never an inferred one.** It refuses a method with `return_from_body.is_some()`. A type the codex marks `inferred: true` on a parameter, a return or a property is inherited, and it writes it. `new` takes its type arguments from the declared type its value goes into, or the class's bounds, never from the analysis.
3. **Approved: visibility keeps PHP's reach.** `public` where PHP leaves it out, every class `public`, an open method `virtual`, and a method that replaces a parent class's `override`. The design recommends no narrowing: `too-wide-member` can't see templates, string callables or routes.
4. **A bare function call's form comes only from declarations,** as [how a bare function call converts](#how-a-bare-function-call-converts) says: a library's function through its namespace or its folder's, a type test as `is`, and a built-in only as a standard library member whose inline form runs exactly that function with PHP's meaning. Any other built-in refuses the call, naming the function and the difference, and nothing is added to the library for the converter.
5. **Approved: nested rewrites are edits on owned tokens, and a reordering rewrite renders its children.**
6. **A name prints by the file's own names,** a root class of `Builtins.sharp` bare, and a missing one gets an `import`, `import … as …` where the short name is taken.
7. **Each file is proven in memory before it is written:** analysis and lowering through `Server::compile`, against the whole project with the earlier conversions in place, with fresh extension hosts once they load a converted file. The project's `ignore` entries and baseline apply to host rules' codes only.
8. **Approved: a tracked file moves with `git mv`, then takes its converted text.**
9. **The proof compares warnings and errors, keyed by file, level, code, message and line, with duplicates counted.** A conversion may add `too-wide-member` on a member it made `public`, and `redundant-docblock-type` in a plain PHP caller whose PHPDoc the converted class now declares. The summary lists both, and any other new issue refuses the file. The summary also says the project's tests, compared test by test, are the gate for what analysis can't see: a `TypeError` at entry (line 1076), a renumbered `int`-keyed array (line 1514), and an `int` that becomes a `float` in a `List<float>` (decision 074).
10. **Approved: `autoload-dev` files stay out** (line 5380).
11. **Approved: the command names what a converted file leaves:** lint, guard and format rules.
12. **Approved: three public functions join existing modules:** `Server::codebase`, `Server::analyze_file_with_artifacts` and `loader::mark_sharp_file`.
13. **New public API for the standard library's forms,** which decision 4 reads from the bridge as Cass asked: `InlineForm::php_call` and `InlineForm::in_order`, the `PhpCall` and `PhpCallArgument` types, `InlineForms::iter`, `IncrementalAnalysisService::standard_library_forms` and `Server::standard_library_forms`. `compile` and `standard_library_forms` share one private `lower_library`, so the library is lowered by one path.

## Found, not fixed

1. **A function the census finds neither among PHP's built-ins nor in Composer's `files` autoload:** 88 functions in 28 files. They are namespaced functions or functions loaded another way, and the slice measures them with the converter itself.

## Evidence

### The census

`census.php`, outside the repository, reads a production application's `composer.json`, every file under its `autoload` PSR-4 folders with PHP's tokenizer, and Composer's `vendor/composer/autoload_files.php`, and changes nothing. A function is a built-in when PHP's reflection says it is internal. It has a form when it is a writable type test or one of the 42 built-ins whose standard library member the converter's `Library` keeps, as `mago convert` listed them on the fixture. A library root function has a folder namespace when a class of a namespace sits in its file's folder. It printed:

```text
files under autoload psr-4: 1144
convertible by shape (one class-like named like the file, in its folder's namespace): 1058
  refused by shape, no class-like: 24
  refused by shape, class-like named unlike the file: 62
bare function calls: 523 convertible files call one, and 128 of them call only functions with a form
  a built-in with a form: 261 files call one, 38 distinct functions
  a built-in without one: 389 files call one, 198 distinct functions
  a library root function with a folder namespace: 350 files call one, 29 distinct functions
  a library root function without one: 0 files call one, 0 distinct functions
  no function this census finds: 28 files call one, 88 distinct functions
the 15 built-ins without a form called in the most convertible files:
    125  array_map
    119  in_array
    107  array_values
    106  array_filter
     94  trim
     93  is_array
     62  array_keys
     50  array_key_exists
     44  json_encode
     42  substr
     41  array_unique
     35  strlen
     34  strtolower
     29  explode
     29  array_merge
```

A built-in "with a form" is an upper bound for a call: its constants and receiver must still fit. The construct counts above are the same census.

### The spike

The spike is `spike/converter` at `ed784cfd2c801c1e70bba42c82f213b9c2428a98`. Its fixture is `sharp/mago/crates/converter/tests/project/`:

- `Spec`, a class with a promoted `readonly` property
- `Special`, a plain PHP subclass of `Spec`, which stays plain PHP
- `Box`, untyped, whose type parameter, property and return the refinement worker gives
- `Shelf`, typed only in PHPDoc, with `@template`, `list<T>`, `array<int, T>` and `count()`
- `Order`, typed natively, with an import, `===` on strings and on objects, and a library's root function
- `Note`, with an untyped parameter and `is_string`
- `Crate`, with two `new Box`, one into a declared property and one into a declared return
- `Tagged`, a subclass whose constructor starts with its parent's
- `Factory`, whose return the analysis takes from its body, which the converter must refuse
- `Counter`, with a by-reference parameter, which it must refuse
- `Pair`, with two classes, which it must refuse
- `packages/format`, a plain PHP library with `shout()` in PHP's root namespace beside `Acme\Format\Formatter`
- `run.php`, plain PHP in another namespace, which calls each class

`sharp/bin/build` built the engine, PHP 8.5.12 NTS DEBUG, and `sharp/bin/build-mago` built mago 0.6.2 in the `test-run` profile. `sharp/mago/crates/converter/tests/spike.sh <empty folder>` copies the fixture, installs it with Composer, commits it to a new git repository, and runs every step below. Its output, in order:

```text
$ php -n run.php
spec alpha
special beta
alpha
special beta
2 2 7,9
spec alpha
special beta
R-1: spec alpha x5 R-1
for R-1 not of beta R-1!
spec gamma special crated
new spec delta new delta
text hi / other
spec made
left+right

$ mago analyze --reporting-format emacs
src/Factory.php:15:21:error - declaration-refinement-proof/unsupported-type: The type this method returns cannot be resolved from its body.
run.php:55:31:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:55:38:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:55:6:error - mixed-argument: The first value for `echo` is too general.

$ mago convert src/Spec.php src/Box.php src/Factory.php src/Shop
 INFO The extension hosts load src/Box.sharp, so the analysis starts again.
 INFO The extension hosts load src/Shop/Crate.sharp, so the analysis starts again.
 INFO The extension hosts load src/Shop/Note.sharp, so the analysis starts again.
 INFO The extension hosts load src/Shop/Order.sharp, so the analysis starts again.
 INFO The extension hosts load src/Shop/Shelf.sharp, so the analysis starts again.
 INFO The extension hosts load src/Shop/Tagged.sharp, so the analysis starts again.
 INFO The extension hosts load src/Spec.sharp, so the analysis starts again.
 WARN src/Factory.php stays plain PHP:
 WARN   src/Factory.php:15:21: `make`'s return is taken from its body, which the analysis infers, and the converter writes no inferred type
 WARN src/Shop/Counter.php stays plain PHP:
 WARN   src/Shop/Counter.php:9:26: a PHP# parameter is never taken by reference, so `$count` can't be: return the new value instead
 WARN src/Shop/Pair.php stays plain PHP:
 WARN   src/Shop/Pair.php: it declares 2 types, and a .sharp file declares one (spec section 27)
 INFO Expected after converting, decision 9: run.php:27: warning redundant-docblock-type: Redundant docblock type for variable `$box`.
 INFO Expected after converting, decision 9: src/Box.sharp:7: warning too-wide-member: `Box.item` is public, and nothing outside `Proof` uses it.
 INFO Converted 7 files to PHP#, and 3 files stay plain PHP.
 INFO PHPDoc types are now checked while the code runs, so run the project's tests on the PHP# engine and compare them test by test.

$ git diff --cached -M --stat
 src/{Box.php => Box.sharp}            | 0
 src/Shop/{Crate.php => Crate.sharp}   | 0
 src/Shop/{Note.php => Note.sharp}     | 0
 src/Shop/{Order.php => Order.sharp}   | 0
 src/Shop/{Shelf.php => Shelf.sharp}   | 0
 src/Shop/{Tagged.php => Tagged.sharp} | 0
 src/{Spec.php => Spec.sharp}          | 0
 7 files changed, 0 insertions(+), 0 deletions(-)

$ mago compile
 INFO Compiled 66 PHP# files into .sharp/.

$ git status --short --ignored src
RM for each of the 7 renames, and !! for each .php file mago compile wrote at an old path

$ git log --follow --name-status --format=%s -- src/Shop/Shelf.sharp
convert to PHP#
M	src/Shop/Shelf.sharp
rename to PHP#
R100	src/Shop/Shelf.php	src/Shop/Shelf.sharp
plain PHP
A	src/Shop/Shelf.php

$ mago analyze --reporting-format emacs
src/Factory.php:15:21:error - declaration-refinement-proof/unsupported-type: The type this method returns cannot be resolved from its body.
src/Box.sharp:7:25:warning - too-wide-member: `Box.item` is public, and nothing outside `Proof` uses it.
run.php:27:10:warning - redundant-docblock-type: Redundant docblock type for variable `$box`.
run.php:55:31:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:55:38:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:55:6:error - mixed-argument: The first value for `echo` is too general.

$ php -n run.php
(the same fourteen lines)

$ diff before after
(no difference, exit 0)
```

The four errors exist before and after: they are `Factory`'s, which stays plain PHP. The two warnings are decision 9's. The 66 compiled files are the seven converted ones and the standard library's.

### Converted files

`src/Shop/Order.php`, from the spike's `--dry-run`, shows `===` both ways and the library's root function:

```diff
-namespace Proof\Shop;
+namespace Proof.Shop;
 
-use Proof\Spec;
+import Proof.Spec;
+import Acme.Format;
⋯
-        return $this->reference === $reference;
+        return this.reference == reference;
⋯
-        return $this->spec === $spec;
+        return this.spec === spec;
⋯
-        return shout($this->reference);
+        return Format.shout(this.reference);
```

`src/Shop/Tagged.php` shows `: super(…)`:

```diff
-class Tagged extends Spec
+public class Tagged : Spec
 {
-    public function __construct(string $name, public string $tag)
+    public Tagged(string name, public string tag { get; set; }) : super(name)
     {
-        parent::__construct($name);
     }
 
-    public function label(): string
+    public override string label()
     {
-        return $this->tag . ' ' . parent::label();
+        return this.tag + ' ' + super.label();
     }
 }
```

`src/Shop/Shelf.php`, typed only in PHPDoc, became:

```csharp
namespace Proof.Shop;

import Proof.Spec;

/**
 * Every type is in PHPDoc.
 */
public class Shelf<T : Spec>
{
    private List<T> items = [];

    private Map<int, T> byNumber = [:];

    public virtual void add(T item, int number)
    {
        this.items.add(item);
        this.byNumber[number] = item;
    }

    public virtual List<T> all()
    {
        return this.items;
    }

    public virtual Map<int, T> numbered()
    {
        return this.byNumber;
    }

    public virtual int size()
    {
        return this.items.count();
    }
}
```

### Rename detection without `git mv`

An earlier spike run staged the deletion and the new file with `git add` instead. `git diff --cached -M --stat` found `src/{Box.php => Box.sharp}` alone, and `-M20%` added only `Note` and `Spec`. `Order` and `Shelf` stayed a deletion and an addition.

### The changed crates' tests

`cargo test -p mago-orchestrator --lib compile`: 41 passed. `cargo test -p mago-sharp-bridge`: 19, 512, 28, 1 and 1 passed, the doctests included.
