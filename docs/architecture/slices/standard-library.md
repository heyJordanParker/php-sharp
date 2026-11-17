# The standard library: what a modern standard library does, in PHP#

The standard library covers what modern standard libraries cover (decision 96). Each domain below groups its operations and gives the PHP# member or language form that does each one, or **missing**. PHP's built-ins appear only in the last column, as the code a PHP# member runs, and an empty cell there is the engine's own code or PHP#'s. PHP# code reaches PHP only through the standard library (decision 83), and `[Import]` declares a built-in that no member covers (decision 93). The [gap list](#gaps) at the end collects every missing operation.

## Text

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Length in characters, in bytes | `text.length()`, `text.byteLength()` | `mb_strlen`, `strlen` |
| Substring | `substring(start, end)`, `byteSubstring(start, end)` | `mb_substr`, `substr` |
| Find | `indexOf(other, from)`, `lastIndexOf(other)` | `mb_strpos`, `mb_strrpos` |
| Contains, starts with, ends with | `contains`, `startsWith`, `endsWith` | `str_contains`, `str_starts_with`, `str_ends_with` |
| Compare, compare ignoring case | `a < b`, `a.compareTo(b)` (section 19), `a.equals(b)`; ignoring case: `ignoreCase: true` on each, and on `indexOf`, `lastIndexOf`, `contains`, `startsWith` and `endsWith` | `strcmp`, `mb_convert_case` |
| Trim | `trim()`, `trimStart()`, `trimEnd()`, `removePrefix(x)`, `removeSuffix(x)` | `mb_trim`, `mb_ltrim`, `mb_rtrim` |
| Change case | `lowercase()`, `uppercase()`, `capitalized()`, `decapitalized()`, `titlecase()` | `mb_strtolower`, `mb_strtoupper`, `mb_ucfirst`, `mb_lcfirst`, `mb_convert_case` |
| Pad | `padStart(length, padding)`, `padEnd(length, padding)` | `mb_str_pad` |
| Repeat | `repeat(times)` | `str_repeat` |
| Replace all, replace the first n | `replace(old, new)`, `replace(old, new, limit)` | `str_replace`, `explode` |
| Split, split into lines, split on whitespace | `split(delimiter, limit)`, `lines()`; on whitespace: `Regex.split` | `explode` |
| Join | `parts.join(separator)` | `implode` |
| Characters, code points, code point to text | `characters()`, `codePoints()`, `n.toCharacter()` | `mb_str_split`, `mb_ord`, `mb_chr` |
| Bytes, bytes to text | `bytes()`, `n.toByteString()` for one byte, `bytes.toByteString()` for a `List<int>` | `ord`, `chr` |
| Build text | a template `` `${n}` ``, `+` (section 18) | |
| Format numbers | `n.format()`, `x.format(decimals, decimalSeparator, thousandsSeparator)` | `number_format` |
| Parse numbers and booleans | `Int.parse`, `Int.tryParse`, `Float.parse`, `Float.tryParse`, `Bool.tryParse` | `filter_var`, `floatval` |
| Character classes | `isLetter()`, `isDigit()`, `isLetterOrDigit()`, `isWhitespace()`, `isUppercase()`, `isLowercase()` | `preg_match` |
| Unicode normalization | `normalized(form)`, with `NormalizationForm` | `normalizer_normalize`, of the `intl` extension every engine builds |
| Regular expressions | `Regex.matches`, `match`, `matchAll`, `replace`, `split`, `filter`, `escape` | `preg_match`, `preg_match_all`, `preg_replace`, `preg_split`, `preg_grep`, `preg_quote` |
| HTML escaping | `Html.escape`, `Html.unescape`, `Html.decodeEntities`, `Html.stripTags` | `htmlspecialchars`, `htmlspecialchars_decode`, `html_entity_decode`, `strip_tags` |

## Collections

