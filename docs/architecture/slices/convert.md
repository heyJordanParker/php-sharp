# Slice: `mago convert`, plain PHP into PHP#

A design for a command that raises a project's plain PHP class files into PHP#, file by file, and proves each one before it writes it. This note is the design only. Cass approves it before any slice starts. The spike behind it is `spike/converter` at `50436cfb3499e663af8d1c6653546264df48542b`.

## The answer first

`mago convert <paths>` converts a file whole or leaves it plain PHP, and it never writes a type the analysis inferred. The spike does this end to end on a Composer fixture project with an extension host: five of its eight class files convert, three stay plain PHP with their reason, `mago compile` accepts every file, a plain PHP subclass of a converted class included, and `run.php` prints the same eleven lines on the engine before and after.

Three findings shape the work more than the code does:

- **Bare PHP function calls are the largest blocker by far.** Of 1,058 class files of a production application that the converter takes by shape, 523 call a bare PHP function, and only 30 of those call nothing but functions a declaration gives a PHP# form. The forms come from declarations only (decision 4), so coverage grows by standard library members, not by converter code. The most called functions without a form are `array_map` (125 files), `in_array` (119), `array_filter` (106), `trim` (94), `is_array` (93) and `array_keys` (62), and a framework's global helper functions block hundreds more, which no PHP# form will ever reach (decisions 076, 080 and 083).
- **Git's rename detection loses almost every converted file unless the rename is its own commit.** The conversion rewrites most lines, so `git diff --cached -M --stat` found one rename of five, and none for `Order` and `Shelf` even at `-M20%`. With `git mv` first, the index holds five `R100` renames and `git log --follow` follows each file (decision 8).
- **Two new warnings follow every conversion.** The converter writes `public` where PHP leaves visibility out, so section 5's `too-wide-member` warns on a public member nothing outside its namespace uses. A plain PHP caller's `@var Box<Special>` becomes `redundant-docblock-type` once `Box` declares its type parameter itself. The proof counts errors strictly and lists warnings by code (decision 9).

Every difference between plain PHP and PHP#, by spec section, is in [every construct](#every-construct-by-spec-section). Constructs the spec says nothing about are [questions for Jordan](#questions-for-jordan), with the number of the application's convertible files each one blocks.

## How a file converts

### Which files

The command takes a file when it is under a PSR-4 folder of `composer.json`'s `autoload`, and declares exactly one class-like, named like the file, as section 27 requires of a `.sharp` file. Every other file stays plain PHP with its reason:

- a file under no `autoload` folder is a script or a test
- a file that declares no class-like is a script
- a file that declares two types, or one named unlike the file, breaks section 27

`autoload-dev` folders are refused until a slice proves a test runner finds a converted test (decision 10). On the application, 1,058 of 1,144 files under `autoload` pass the shape check: 24 declare no class-like, and 62 declare one named unlike the file.

### Where the types come from

The command runs the project's analysis as `mago analyze` runs it, with the project's `mago.toml` and its extension hosts, and reads each declaration's type from the codebase after `analyze()`. Each `TypeMetadata` carries one of four origins, and the converter writes three:

- a native declaration: `from_docblock: false, inferred: false`
- a PHPDoc tag, as `@var list<T>`: `from_docblock: true`
- an extension host's refinement, which `apply_refinements` stores as `from_docblock: true`
- a return the analysis takes from the method's body, which `resolve_body_returns` also stores as `from_docblock: true`

The last one is inferred, and it looks like the second and third. `FunctionLikeMetadata::return_from_body` tells it apart: it holds the issue that explains the body-taken return, and is `None` for every declared or refined one. A method with `return_from_body.is_some()`, or a type with `inferred: true`, refuses its file:

```text
src/Factory.php:15:21: `make`'s return is taken from its body, which the analysis infers, and the converter writes no inferred type
```

