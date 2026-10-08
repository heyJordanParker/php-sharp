# 65. `map.has(key)`

## Decision

`map.has(key)` tells a missing key from a stored null. This replaces "until the standard library's methods tell them apart".

## Options

### Chosen: `has`

```csharp
Map<string, int?> limits = ["pro": null];
limits["pro"] ?? 0;          // 0: the stored null
limits["team"] ?? 0;         // 0: the missing key
limits.has("pro");           // true
limits.has("team");          // false
```

A read gives `int?` as Kotlin's does (section 12), and one method answers the one question a read can't.

### Rejected: PHP's `isset`

```php
$limits = ["pro" => null];
isset($limits["pro"]);       // false: a stored null reads as missing
```

`isset` treats a stored null as a missing key, which is the confusion `has` exists to remove. Telling them apart in PHP takes a second function, `array_key_exists`.

## Precedent

- **Chosen:** Kotlin's `containsKey`.
- **Rejected:** PHP's `isset`.

## Spec

- [Section 12, Collections](../spec.md#12-collections)