| Operation | PHP# | Runs on |
| --- | --- | --- |
| A list, a map, a set | `List<T>`, `Map<K, V>`, `Set<T>` and their literals (section 12) | |
| Count, empty | `count()`, `isEmpty()`, `isNotEmpty()` | `count` |
| Read by index, safely | `list[i]`, `getOrNull(i)` | |
| First, last, first that matches, or null | `first(predicate)`, `last()`, `firstOrNull(predicate)` on a `List`, a `Map` and a `Set` | `array_last` |
| Add, insert, remove, clear | `add`, `insert`, `removeAt`, `remove`, `removeFirst`, `removeLast`, `clear` | |
| Add many, remove all that match | a spread literal `[...a, ...b]`, `removeAll(predicate)` | |
| Contains, index of | `contains`, `indexOf`, `indexOfFirst(predicate)` | |
| Map, filter, flat map, fold | `map`, `filter`, `flatMap`, `fold` | `array_reduce` |
| Any, all | `any`, `all` | |
| Sum, minimum, maximum, by a key | `sumOf(selector)`, `max()`, `min()` on a `List<int>`, `minBy(selector)`, `maxBy(selector)` | `max`, `min` |
| Sort by a key, descending, by a comparison | `sortedBy(selector)`, then by as a tuple key `sortedBy(p => [p.last, p.first])`, `sortedByDescending(selector)`, `sortedWith(comparison)` | |
| Reverse, distinct, slice, chunks | `reversed()`, `distinct()`, `slice(start, end)`, `chunked(size)` | `array_reverse`, `array_unique`, `array_slice`, `array_chunk` |
| Zip, sliding windows, partition | `zip(other)`, `windowed(size)`, `partition(predicate)` | |
| Group, index by a key | `groupBy(key)`, `associateBy(key)` | |
| Index and element | `entries()` | |
| Binary search | `binarySearch(comparison)`, `partitionPoint(predicate)` | |
| Map: read, test, write, remove | `get(key)`, `has(key)`, `map[key] = value`, `set`, `remove` | |
| Map: read or add | `getOrPut(key, defaultValue)` | |
| Map: keys, values | `keys()`, `values()` | |
| Set: union, intersection, difference, subset | `union(other)`, `intersect(other)`, `subtract(other)`, `isSubsetOf(other)` | |
| Sorted map and set | `SortedMap<TKey, TValue>`, `SortedSet<T>`, with every `Map` and `Set` member, `first()`, `last()`, `floor(key)`, `ceiling(key)` and `range(from, to)` | a sorted `List`, and the engine's `Sharp\Internal\compare` for a key's `operator <=>` |
| Queue, stack, double-ended queue | a `List` with `add`, `removeFirst` and `removeLast` | |
| Priority queue | `PriorityQueue<TElement, TPriority>`: `enqueue`, `dequeue()`, `peek()`, `count()`, `isEmpty()` | a binary heap in a `List` |
| Lazy sequences | an `Iterable`'s `filter`, `map`, `take`, `toList()` | |

## I/O and files

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Read a whole file, its lines, its lines lazily | `Files.read(path)`, `Files.readLines(path)`, `Files.readLinesLazily(path)` | `file_get_contents`, `fopen`, `fgets` |
| Write, append | `Files.write(path, text)`, `Files.append(path, text)` | `file_put_contents` |
| Open a stream, read a chunk, seek | **missing** | |
| Exists, is a directory, size, last change | `Files.exists`, `Files.isDirectory`, `Files.size`, `Files.lastModified` | `file_exists`, `is_dir`, `filesize`, `filemtime` |
| Delete, copy, move | `Files.delete`, `Files.copy`, `Files.move` | `unlink`, `copy`, `rename` |
| Create, delete, list a directory | `Files.createDirectory`, `Files.deleteDirectory`, `Files.list(directory)` | `mkdir`, `rmdir`, `scandir` |
| Walk a tree | `Files.walk(directory)`, lazy | `scandir` |
| A ZIP archive: open, create, list, read, add, extract | `ZipArchive.open(path)`, `ZipArchive.create(path)`, `list()`, `read(name)`, `write(name, text)`, `addFile(file, name)`, `extractTo(directory)`, `close()` | `ZipArchive` |
| A temporary file, the temporary directory | `Files.temporaryFile()`, `Files.temporaryDirectory()` | `tempnam`, `sys_get_temp_dir` |
| A path's parts | `Path.fileName`, `Path.directory`, `Path.extension`, `Path.fileNameWithoutExtension` | `basename`, `dirname`, `pathinfo` |
| Join paths, an absolute path | `Path.join(first, ...rest)`, `Path.absolute(path)`, which reads no disk | `getcwd` |
| Permissions, locks, watching | **missing** | |
| Console: write, write an error, read a line | `Console.write`, `Console.writeLine`, `Console.writeError`, `Console.readLine` | `file_put_contents` |