A declaration with no type anywhere is `mixed`, which PHP# writes `Any?`. A type `get_sharp_type` returns `None` for refuses the file with the type in its reason, as `array-key`, `scalar`, `array{…}`, a bare `array`, which is `array<array-key, mixed>`, and a literal type such as the `false` of `int|false`.

### How the text changes

The output is edits on the file's own text, through `mago-text-edit`, so every comment and blank line stays. A rewrite edits only the tokens its node owns, never its children's, so nested rewrites never overlap: `$this->spec->label()` is three edits, two `->` to `.` and one `$this` to `this`.

A rewrite that moves its children renders each child first, with the edits inside it, and replaces its whole span. `count($this->items)` becomes `this.items.count()`, and `'x' . $this->quantity` becomes `` `x${this.quantity}` ``. `Edits::render(source, start, end)` returns the child's text and takes its edits out of the list, so no edit is applied twice. Two edits that still overlap are a defect, and refuse the file.

PHPDoc tags whose type the declaration now writes leave the docblock: `@var`, `@param`, `@return` and the `@template` tags. Prose and every other tag stay, and a docblock left empty goes.

### How a type prints

`types::type_text` prints an analysis type in PHP#'s spelling, as the bridge's `type_text` prints one in PHP's. A class prints by the name the file reaches it by:

- its short name, when it is in the file's own namespace
- its alias, when a `use` line names it
- its short name and a new `import` line, after the file's last `use` line, otherwise

A short name another `use` line already takes, and a PHP built-in class with no `use` line, refuse the file. Section 23 names PHP built-in classes only in the standard library.

### How a file is proven before it is written

The command puts the converted text in the server's database in place of the plain file, under the `.sharp` name, marked by `mark_sharp_file` as the loader marks a `.sharp` file it reads, and runs `analyze_incremental` on it. The file stays plain PHP when the converted file has any error, or any other file gains one. The database then gets the plain file back.

No `ignore` entry and no baseline applies here, so a language error they would hide in `mago analyze` still refuses the file. Files convert in path order, each proven against the earlier ones already converted.

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

Each line gives the plain PHP form, the PHP# form, what finds it, and the slice that converts it, or "refuses". "Tree" is the syntax tree, "type" is the analyzer's type of an expression or declaration, and "both" needs both.

### Section 1. Attributes

- `#[Name(args)]` → `[Name(args)]`, each kept with its arguments. Tree. Slice 1.

### Section 2. Variables

- `$name` → `name`, and `$let` → `@let` for a PHP# keyword. Tree. Slice 1.
- A local's first assignment → `const name = …`, `let name = …`, or `T name = …` when the value's type is not the one PHP# infers, as an empty literal. Both. Slice 1.
- `$$name`, `global`, `compact()` and `extract()` → removed. Tree. Refuses. 10 files hold `global`, 3 `compact` or `extract`.
- Superglobals → removed (section 29). Tree. Refuses. 18 files.

### Section 3. Scope

- A local first assigned in a block and read after it → declared before the block, as `T? name = null;`. Both. Slice 2. Slice 1 refuses it.

### Section 4. Member access

- `->` and `::` → `.`, and `?->` → `?.`. Tree. Slice 1.
- A static and an instance member of one name → refuses, by rule.

### Section 5. Access modifiers

- A class → `public class`, since PHP reaches every class. Tree. Slice 1.
- A member with no visibility, or `var` → `public`. Tree. Slice 1.

### Section 6. Fields and properties

- A `private` or `protected` property → a field, `private int count = 0;`. Both. Slice 1.
- A `public` property → `public T name { get; set; }`. Both. Slice 1.
- An untyped property, which PHP fills with `null` → `Any? name = null`. Both. Slice 1.
- Two names in one declaration → refuses. Tree.
- Property hooks → accessors. Tree. Slice 4.

### Section 7. Methods

