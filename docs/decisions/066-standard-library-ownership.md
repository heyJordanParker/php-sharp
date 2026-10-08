# 66. The standard library in the spec

## Decision

"Undecided, in order" lists no standard library APIs. The standard library is specified with the library itself, by the team lead. No section holds an **Open** heading for a standard library API either. Sections 12, 15 and 28.1 state the capability, and the library specifies `keys()` and `entries()`, the dispatcher (php-sharp #56) and the structure facts (php-sharp #53).

## Options

### Chosen: the spec lists only language questions

```text
## Undecided, in order

None. The standard library is specified with the library itself, by the team lead.
```

"Undecided, in order" holds the questions that wait on the Architect. The standard library's APIs have their own owner, so the list names none of them.

### Rejected: the standard library's APIs listed under "Undecided"

```text
## Undecided, in order

None. The standard library's APIs are specified with the library itself: event dispatch, JSON decoding, the complete collection methods, and the generated structure module for rules files.
```

This was the old text. It listed APIs that the spec never decides, beside the questions it does, so a reader could not tell which items waited on the Architect.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
- [Section 15, Events](../spec.md#15-events)
- [Section 28.1, Lean files](../spec.md#281-lean-files)
- [Undecided, in order](../spec.md#undecided-in-order)