## Time

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Now, a clock a test fixes | `Clock.now()`, `new Clock.fixed(instant)` bound in a test module | `DateTimeImmutable` |
| Measure elapsed time | `Clock.timestamp()`, `Clock.elapsedSince(start)` | `hrtime` |
| Sleep | `Clock.sleep(duration)` | `usleep` |
| A length of time | `Duration.ofSeconds`, `ofMilliseconds`, `ofMinutes`, `ofHours`, `ofDays`, `milliseconds`, `seconds`, `plus`, `minus`, `times`, `negated`, `isNegative` | |
| An instant, Unix time | `Instant.ofEpochMilliseconds`, `epochSeconds`, `epochMilliseconds`, `plus`, `minus`, `until` | |
| A calendar date | `Date`, `plusDays`, `plusMonths`, `daysUntil`, `weekday`, `monthOfYear`, `lengthOfMonth`, `ofEpochDay` | |
| A time of day | `TimeOfDay`, a `DateTime`'s `timeOfDay` | |
| A date and time in a zone, time zones | `DateTime`, `instant.inZone(zone)`, `TimeZone.tryOf`, `TimeZone.names()`, `TimeZone.utc`, `zone.offsetAt(instant)` | `DateTimeZone`, `timezone_identifiers_list` |
| Parse and write ISO 8601, SQL and HTTP dates | `parse`, `tryParse`, `toString()`, `toOffsetString()`, `toSqlString()`, `DateTime.parseLocal`, `instant.toHttpDate()` | |
| Write with a custom pattern | a template of the fields, by design (spec section 24): a named format or a typed template writes a date, and a pattern string would be a second, untyped way | |
| Plain PHP dates | `toInstant()`, `toDateTime()` on a `DateTimeInterface`, `toPhpDateTime()` back | |

## Math and random

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Absolute value, clamp, minimum, maximum | `n.abs()`, `n.clamp(min, max)`, `Math.min(a, b)`, `Math.max(a, b)` | `abs`, `min`, `max` |
| Round, floor, ceiling, truncate | `x.round(decimals)`, `x.floor()`, `x.ceil()`, `(int) x` | `round`, `floor`, `ceil` |
| Power, square root, logarithms, exponential | `Math.pow`, `Math.sqrt`, `Math.log`, `Math.log10`, `Math.exp` | `sqrt`, `log`, `log10`, `exp` |
| Trigonometry | `Math.sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2` | `sin`, `cos`, `atan2` |
| Integer division, a remainder that rounds down | `a / b` on two `int`s (section 24), `a.floorDiv(b)`, `a.mod(b)` | |
| Checked, saturating arithmetic | `n.checkedAdd(m)`, `checkedSubtract`, `checkedMultiply`, `saturatingAdd`, `saturatingSubtract`, `saturatingMultiply` | |
| Big integers, decimals | `BigInteger.parse`, `of`, `+ - * / %`, `pow`, `toString(radix)`, `toIntOrNull()`; `Decimal.parse`, `of`, `+ - *`, `divide(divisor, scale, mode)`, `round(scale, mode)`, `scale`, with `RoundingMode` | `BcMath\Number`, of the `bcmath` extension every engine builds |
| Limits, constants | `Int.MAX`, `Int.MIN`, `Math.PI`, `Math.E` | `M_PI`, `M_E` |
| Finite, not a number | `x.isFinite()`, `x.isNaN()` | `is_finite`, `is_nan` |
| Write and read another base | `n.toHex()`, `Int.parse(text, radix)`, `Int.tryParse(text, radix)` | `dechex`, `intval` |
| Random numbers, bytes, a shuffle, a pick | `random.nextInt(min, max)`, `nextFloat()`, `nextBytes(length)`, `shuffle(items)`, `pick(items)` | `Random\Randomizer` |
| A seeded generator | `new Random(seed)` | `Random\Engine\Mt19937` |

## Encoding and cryptography

| Operation | PHP# | Runs on |
| --- | --- | --- |
| JSON: read any value, read a type, write | `Json.parse(text)`, `Json.decode<T>(text)`, `Json.encode(value)` | `json_decode`, `json_encode` |
| Base64, URL-safe Base64 | `Base64.encode`, `Base64.decode`; URL-safe: `urlSafe: true` on each | `base64_encode`, `strtr` |
| Hex | `Hex.encode`, `Hex.decode` | `bin2hex`, `hex2bin` |
| URL encoding, a query string | `Url.encode`, `Url.encodeForm`, `Url.decode`, `Url.buildQuery`, `Url.parseQuery` | `rawurlencode`, `http_build_query` |
| Compression | `Compression.compress`, `Compression.uncompress` | `gzcompress` |
| Integers to and from bytes | `n.toBytes(order)`, `Int.fromBytes(bytes, order)`, with `ByteOrder` | `pack`, `unpack` |
| XML | **missing** | |
| CSV | `text.parseCsv()`, `fields.toCsv()` on a `List<string>` | `str_getcsv` |
| PHP's own formats | `Serialized.encode`, `Serialized.decode(text, allowedClasses)`, `PhpLiteral.of(value)` | `serialize`, `var_export` |
| Hash, compare in constant time | `Hash.of(algorithm, data)`, `Hash.equals` | `hash`, `hash_equals` |
| HMAC | `Hmac.of(algorithm, data, key)`, `Hmac.verify` | `hash_hmac` |
| Password hashing | `Password.hash`, `Password.verify` | `password_hash` |
| Encryption | **missing** | |

