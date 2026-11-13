# 97. One way to name a library's root names

Jordan's ruling of 11-13. This amends [decision 83](083-php-through-the-standard-library.md), whose rule that a library's function of PHP's root namespace belongs to the namespace of the classes in its folder it deletes, and [decision 91](091-extern-namespace.md), whose `extern namespace` line becomes the only way to give any root name of a library a namespace.

## Decision

`extern namespace X for <name>;` names a class, function or constant a plain PHP library declares in PHP's root namespace, and gives the folder that declares it the PHP# namespace `X`. That folder's root classes, functions and constants are then named through `X`, and nothing else gives a root name a namespace: the classes beside a function no longer do. `extern namespace Illuminate.Foundation for abort;` makes `Foundation.abort(404)` and `Foundation.now()` work after `import Illuminate.Foundation;`. Without that line, the call is the error a missing function gets.

The line writes its namespace in full, as `Illuminate.Foundation`, or through the file's import of it, as `Foundation` after `import Illuminate.Foundation;`. A namespace a class already has is one only a folder that holds a class of it takes, as Laravel's `helpers.php` sits beside the classes of `Illuminate\Foundation`, since a class of the namespace would otherwise be PHP's root class of that name. A folder takes one namespace, and a namespace one folder, so a second line for either is a compile error. A root class of another folder of the library takes no namespace from the line. `[Import]` stays the way to reach PHP's own built-ins ([decision 93](093-import.md)).

## Options

### Chosen: a line names one of the folder's root names

```csharp
// app/Stubs/Laravel.sharp
namespace App.Stubs;

extern namespace Illuminate.Foundation for abort;   // compiles: helpers.php sits beside Illuminate\Foundation's classes
extern namespace Stancl.Tenancy for tenant;
```

```csharp
import Illuminate.Foundation;

Foundation.abort(404);                              // compiles: runs PHP's \abort(404)
Foundation.now();                                   // compiles: now() sits in the same folder as abort()
```

The project names, once, every library whose root names its code reaches, in its own declarations, and the checker reads no namespace from where a file sits.

### Rejected: the namespace of the classes beside the function

```csharp
import Illuminate.Foundation;

Foundation.abort(404);                              // compiles with no line: Application sits beside helpers.php
```

This was decision 83's rule. Two ways gave a root function a namespace, and one of them read it from the classes a library happens to keep beside a file, so moving a class changed what a call names.

### Rejected: a line for each function

```csharp
extern namespace Illuminate.Foundation for abort;
extern namespace Illuminate.Foundation for now;
extern namespace Illuminate.Foundation for app;
```

A helpers file holds dozens of functions, and decision 91 already gives a whole folder a namespace through one of its names.

## Precedent

- **Chosen:** C#'s `extern alias`, which gives an assembly a root namespace the code imports, and TypeScript's `declare module`, which names a library without types once for every file.
- **Rejected, the classes beside the function:** no language reads a namespace from a file's neighbors. Kotlin gives a top-level function the package its own file declares.

## Spec

- [Section 23, Namespaces and imports](../spec.md#23-namespaces-and-imports)
- [Section 29, Effects](../spec.md#29-effects)
