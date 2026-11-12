# 89. Bytes, exact JSON and SQL identifiers

Jordan's rulings of 11-07 and 11-08, approved 11-11.

## Decision

**A `string` reads as its bytes too.** `byteLength()`, `byteSubstring(start, end)` and `bytes()` read a `string` as its bytes, as `length()`, `substring()` and `codePoints()` read it as characters. `byteSubstring` counts in bytes and throws `OutOfRangeException` for a range outside the bytes, and `bytes()` gives each byte as an `int` from 0 to 255. `trim`, `trimStart` and `trimEnd` take an optional text whose characters they remove instead of whitespace.

**`Json.encode` takes `escapeSlashes`, `escapeUnicode` and `keepZeroFraction`,** which default to `false`, `false` and `true`, its output before them, so a payload can match the bytes another system signs.

**`Json.parse` returns a `JsonValue`,** a struct whose `value` holds the decoded value, `null`, a `bool`, an `int`, a `float`, a `string`, a `List` or a `Map<string, …>` of them, and compares by structure: two `JsonValue`s are `==` when their values are, at every depth, an object's keys in any order and `1` equal to `1.0`. `Json.encode` writes the value it holds. An empty object and an empty array decode alike, as `Json.parse` decoded them before.

**`Sql.identifier(string name, List<string> allowed)` returns a `literal-string`** only for a name in the allow-list, and throws `ValueError` for any other, so a column or a table chosen at run time reaches a plain PHP `literal-string` parameter, as Laravel's `orderByRaw` and `selectRaw` declare one, and no composed text does. The engine declares `Sharp\Data\Sql`, since a PHP# return type can't say `literal-string`, and it returns the allow-list's own element.

## Options

### Chosen: members of `string`, three switches, a JSON value and a checked identifier

```csharp
"école".byteLength();                                   // 6
"école".bytes();                                        // [195, 169, 99, 111, 108, 101]
"école".byteSubstring(0, 2);                            // "é"
"xyx-a-xyx".trim("xy");                                 // "-a-": each character of "xy"
Json.encode(payload, escapeSlashes: true, escapeUnicode: true, keepZeroFraction: false);   // the bytes PHP's json_encode writes by default
Json.parse("{\"a\":[1,2]}") == Json.parse("{ \"a\": [1, 2] }");                        // true
query.orderByRaw(Sql.identifier(column, ["name", "created_at"]));                       // compiles: a literal-string from the allow-list
```

Each replaces a plain PHP class a project wrote under decision 83 to call `strlen`, `substr`, `unpack`, `trim` with a character list, `json_encode` with its flags, or to pick an identifier from a list, and each mirrors a name PHP# or PHP's flag already uses.

### Rejected: a `Bytes` type, as Kotlin's `ByteArray` and Swift's `Data`

```csharp
Bytes raw = Ip.toBinary("::1");                          // would need a conversion at every crossing
```

`Hex`, `Base64`, `Hash` and `Hmac` already take binary data as `string`, and plain PHP passes bytes as `string`.

### Rejected: an unchecked cast to `literal-string`

```csharp
query.orderByRaw(column as literal-string);              // would compile whatever column holds
```

It reopens the injection `literal-string` closes: any text becomes trusted SQL where the cast is written.

## Precedent

- **Chosen, bytes:** Kotlin's `encodeToByteArray()` and `trim(vararg chars)`, C#'s `Encoding.GetByteCount` and `Trim(params char[])`, Rust's `str::len()` in bytes and `trim_matches`, and Hack's `Str\length` and `Str\trim` with a `$char_mask`.
- **Chosen, JSON switches:** Swift's `JSONEncoder.OutputFormatting.withoutEscapingSlashes` and C#'s `JsonSerializerOptions.Encoder`.
- **Chosen, a JSON value with structural `==`:** kotlinx.serialization's `JsonElement`, .NET's `JsonNode.DeepEquals` and Rust's `serde_json::Value`.
- **Chosen, an allow-listed identifier:** the PHP RFC `is_literal` (Craig Francis, 2021), whose example takes a sort column from user input only by finding it in an array of allowed columns and appending that array's own literal, "so the user (attacker) cannot use anything unexpected". OWASP's SQL Injection Prevention Cheat Sheet: parameter markers "represent data values, not table names, column names, or SQL keywords", so a choice among them maps to "a small set of application-owned values allowed for that query" and anything else is rejected. psycopg's `sql.Identifier`, a separate type for a name in composed SQL, since inserting a table name as text "is an accident waiting to happen". Rails' `order`, which refuses a raw argument that names no column with `UnknownAttributeReference`.
- **Rejected, `Bytes`:** Kotlin's `ByteArray` and Swift's `Data`.
- **Rejected, an unchecked cast:** Rails' `Arel.sql()`, which marks any text as trusted SQL.

## Spec

- [Section 18, Strings](../spec.md#18-strings)
- [Section 24, Built-in types and `Any`](../spec.md#24-built-in-types-and-any)