## Networking

| Operation | PHP# | Runs on |
| --- | --- | --- |
| HTTP requests | `Http.get(url)`, `Http.send(request)` with an `HttpRequest` and an `HttpResponse` | `curl` |
| A URL: parse, read its parts, write | `Url.parse`, `Url.tryParse`, `scheme`, `host`, `port`, `path`, `query`, `queryValues()`, `toString()`, `Url.isValid` | `parse_url` |
| IP addresses | `Ip.isValid`, `Ip.toBinary`; the struct `IpAddress`: `parse`, `tryParse`, `family`, `isLoopback()`, `isPrivate()`, `toString()`, `==` | `inet_pton`, `inet_ntop` |
| Mail | `Mail.send(message)`, `MailMessage`, `MailAddress` | `mail` |
| TCP and UDP sockets | **missing** | |
| Name lookup | `Dns.addresses(host)`, a `List<IpAddress>` | `getaddrinfo`, through the engine's `Sharp\Internal\resolve` |
| An HTTP server | **missing**, the web framework serves | |

## Processes and environment

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Run a program and capture its output | `Process.run(program, arguments, timeout)`, a `ProcessResult` with `exitCode`, `output`, `errorOutput` | `proc_open` |
| Start a program without waiting, pipe its input | `Process.start(program, arguments)`, a `ChildProcess` with `writeInput`, `closeInput()`, `readOutput()`, `readError()`, `wait()`, `kill()` | `proc_open` |
| Exit, this process's id | `Process.exit(code)`, `Process.id()` | `exit`, `getmypid` |
| Arguments | `Environment.arguments` | |
| Environment variables | `Environment.variable(name)`, `Environment.require(name)`, `Environment.variables()`, `Environment.setVariable` | `getenv`, `putenv` |
| The current directory | `Environment.currentDirectory()`, `Environment.changeDirectory` | `getcwd`, `chdir` |
| The machine: its system, processors, name | `Environment.processorCount()`, `Environment.machineName()`; its system: **missing** | `sysconf`, through the engine's `Sharp\Internal\processorCount`, `gethostname` |
| Signals | **missing** | |
| PHP's globals and superglobals, a legacy PHP file run in the global scope | `PhpGlobals.get(name)`, `set(name, value)`, `read(which, key)`, `write(which, key, value)`, `include(path)`, with the enum `Superglobal` | `EG(symbol_table)`, through the engine's `Sharp\Internal\readGlobal`, `writeGlobal`, `includeGlobal` |

## Concurrency

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Threads, tasks | **missing** | |
| Asynchronous code | **missing** | |
| Channels | **missing** | |
| Locks, atomics | **missing**; a `Database` transaction and a `Cache` hold what processes share | |
| A value made once | a `lazy` property (section 6.5) | |
| A timeout, cancelling | a `timeout` on `Process.run` and `HttpRequest`; cancelling: **missing** | |

## Data and caching

| Operation | PHP# | Runs on |
| --- | --- | --- |
| Query rows, the first row, one value | `database.query(sql, parameters)`, `queryFirst`, `scalar` | `PDO` |
| Run a statement | `database.execute(sql, parameters)` | `PDO` |
| Transactions | `beginTransaction()`, `commit()`, `rollback()` | `PDO` |
| A cache: read, write, remove, read or compute | `cache.get`, `set`, `remove`, `getOrCreate` | `PDO` on SQLite |

## Types, reflection and events

| Operation | PHP# | Runs on |
| --- | --- | --- |
| A type's value, a value's type, its name | `typeof(T)`, `Class.of(value)`, `type.name()` (section 25) | |
| Test a type | `value is T`, `value as T` (section 21) | |
| Members, their attributes, type arguments | `methods()`, `properties()`, `attributes<T>()`, `typeArguments()` | `ReflectionClass` |
| Call, read and write a member by its reflection | `method.invoke(receiver, arguments)`, `property.get(receiver)`, `property.set(receiver, value)` | |
| Create an instance from a type | `new (type)(arguments)` (section 25) | |
| A result or an error | `Result<T, E>` with `Ok` and `Err`, and exceptions | |
| Events | `emit` (section 15), `Events.on<TEvent>(listener)`, a `Dispatcher` | |
| Services | a `Container`, modules (section 16), `Scope.run(body)` | |

## Gaps

Every operation marked **missing** above, by domain, for later batches to plan:

- I/O and files: streams that read a chunk and seek, permissions, file locks, watching.
- Encoding and cryptography: XML, encryption.
- Networking: TCP and UDP sockets, an HTTP server.
- Processes and environment: the machine's system, signals.
- Concurrency: threads and tasks, asynchronous code, channels, locks and atomics, cancelling.
