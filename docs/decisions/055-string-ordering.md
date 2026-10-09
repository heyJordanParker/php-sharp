# 55. String ordering

## Decision

`<`, `>` and `sort` compare strings byte by byte, so `"10" < "9"` is true and capitals sort before lowercase. This agrees with `==`. `compareTo` gives -1, 0 or 1 in the same order.

## Options

### Chosen: byte order

```csharp
"10" < "9";                       // true: the byte "1" sorts before the byte "9"
"Zebra" < "apple";                // true: capitals sort before lowercase
"b".compareTo("a");               // 1
"a".compareTo("a");               // 0, and "a" == "a" is true
```

One order holds on every server and for every string. Two strings that compare equal are `==`, and two strings that are `==` compare equal.

### Rejected: PHP's comparison

```php
"10" < "9";                       // false: two numeric strings compare as numbers
"10" < "9a";                      // true: "9a" is not numeric, so the strings compare byte by byte
```

The order depends on the content, so sorting a list of codes changes when one code gains a letter. It also disagrees with `==`, which PHP# keeps strict (section 19).

### Rejected: locale collation

```php
setlocale(LC_COLLATE, "de_DE.UTF-8");
strcoll("Zebra", "apple");        // its sign depends on the locale LC_COLLATE names; under the C locale it is negative
```

The order depends on the server's locale, so the same code sorts differently on two machines.

## Precedent

- **Chosen:** Go, whose `<` on strings and `strings.Compare` order bytes.
- **Rejected, PHP's comparison:** PHP 8's string-to-string comparison.
- **Rejected, locale collation:** C's `strcoll`, which PHP exposes as `strcoll()`.

## Spec

- [Section 19, Equality, comparison and operators](../spec.md#19-equality-comparison-and-operators)
