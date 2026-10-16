# Spec check self-test

`sharp/bin/spec --self-test` checks the examples below as it checks `docs/spec.md`, then compares each example's problems with the `<!-- expect <id>` comment after it. Each example shows one rule of `sharp/bin/spec`: how it reads an annotation, how it matches the checker's errors to lines, how it compares a run with its claims, or how it completes a fragment. Most examples break their rule on purpose, so the problems they expect are the rule at work.

An expect comment lists every problem, one per line, and a one-line `<!-- expect <id> -->` expects none. A location reads `line <n>`, counted from the example's first fence, so line 1 is its first code line. `…` matches any text, so a row keeps passing when mago words a message differently.

## 1. Annotations

`compiles`, `fits:` and `accepted:` claim no error, so the value after them is never a claim:

<!-- example compiles-forms: statements -->
```csharp
int a = 3;   // compiles: 5
int b = 3;   // fits: 5
int c = 3;   // accepted: 5
```
<!-- expect compiles-forms -->

`compile error` claims an error, at the start of the annotation or after its first `:` or `;`:

<!-- example compile-error-forms: statements -->
```csharp
int a = "three";   // compile error: a string is no int
int b = "three";   // a string for an int: compile error
```
<!-- expect compile-error-forms -->

Any other text is prose, which claims no error:

<!-- example prose: statements -->
```csharp
int a = "three";   // a string for an int
```
<!-- expect prose
line 1 expects no error, and the checker reports: …
-->

`parse error` claims a parse error, and its line compiles alone, so the rest of the example still compiles:

<!-- example parse-error-form: statements -->
```csharp
let int a = 1;   // parse error: let takes no written type
const b = 2;
```
<!-- expect parse-error-form -->

A number, `true`, `false`, `null`, a quoted string or a bracketed value claims the value the line gives:

<!-- example value-forms: statements -->
```csharp
const a = 1 + 2;          // 4
const b = 1 < 2;          // false
const c = "order-42";     // "order-7"
const d = [1, 2];         // [1, 3]
```
<!-- expect value-forms
line 1 expects 4, and gives 3
line 2 expects false, and gives true
line 3 expects "order-7", and gives 'order-42'
line 4 expects [1, 3], and gives array (…
-->

`throws <Class>` claims a throw of that class, and `throws: "<message>"` a throw with that message:

<!-- example throws-forms: statements -->
```csharp
throw new NotPaid(7);     // throws NotFound
throw new NotPaid(7);     // throws: "8"
```
<!-- expect throws-forms
line 1 expects a throw of NotFound, and throws App\NotPaid: 7
line 2 expects a throw with the message "8", and throws App\NotPaid: 7
-->

## 2. Errors and lines

A `compile error` line with no error fails:

<!-- example error-accepted: statements -->
```csharp
int a = 3;   // compile error: three is an int
```
<!-- expect error-accepted
line 1 expects a compile error, and the checker accepts it
-->

A "not supported yet" refusal never satisfies a `compile error` line, because it says the feature is missing:

<!-- example refusal: members -->
```csharp
public void bump(&count) { }   // compile error: a parameter takes a value, not a reference
```
<!-- expect refusal
line 1 expects a compile error, and the checker reports: …not supported yet…
-->

A missing class satisfies a `compile error` line only when that line writes the missing name itself:

<!-- example missing-class-named-here: members -->
```csharp
public void send(Mailerr mailer) { }   // compile error: there is no Mailerr
```
<!-- expect missing-class-named-here -->

A missing class written on another line says the setup lacks a name, and stays on the line that writes it, even when that line opens the brackets around a `compile error` line:

<!-- example missing-class-named-elsewhere: members -->
```csharp
public void send(Mailerr mailer)
{
    const sent = true;   // compile error: the missing name sits on the method's line
}
```
<!-- expect missing-class-named-elsewhere
line 1 expects no error, and the checker reports: …Mailerr…
line 3 expects a compile error, and the checker accepts it
-->

A parse error that says the parser could not read the line never satisfies a `compile error` line:

<!-- example unreadable-line: statements -->
```csharp
let int a = 1;   // compile error: let takes no written type
```
<!-- expect unreadable-line
line 1 expects a compile error, and the checker reports: Parse error encountered during parsing…
-->

A message the parser writes to name a PHP# rule satisfies a `compile error` line:

<!-- example parser-rule: members -->
```csharp
public int twice(n) => n * 2;   // compile error: write the parameter's type
```
<!-- expect parser-rule -->

A `parse error` line needs a parse error:

<!-- example parse-error-needed: statements -->
```csharp
int a = "three";   // parse error
```
<!-- expect parse-error-needed
line 1 expects a parse error, and the checker reports: …
-->

An error in the marker's setup belongs to no line of the block:

<!-- example setup-error: statements
int a = "three";
-->
```csharp
const b = 2;
```
<!-- expect setup-error
line … of the generated app/Example.sharp, from the marker's setup or wrapper: …
-->

An error mago reports for a whole `match` at its first line moves to the one `compile error` line inside it:

<!-- example moves-into-arm: statements
int n = 3;
-->
```csharp
const a = match (n) {
    1 => "one",   // compile error: a match on an int needs a default arm
};
```
<!-- expect moves-into-arm -->

It stays when two `compile error` lines sit inside:

<!-- example moves-to-one-line: statements
int n = 3;
-->
```csharp
const a = match (n) {
    1 => "one",   // compile error
    2 => "two",   // compile error
};
```
<!-- expect moves-to-one-line
line 1 expects no error, and the checker reports: …
line 2 expects a compile error, and the checker accepts it
line 3 expects a compile error, and the checker accepts it
-->

It stays on a line annotated `compiles`:

<!-- example moves-from-unannotated: statements
int n = 3;
-->
```csharp
const a = match (n) {   // compiles
    1 => "one",         // compile error
};
```
<!-- expect moves-from-unannotated
line 1 expects no error, and the checker reports: …
line 2 expects a compile error, and the checker accepts it
-->

## 3. Fragments

A signature that ends in `uses` and the function parameters it names gets a body, with or without its `;`, as a library declaration such as `List.map`'s is written:

<!-- example uses-signature: members -->
```csharp
public int twice(Function<int(int)> f) uses f;
public int thrice(Function<int(int)> f) uses f
public int quoted(Function<int(int)> f) uses f readonly;
```
<!-- expect uses-signature -->

A line holding only `…` that ends a function's body becomes a throw, so a body that returns a value still compiles. Anywhere else it is dropped, so a statement after it still runs:

<!-- example elided-body: members -->
```csharp
public int total()
{
    const count = 1;
    …
}

public void refresh()
{
    …
    this.total();
}
```
<!-- expect elided-body -->
