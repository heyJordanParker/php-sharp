# 96. A standard library as modern ones cover

Jordan's ruling of 11-13. The standard library's coverage map followed PHP's built-ins, as one codebase called them, and listed a reason to leave each one out. It now follows what modern standard libraries cover.

## Decision

The standard library is measured against what modern standard libraries cover, domain by domain: text, collections, I/O and files, time, math and random, encoding and cryptography, networking, processes and environment, concurrency, data and caching, and types, reflection and events. Each operation those libraries offer has a PHP# member, a language form, or a place on the gap list ([the coverage map](../architecture/slices/standard-library.md)). A gap is filled with the name and the shape the modern libraries give it, and PHP's built-in is only how the member runs. No reason to leave an operation out cites what one application calls.

## Options

### Chosen: the operations modern standard libraries offer

```csharp
"7".padStart(3, "0");              // "007"
items.sortedBy(item => item.price) // the items, lowest price first
```

The map says what a developer expects a modern standard library to offer, and the gap list says what is still to build, so later batches plan from it.

### Rejected: PHP's built-ins, as one codebase calls them

```php
str_pad('7', 3, '0', STR_PAD_LEFT);   // the built-in the map was organized by
```

The map covered what one application happened to call, and its reasons to leave a built-in out held only for that application. An operation no PHP code called, as a zip or a priority queue, never reached the map at all.

## Precedent

- **Rejected:** PHP's function reference, organized by extension.

## Spec

- [Section 8, Functions](../spec.md#8-functions)
- [Section 12, Collections](../spec.md#12-collections)
- [Section 18, Strings](../spec.md#18-strings)