- `public function name(int $a): string` → `public virtual string name(int a)`. Both. Slice 1.
- A by-reference parameter `int &$count` → no form: the checker's parameter rule refuses one, and the spec has no line on it. Tree. Refuses, "return the new value instead". 29 files.
- A by-reference return `function &name()` → refuses. Tree.

### Section 8. Functions

- A file-level function or statement → refuses, since a `.sharp` file declares one type (section 27). Tree.
- `echo`, `print`, `exit` and `die` → `Console` and `Process`, which a class receives through its constructor. Tree. Refuses. 29 files.
- A bare PHP function call → the form a declaration gives it, or refuses with its name. Both. Slice 3. See [decision 4](#decisions-cass-approves).

### Section 9. Constructors

- `__construct` → the class's name. Tree. Slice 1.
- A promoted parameter → `private Spec spec` for a field, `public string reference { get; set; }` for a property. Both. Slice 1.
- `parent::__construct(…)` as the first statement → `: super(…)`. Tree. Slice 4.
- A parent constructor call after other statements → refuses. Tree. 6 files.
- A subclass constructor whose ancestors declare none → the same constructor with no `: super()`, which `mago analyze` accepts. Tree. Slice 4. 162 files.

### Section 11. Generics

- `@template T of Spec` → `class Shelf<T : Spec>`, and `@template-covariant` → `out T`. Type. Slice 1.
- `new Box($x)` of a generic class → `new Box<Spec>(x)`, with the type arguments the analysis gives the object. Type. Slice 1.
- `@extends Base<T>` → `: Base<T>`. Type. Slice 4.

### Section 12. Collections

- `list<T>` → `List<T>`, `array<K, V>` → `Map<K, V>`. Type. Slice 1.
- `[]` where a `Map` is declared → `[:]`. Type. Slice 1.
- `$list[] = $value` → `list.add(value)`. Tree. Slice 1.
- `empty($items)` → `items.isEmpty()`, and `unset($map[$key])` → `map.remove(key)`. Both. Slice 2.
- `array` with no PHPDoc type, and an array shape → refuses with the type. Type.

### Section 13. `readonly`

- A `readonly` property or promoted parameter → `{ get; }`. Tree. Slice 1.
- A `readonly` class → each property `{ get; }`. Tree. Slice 4.

### Section 14. Functions as values

- `fn($x) => …` and `function ($x) use ($y) { … }` → `(T x) => …`. Both. Slice 5.
- A by-reference capture `use (&$total)` → a plain capture, since a lambda captures the variable itself (section 14.2). Tree. Slice 5. 26 files.
- A by-value capture `use ($total)` → the same plain capture, only when nothing writes `total` after the lambda is made. Both. Slice 5.
- `[$this, 'name']`, `'Class::name'` and `name(...)` → `this.name`. Both. Slice 5.
- A method and a property of one name → refuses, by rule (section 14.3). 51 files.
- `??` stays. `?:` → refuses (section 21).

### Section 17. Loops

- `foreach ($items as $item)` → `for (const item of items)`, and `foreach ($map as $key => $value)` → `for (const [key, value] of map)`. Both. Slice 2.
- A body without braces → braces added. Tree. Slice 2. Slice 1 refuses it.

### Section 18. Strings

- `.` between two strings → `+`. Type. Slice 1.
- `.` with a number → a template, `` `x${count}` ``. Type. Slice 1.
- `.` with a `bool`, which PHP prints as `1` or nothing, and a template as `true` or `false` → refuses. Type.
- `"Hi $name"` → `` `Hi ${name}` ``. Tree. Slice 6.
- Heredoc and nowdoc → a template. Tree. Slice 6. 7 files.
- `__toString()` → `string toString()`. Tree. Slice 4.

### Section 19. Equality, comparison and operators

- `===` and `!==` → `==` and `!=`, unless both sides are objects, where `===` stays. Type. Slice 1.
- Loose `==` → `==` when both sides have one scalar type, and refuses otherwise. Type. Slice 2.
- `instanceof` → `is`, in parentheses where `is` binds looser. Tree. Slice 1.

### Section 20. Enums

- A PHP enum → a PHP# enum. Tree. Slice 4.

### Section 21. Pattern matching

- A condition that is no `bool` → the comparison its type gives: `x != null` for `T?`, `!list.isEmpty()` for a collection, `x != "" && x != "0"` for a string. Type. Slice 2. Slice 1 refuses it.
- `is_string($x)` → `x is string`. Both. Slice 1, through decision 4.
- `switch` → `match` with block arms, when no arm falls through. Tree. Slice 6. 6 files.
- `and`, `or` and `xor` → `&&` and `||`, and `xor` refuses. Tree. Slice 2.

### Section 22. Inheritance

- `extends A implements B` → `: A, B`. Tree. Slice 1.
- An open method → `virtual`, one that replaces a parent class's → `override`, and a `final` or `private` one → nothing. Both. Slice 1.
- A trait declaration → refuses, since PHP# declares no traits. Tree. 16 files.

### Section 23. Namespaces and imports

- `namespace A\B;` → `namespace A.B;`, and `use A\B;` → `import A.B;`. Tree. Slice 1.
- A class in code → its short name and an `import`, as [how a type prints](#how-a-type-prints) says. Both. Slice 1.
- `use function` and `use const` → refuses. Tree.

### Section 24. Built-in types and `Any`

- `mixed` → `Any?`, `?T` → `T?`, `object` → `Object`. Type. Slice 1.
- `(int)`, `(float)` and `(string)` stay. `(bool)`, `(array)`, `(object)` and the aliases → refuses. Tree.

### Section 25. Referring to classes

- `X::class` → `typeof(X)`, `static` → `Self`, `self` → the class's name. Tree. Slice 1.
- A property named by a variable, `$x->$name` → refuses. Tree. 23 files.

### Section 27. PHP# files

- `<?php` and `declare(strict_types=1);` → removed, since PHP# is always strict. Tree. Slice 1.
- A file without `strict_types` → the same removal, which makes its calls strict. The proof's analysis and tests catch a call that relied on coercion. Tree. Slice 1.
- Magic constants such as `__DIR__` → `Position` (section 27). Tree. Slice 6. 66 files.

### Section 29. Effects

- `getenv()`, `echo` and `exit` → `Environment`, `Console` and `Process`, which a class receives through its constructor. Refuses until a slice injects them.

### Section 30. Tuples

- `[$a, $b] = $this->pair()` of a tuple type → `const [a, b] = this.pair();`. Both. Slice 5.

### Spec silent

Each of these refuses, and each is a [question for Jordan](#questions-for-jordan): an anonymous class, `__invoke`, `__destruct`, a `static` local, a keyed destructuring, `clone`, a method named by a variable, `include`, `require` and `eval`, the `@` silence operator, a keyed `yield`, a by-reference `foreach`, an assignment by reference, and `goto`.

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
│   │                                     pub struct Imports: new(namespace), add_use(alias, class),
│   │                                     name_of(class) -> Result<String, String>, added()
│   ├── src/library.rs                    pub struct Library: new(standard_library_files), forms(name, codebase)
│   │                                     pub enum Form { TypeTest(TUnion), Member { class, member, receiver,
│   │                                     arguments } }, pub enum Slot, pub enum Receiver
│   ├── src/declaration.rs                classes, properties, methods, constructors and parameters; crate-private
│   ├── src/body.rs                       statements and expressions; crate-private
│   └── tests/                            cases/<construct>/{input.php, output.sharp}, and project/, the
│                                         whole-project fixture
├── crates/server/src/server.rs           pub fn codebase(&self) -> &CodebaseMetadata
│                                         pub fn analyze_file_with_artifacts(&self, file_id) -> Option<(IssueCollection,
│                                         AnalysisArtifacts)>, both forwarding to the analysis service
├── crates/database/src/loader.rs         pub fn mark_sharp_file(file: &mut File, workspace: &Path)
├── src/commands/convert.rs               new: ConvertCommand { paths, dry_run }, execute(configuration, color_choice)
└── src/utils/git.rs                      pub fn move_tracked(workspace, from, to) -> Result<bool, Error>
```

`declaration.rs` and `body.rs` hold one `impl Converter` each, the state of one file's conversion. The command alone talks to the server, the file system and git, so the crate reads nothing but its `Input`.

## Slices

Each slice ends in a fixture pair per construct and a run of the whole-project proof. Slice 1 is the smallest set that converts a real class file.

1. **Declarations.** Files, namespaces and imports, classes with their header, properties and fields, methods, constructors of classes with no parent class, attributes, `$` and member access, `+` and templates, `===`, `is`, `X::class`, locals, and the type tests of decision 4. Proves a real class file converts, compiles, links to its plain PHP subclasses, and keeps git's history. The whole-project proof lands here, so every later slice runs it.
2. **Conditions and loops.** Conditions from their type, loose `==`, `empty()` and `unset()`, `foreach`, bodies without braces, and locals across blocks. Proves PHP's truthiness and loops convert with their meaning kept.
3. **Library calls.** Every form decision 4 reads, after the standard library gains its first exact forwarders for the most called functions. Proves the forms grow with the library and nothing else.
4. **Types and inheritance.** Subclass constructors, interfaces, abstract classes, enums, class constants, statics, `@extends`, property hooks, `readonly` classes and `__toString()`. Proves a whole class hierarchy converts in either order.
5. **Functions as values.** Lambdas, captures, method values and tuples. Proves capture keeps its meaning.
6. **Text and branches.** Interpolation, heredoc, magic constants and `switch`. Proves the last syntax-only constructs.

## Tests

- **A fixture pair per construct:** `crates/converter/tests/cases/<construct>/input.php` and `output.sharp`, with a `refusal.txt` instead for a refusing construct. One test runs each through the converter against a small project of the case's own files, and compares the text exactly.
- **The whole-project proof:** `crates/converter/tests/project/`, a Composer project with the PHP# Composer plugin, one PSR-4 folder, and the neutral refinement worker as its extension host. `crates/converter/tests/spike.sh` runs it today. The slice turns it into a test that checks, after `mago convert`: `mago compile` accepts every file, plain PHP subclasses included; `mago analyze` has no new error and only the warnings decision 9 lists; `run.php` prints the same output on the engine; and every test of the project's suite gives the same result, test by test, from the suite's XML report before and after.

## Decisions Cass approves

1. **The crate is `crates/converter`, `mago-converter`, and the command `mago convert <paths> [--dry-run]`.** Paths are required. Mago names its crates by what they do (`formatter`, `linter`, `analyzer`), and the bridge is the other direction's crate.
2. **The converter writes declared, PHPDoc and refined types, and never an inferred one.** It refuses a method with `return_from_body.is_some()` and any `inferred` type. A declaration with no type anywhere becomes `Any?`, and the in-memory check refuses a method whose body then fails it.
3. **Visibility keeps PHP's reach.** `public` where PHP leaves it out, every class `public`, an open method `virtual`, and a method that replaces a parent class's `override`. A narrower one is a later choice the user makes, guided by `too-wide-member`.
4. **A bare PHP function's form comes only from declarations:**
   - the prelude's type tests, each `@return ($value is T ? true : false)`, give `value is T`, or `value == null` for `null`, and a `T` PHP# can't write refuses, as `is_array` and `is_numeric` do
   - a standard library member whose body is one `global.f(…)` call reading each parameter, its receiver first, exactly once, every other argument a constant, gives that member: `str_contains(text, other)` is `contains(other) => global.str_contains(text, other)`, so it becomes `text.contains(other)`. This is the bridge's inlining rule in `lower/inline.rs`, read the other way
   - an extension member applies only when the first argument's type is its receiver's
   - every other function refuses its file, naming it

   The task's premise that the prelude declares PHP# members does not hold: `crates/prelude` holds PHP's own stubs, and the PHP# members live in `sharp/composer/library/Sharp/`. A PHP function whose stdlib member runs another one, as `trim` against `mb_trim`, has no form, because its result differs. Coverage grows by adding exact forwarders to the standard library, ranked by the census, never by a table in the converter.
5. **Nested rewrites are edits on owned tokens, and a reordering rewrite renders its children,** as [how the text changes](#how-the-text-changes) says.
6. **A type prints by the file's own names, and a missing one gets an `import`,** as [how a type prints](#how-a-type-prints) says.
7. **Each file is proven in memory before it is written,** with no `ignore` and no baseline, against the whole project with the earlier conversions in place.
8. **A tracked file moves with `git mv`, then takes its converted text.** The index holds pure renames, so the user's next commit is the rename alone and `git log --follow` follows the file. The alternative, staging the rename and the conversion together, loses the history of most files, as measured.
9. **The proof counts errors strictly and warnings by code.** A conversion may add `too-wide-member` on a member it made `public`, and `redundant-docblock-type` in a plain PHP caller whose PHPDoc the converted class now declares. The summary lists both, as work the user may do next, and any other new warning fails the proof.
10. **`autoload-dev` files stay out until a slice proves the test runner finds a converted test,** as the discovery slice found the test runner's configuration must list `suffix="Test.sharp"`.
11. **The command names what a converted file leaves:** lint, guard and format rules.
12. **Three public functions join existing modules:** `Server::codebase`, `Server::analyze_file_with_artifacts` and `loader::mark_sharp_file`. Each exposes what the server and the loader already compute.

## Questions for Jordan

Each one decides what users write. The spec says nothing about these constructs, and the note answers none. Each count is of the production application's 1,058 convertible files that hold one.

1. **A keyed destructuring, `['id' => $id] = $row;`:** 16 files.
2. **`clone` of an object, with no property set:** 13 files. Section 6.4 mentions plain PHP's `clone`, and `with` copies a value with changes.
3. **A method named by a variable, `$x->$name()`:** 10 files. Section 25 gives a property named by a variable no form, and says nothing of a method.
4. **`include`, `require` and `eval`:** 7 files.
5. **The `@` silence operator:** 5 files. In PHP# `@` marks a keyword used as a name (section 2).
6. **An anonymous class:** 2 files.
7. **A keyed `yield`:** 2 files. Section 12's `yield` gives values only.
8. **A `static` local:** 2 files.
9. **`__invoke`:** 2 files.
10. **A by-reference `foreach`:** 2 files.
11. **An assignment by reference, `$a = &$b`:** 2 files.
12. **`__destruct`:** 1 file.
13. **`goto`:** 1 file.
14. **A by-reference parameter:** 29 files. The checker refuses one, and no spec line says so.

## Found, not fixed

1. **Section 5's `too-wide-member` and the converter's `public` pull against each other.** Converting a file that plain PHP reaches from one namespace only warns on every member it made public (decision 9).
2. **A float in a template.** The spike writes `` `x${amount}` `` for `'x' . $amount` with a `float`. PHP prints `1.0` as `1`, and whether a template prints the same is unproven. Slice 1's fixture pair proves it, or the converter refuses floats there.
3. **A static method that PHP lets a subclass replace.** The spike writes `virtual` on it, as on an instance method, and no fixture holds one yet.

## Evidence

### The census

`census.php`, outside the repository, reads a production application's `composer.json` and every file under its `autoload` PSR-4 folders with PHP's tokenizer, and changes nothing. It printed:

```text
files under autoload psr-4: 1144
convertible by shape (one class-like named like the file, in its folder's namespace): 1058
  refused by shape, no class-like: 24
  refused by shape, class-like named unlike the file: 62
bare function calls: 523 convertible files call one, and 30 of them call only functions a declaration gives a form
```

The construct counts above are its output. The function coverage is an upper bound: it counts every PHP function a standard library member calls in a one-expression body, before decision 4's check that each slot is read once.

### The spike

The spike is `spike/converter` at `50436cfb3499e663af8d1c6653546264df48542b`. Its fixture is `sharp/mago/crates/converter/tests/project/`:

- `Spec`, a class with a promoted `readonly` property
- `Special`, a plain PHP subclass of `Spec`, which stays plain PHP
- `Box`, untyped, whose type parameter, property and return the refinement worker gives
- `Shelf`, typed only in PHPDoc, with `@template`, `list<T>` and `array<int, T>`
- `Order`, typed natively, with an import
- `Note`, with an untyped parameter and `is_string`
- `Factory`, whose return the analysis takes from its body, which the converter must refuse
- `Counter`, with a by-reference parameter, which it must refuse
- `Pair`, with two classes, which it must refuse
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
text hi / other
spec made
left+right

$ mago analyze --reporting-format emacs
src/Factory.php:15:21:error - declaration-refinement-proof/unsupported-type: The type this method returns cannot be resolved from its body.
run.php:46:31:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:46:38:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:46:6:error - mixed-argument: The first value for `echo` is too general.

$ mago convert src/Spec.php src/Box.php src/Factory.php src/Shop
 WARN src/Factory.php stays plain PHP:
 WARN   src/Factory.php:15:21: `make`'s return is taken from its body, which the analysis infers, and the converter writes no inferred type
 WARN src/Shop/Counter.php stays plain PHP:
 WARN   src/Shop/Counter.php:9:26: a PHP# parameter is never taken by reference, so `$count` can't be: return the new value instead
 WARN src/Shop/Pair.php stays plain PHP:
 WARN   src/Shop/Pair.php: it declares 2 types, and a .sharp file declares one (spec section 27)
 INFO Converted 5 files to PHP#, and 3 files stay plain PHP.

$ git diff --cached -M --stat
 src/{Box.php => Box.sharp}          | 0
 src/Shop/{Note.php => Note.sharp}   | 0
 src/Shop/{Order.php => Order.sharp} | 0
 src/Shop/{Shelf.php => Shelf.sharp} | 0
 src/{Spec.php => Spec.sharp}        | 0
 5 files changed, 0 insertions(+), 0 deletions(-)

$ mago compile
 INFO Compiled 64 PHP# files into .sharp/.

$ git status --short --ignored src
RM src/Box.php -> src/Box.sharp
RM src/Shop/Note.php -> src/Shop/Note.sharp
RM src/Shop/Order.php -> src/Shop/Order.sharp
RM src/Shop/Shelf.php -> src/Shop/Shelf.sharp
RM src/Spec.php -> src/Spec.sharp
!! src/Box.php
!! src/Shop/Note.php
!! src/Shop/Order.php
!! src/Shop/Shelf.php
!! src/Spec.php

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
run.php:25:10:warning - redundant-docblock-type: Redundant docblock type for variable `$box`.
run.php:46:31:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:46:38:error - mixed-method-access: Attempting to access a method on a non-object type (`mixed`).
run.php:46:6:error - mixed-argument: The first value for `echo` is too general.

$ php -n run.php
(the same eleven lines)

$ diff before after
(no difference, exit 0)
```

The four errors exist before and after: they are `Factory`'s, which stays plain PHP. The 64 compiled files are the five converted ones and the standard library's.

### One converted file

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

### A constructorless plain parent

A PHP# subclass of a plain PHP class that declares no constructor, with a constructor written with `: super()` and one written without it: `mago analyze` reported only `too-wide-member` on each, so both forms compile.
