# The standard library: what a modern standard library does, in PHP#

The standard library covers what Rust's `std` and .NET's base class library cover, the two modern standard libraries PHP# follows (decision 96). Each domain below groups operations as those libraries group them, lists the member each library offers, and gives the PHP# member or language form that does it, or **missing**. A Rust cell names a crate where `std` has none, since Rust leaves those domains to crates on purpose. PHP's built-ins appear only in the last column, as the code a PHP# member runs, and an empty cell there is the engine's own code or PHP#'s. PHP# code reaches PHP only through the standard library (decision 83), and `[Import]` declares a built-in that no member covers (decision 93). The [gap list](#gaps) at the end collects every missing operation.

## Text

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Length in characters, in bytes | `chars().count()`, `len()` | `Length`, `Encoding.UTF8.GetByteCount` | `text.length()`, `text.byteLength()` | `mb_strlen`, `strlen` |
| Substring | `&s[a..b]` | `Substring` | `substring(start, end)`, `byteSubstring(start, end)` | `mb_substr`, `substr` |
| Find | `find`, `rfind` | `IndexOf`, `LastIndexOf` | `indexOf(other, from)`, `lastIndexOf(other)` | `mb_strpos`, `mb_strrpos` |
| Contains, starts with, ends with | `contains`, `starts_with`, `ends_with` | `Contains`, `StartsWith`, `EndsWith` | `contains`, `startsWith`, `endsWith` | `str_contains`, `str_starts_with`, `str_ends_with` |
| Compare, compare ignoring case | `cmp`, `eq_ignore_ascii_case` | `CompareTo`, `string.Equals(a, b, OrdinalIgnoreCase)` | `a < b`, `a.compareTo(b)` (section 19); ignoring case: **missing**, `a.lowercase() == b.lowercase()` stands in | `strcmp` |
| Trim | `trim`, `trim_start`, `trim_end`, `strip_prefix`, `strip_suffix` | `Trim`, `TrimStart`, `TrimEnd` | `trim()`, `trimStart()`, `trimEnd()`, `removePrefix(x)`, `removeSuffix(x)` | `mb_trim`, `mb_ltrim`, `mb_rtrim` |
| Change case | `to_lowercase`, `to_uppercase` | `ToLower`, `ToUpper`, `TextInfo.ToTitleCase` | `lowercase()`, `uppercase()`, `capitalized()`, `decapitalized()`; title case: **missing** | `mb_strtolower`, `mb_strtoupper`, `mb_ucfirst`, `mb_lcfirst` |
| Pad | `format!("{:>8}")` | `PadLeft`, `PadRight` | `padStart(length, padding)`, `padEnd(length, padding)` | `mb_str_pad` |
| Repeat | `repeat` | `string.Concat(Enumerable.Repeat(s, n))` | `repeat(times)` | `str_repeat` |
| Replace all, replace the first n | `replace`, `replacen` | `Replace` | `replace(old, new)`; the first n: **missing** | `str_replace` |
| Split, split into lines, split on whitespace | `split`, `splitn`, `lines`, `split_whitespace` | `Split`, `StringReader.ReadLine` | `split(delimiter, limit)`, `lines()`; on whitespace: `Regex.split` | `explode` |
| Join | `join` | `string.Join` | `parts.join(separator)` | `implode` |
| Characters, code points, code point to text | `chars`, `char::from_u32` | `EnumerateRunes`, `char.ConvertFromUtf32` | `characters()`, `codePoints()`; code point to text: **missing** | `mb_str_split`, `mb_ord` |
| Bytes, bytes to text | `as_bytes`, `String::from_utf8` | `Encoding.UTF8.GetBytes`, `GetString` | `bytes()`, `n.toByteString()` for one byte; a list of bytes to text: **missing** | `ord`, `chr` |
| Build text | `String::push_str`, `format!` | `StringBuilder`, interpolation | a template `` `${n}` ``, `+` (section 18) | |
| Format numbers | `format!("{:.2}")` | `ToString("N2")` | `n.format()`, `x.format(decimals, decimalSeparator, thousandsSeparator)` | `number_format` |
| Parse numbers and booleans | `str::parse` | `int.Parse`, `int.TryParse`, `bool.TryParse` | `Int.parse`, `Int.tryParse`, `Float.parse`, `Float.tryParse`, `Bool.tryParse` | `filter_var`, `floatval` |
| Character classes | `char::is_alphabetic`, `is_numeric`, `is_whitespace` | `char.IsLetter`, `IsDigit`, `IsWhiteSpace` | **missing**, `Regex.matches` stands in | |
| Unicode normalization | `unicode-normalization` crate | `string.Normalize` | **missing** | |
| Regular expressions | `regex` crate | `Regex.IsMatch`, `Match`, `Matches`, `Replace`, `Split`, `Escape` | `Regex.matches`, `match`, `matchAll`, `replace`, `split`, `filter`, `escape` | `preg_match`, `preg_match_all`, `preg_replace`, `preg_split`, `preg_grep`, `preg_quote` |
| HTML escaping | `html-escape` crate | `WebUtility.HtmlEncode`, `HtmlDecode` | `Html.escape`, `Html.unescape`, `Html.decodeEntities`, `Html.stripTags` | `htmlspecialchars`, `htmlspecialchars_decode`, `html_entity_decode`, `strip_tags` |

## Collections

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| A list, a map, a set | `Vec`, `HashMap`, `HashSet` | `List<T>`, `Dictionary<K, V>`, `HashSet<T>` | `List<T>`, `Map<K, V>`, `Set<T>` and their literals (section 12) | |
| Count, empty | `len`, `is_empty` | `Count`, `Any()` | `count()`, `isEmpty()`, `isNotEmpty()` | `count` |
| Read by index, safely | `v[i]`, `get` | `list[i]`, `ElementAtOrDefault` | `list[i]`, `getOrNull(i)` | |
| First, last, first that matches, or null | `first`, `last`, `iter().find` | `First`, `Last`, `FirstOrDefault` | `first(predicate)`, `last()`; the first or null: **missing** | `array_last` |
| Add, insert, remove, clear | `push`, `insert`, `remove`, `pop`, `clear` | `Add`, `Insert`, `RemoveAt`, `Remove`, `Clear` | `add`, `insert`, `removeAt`, `remove`, `removeFirst`, `removeLast`, `clear` | |
| Add many, remove all that match | `extend`, `retain` | `AddRange`, `RemoveAll` | a spread literal `[...a, ...b]`; remove all that match: **missing**, `filter` gives a new list | |
| Contains, index of | `contains`, `iter().position` | `Contains`, `IndexOf`, `FindIndex` | `contains`, `indexOf`; the index of the first that matches: **missing** | |
| Map, filter, flat map, fold | `map`, `filter`, `flat_map`, `fold` | `Select`, `Where`, `SelectMany`, `Aggregate` | `map`, `filter`, `flatMap`, `fold` | `array_reduce` |
| Any, all | `any`, `all` | `Any`, `All` | `any`, `all` | |
| Sum, minimum, maximum, by a key | `sum`, `min`, `max`, `min_by_key`, `max_by_key` | `Sum`, `Min`, `Max`, `MinBy`, `MaxBy` | `sumOf(selector)`, `max()`, `min()` on a `List<int>`; by a key: **missing** | `max`, `min` |
| Sort by a key, descending, by a comparison | `sort_by_key`, `sort_by` | `OrderBy`, `OrderByDescending`, `ThenBy`, `Sort(Comparison)` | `sortedBy(selector)`; descending, then by, by a comparison: **missing** | |
| Reverse, distinct, slice, chunks | `reverse`, `dedup`, `&v[a..b]`, `chunks` | `Reverse`, `Distinct`, `Skip`, `Take`, `Chunk` | `reversed()`, `distinct()`, `slice(start, end)`, `chunked(size)` | `array_reverse`, `array_unique`, `array_slice`, `array_chunk` |
| Zip, sliding windows, partition | `zip`, `windows`, `partition` | `Zip`, none, none | **missing** | |
| Group, index by a key | `itertools` crate | `GroupBy`, `ToDictionary`, `ToLookup` | `groupBy(key)`, `associateBy(key)` | |
| Index and element | `enumerate` | `Select((item, index) => …)` | `entries()` | |
| Binary search | `binary_search` | `BinarySearch` | **missing** | |
| Map: read, test, write, remove | `get`, `contains_key`, `insert`, `remove` | `TryGetValue`, `ContainsKey`, `Add`, `Remove` | `get(key)`, `has(key)`, `map[key] = value`, `set`, `remove` | |
| Map: read or add | `entry().or_insert_with` | `CollectionsMarshal.GetValueRefOrAddDefault` | **missing** | |
| Map: keys, values | `keys`, `values` | `Keys`, `Values` | `keys()`, `values()` | |
| Set: union, intersection, difference, subset | `union`, `intersection`, `difference`, `is_subset` | `UnionWith`, `IntersectWith`, `ExceptWith`, `IsSubsetOf` | **missing**, `filter` with `contains` stands in | |
| Sorted map and set | `BTreeMap`, `BTreeSet` | `SortedDictionary`, `SortedSet` | **missing** | |
| Queue, stack, double-ended queue | `VecDeque` | `Queue<T>`, `Stack<T>`, `LinkedList<T>` | a `List` with `add`, `removeFirst` and `removeLast` | |
| Priority queue | `BinaryHeap` | `PriorityQueue<T, P>` | **missing** | |
| Lazy sequences | `Iterator` | `IEnumerable<T>` with deferred LINQ | an `Iterable`'s `filter`, `map`, `take`, `toList()` | |

## I/O and files

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Read a whole file, its lines, its lines lazily | `fs::read_to_string`, `BufRead::lines` | `File.ReadAllText`, `ReadAllLines`, `ReadLines` | `Files.read(path)`, `Files.readLines(path)`, `Files.readLinesLazily(path)` | `file_get_contents`, `fopen`, `fgets` |
| Write, append | `fs::write`, `OpenOptions::append` | `File.WriteAllText`, `AppendAllText` | `Files.write(path, text)`, `Files.append(path, text)` | `file_put_contents` |
| Open a stream, read a chunk, seek | `File`, `Read`, `Seek`, `BufReader` | `FileStream`, `StreamReader`, `StreamWriter` | **missing** | |
| Exists, is a directory, size, last change | `Path::exists`, `is_dir`, `fs::metadata` | `File.Exists`, `Directory.Exists`, `FileInfo.Length`, `LastWriteTimeUtc` | `Files.exists`, `Files.isDirectory`, `Files.size`, `Files.lastModified` | `file_exists`, `is_dir`, `filesize`, `filemtime` |
| Delete, copy, move | `remove_file`, `copy`, `rename` | `File.Delete`, `Copy`, `Move` | `Files.delete`, `Files.copy`, `Files.move` | `unlink`, `copy`, `rename` |
| Create, delete, list a directory | `create_dir_all`, `remove_dir_all`, `read_dir` | `Directory.CreateDirectory`, `Delete`, `EnumerateFileSystemEntries` | `Files.createDirectory`, `Files.deleteDirectory`, `Files.list(directory)` | `mkdir`, `rmdir`, `scandir` |
| Walk a tree | `walkdir` crate | `EnumerateFiles(path, "*", AllDirectories)` | **missing** | |
| A temporary file, the temporary directory | `env::temp_dir` | `Path.GetTempFileName`, `GetTempPath` | `Files.temporaryFile()`; the directory: **missing** | `tempnam` |
| A path's parts | `file_name`, `parent`, `extension`, `file_stem` | `Path.GetFileName`, `GetDirectoryName`, `GetExtension`, `GetFileNameWithoutExtension` | `Path.fileName`, `Path.directory`, `Path.extension`, `Path.fileNameWithoutExtension` | `basename`, `dirname`, `pathinfo` |
| Join paths, an absolute path | `Path::join`, `canonicalize` | `Path.Combine`, `GetFullPath` | **missing**, a template stands in for joining | |
| Permissions, locks, watching | `set_permissions`, `File::lock` | `File.SetUnixFileMode`, `FileStream.Lock`, `FileSystemWatcher` | **missing** | |
| Console: write, write an error, read a line | `print!`, `eprint!`, `stdin().read_line` | `Console.Write`, `Console.Error`, `Console.ReadLine` | `Console.write`, `Console.writeLine`, `Console.writeError`, `Console.readLine` | `file_put_contents` |

## Time

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Now, a clock a test fixes | `SystemTime::now` | `TimeProvider.GetUtcNow`, `FakeTimeProvider` | `Clock.now()`, `new Clock.fixed(instant)` bound in a test module | `DateTimeImmutable` |
| Measure elapsed time | `Instant::now`, `elapsed` | `Stopwatch` | `Clock.timestamp()`, `Clock.elapsedSince(start)` | `hrtime` |
| Sleep | `thread::sleep` | `Thread.Sleep`, `Task.Delay` | `Clock.sleep(duration)` | `usleep` |
| A length of time | `Duration::from_secs`, `as_millis` | `TimeSpan.FromSeconds`, `TotalMilliseconds` | `Duration.ofSeconds`, `ofMilliseconds`, `ofMinutes`, `ofHours`, `ofDays`, `milliseconds`, `seconds`, `plus`, `minus`, `times`, `negated`, `isNegative` | |
| An instant, Unix time | `SystemTime`, `UNIX_EPOCH`, `duration_since` | `DateTimeOffset.FromUnixTimeMilliseconds`, `ToUnixTimeMilliseconds` | `Instant.ofEpochMilliseconds`, `epochSeconds`, `epochMilliseconds`, `plus`, `minus`, `until` | |
| A calendar date | `chrono` crate | `DateOnly`, `AddDays`, `AddMonths`, `DayOfWeek` | `Date`, `plusDays`, `plusMonths`, `daysUntil`, `weekday`, `monthOfYear`, `lengthOfMonth`, `ofEpochDay` | |
| A time of day | `chrono` crate | `TimeOnly` | **missing**, a `DateTime`'s `hour`, `minute` and `second` stand in | |
| A date and time in a zone, time zones | `chrono-tz` crate | `DateTimeOffset`, `TimeZoneInfo` | `DateTime`, `instant.inZone(zone)`, `TimeZone.tryOf`, `TimeZone.names()`, `TimeZone.utc`, `zone.offsetAt(instant)` | `DateTimeZone`, `timezone_identifiers_list` |
| Parse and write ISO 8601, SQL and HTTP dates | `chrono` crate | `DateTimeOffset.Parse`, `ToString("o")`, `ToString("R")` | `parse`, `tryParse`, `toString()`, `toOffsetString()`, `toSqlString()`, `DateTime.parseLocal`, `instant.toHttpDate()` | |
| Write with a custom pattern | `chrono` crate | `ToString("yyyy-MM-dd")` | **missing**, a template of the fields stands in | |
| Plain PHP dates | | | `toInstant()`, `toDateTime()` on a `DateTimeInterface`, `toPhpDateTime()` back | |

## Math and random

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Absolute value, clamp, minimum, maximum | `abs`, `clamp`, `min`, `max` | `Math.Abs`, `Clamp`, `Min`, `Max` | `n.abs()`, `n.clamp(min, max)`, `Math.min(a, b)`, `Math.max(a, b)` | `abs`, `min`, `max` |
| Round, floor, ceiling, truncate | `round`, `floor`, `ceil`, `trunc` | `Math.Round`, `Floor`, `Ceiling`, `Truncate` | `x.round(decimals)`, `x.floor()`, `x.ceil()`, `(int) x` | `round`, `floor`, `ceil` |
| Power, square root, logarithms, exponential | `powf`, `sqrt`, `ln`, `log10`, `exp` | `Math.Pow`, `Sqrt`, `Log`, `Log10`, `Exp` | `Math.pow`, `Math.sqrt`, `Math.log`; `log10` and `exp`: **missing** | `sqrt`, `log` |
| Trigonometry | `sin`, `cos`, `atan2` | `Math.Sin`, `Cos`, `Atan2` | **missing** | |
| Integer division, a remainder that rounds down | `/`, `div_euclid`, `rem_euclid` | `/`, `Math.DivRem` | `a / b` on two `int`s (section 24), `a.floorDiv(b)`, `a.mod(b)` | |
| Checked, saturating arithmetic | `checked_add`, `saturating_add` | `checked`, `Math.Clamp` | **missing** | |
| Big integers, decimals | `num-bigint` crate | `BigInteger`, `decimal` | **missing** | |
| Limits, constants | `i64::MAX`, `f64::consts::PI` | `long.MaxValue`, `Math.PI`, `Math.E` | `Int.MAX`, `Int.MIN`; `PI` and `E`: **missing** | |
| Finite, not a number | `is_finite`, `is_nan` | `double.IsFinite`, `IsNaN` | `x.isFinite()`; `isNaN`: **missing** | `is_finite` |
| Write and read another base | `format!("{:x}")`, `from_str_radix` | `ToString("x")`, `Convert.ToInt64(text, 16)` | `n.toHex()`; reading one: **missing** | `dechex` |
| Random numbers, bytes, a shuffle, a pick | `rand` crate | `Random.Next`, `NextDouble`, `NextBytes`, `Shuffle`, `GetItems` | `random.nextInt(min, max)`, `nextFloat()`, `nextBytes(length)`, `shuffle(items)`, `pick(items)` | `Random\Randomizer` |
| A seeded generator | `rand` crate `seed_from_u64` | `new Random(seed)` | `new Random(seed)` | `Random\Engine\Mt19937` |

## Encoding and cryptography

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| JSON: read any value, read a type, write | `serde_json` crate | `JsonDocument.Parse`, `JsonSerializer.Deserialize<T>`, `Serialize` | `Json.parse(text)`, `Json.decode<T>(text)`, `Json.encode(value)` | `json_decode`, `json_encode` |
| Base64, URL-safe Base64 | `base64` crate | `Convert.ToBase64String`, `FromBase64String`, `Base64Url` | `Base64.encode`, `Base64.decode`; URL-safe: **missing** | `base64_encode` |
| Hex | `hex` crate | `Convert.ToHexString`, `FromHexString` | `Hex.encode`, `Hex.decode` | `bin2hex`, `hex2bin` |
| URL encoding, a query string | `percent-encoding` crate | `Uri.EscapeDataString`, `HttpUtility.ParseQueryString` | `Url.encode`, `Url.encodeForm`, `Url.decode`, `Url.buildQuery`, `Url.parseQuery` | `rawurlencode`, `http_build_query` |
| Compression | `flate2` crate | `GZipStream`, `ZLibStream`, `BrotliStream` | `Compression.compress`, `Compression.uncompress` | `gzcompress` |
| Integers to and from bytes | `to_le_bytes`, `from_le_bytes` | `BinaryPrimitives`, `BitConverter` | **missing** | |
| XML | `quick-xml` crate | `XDocument`, `XmlReader` | **missing** | |
| CSV | `csv` crate | none | `text.parseCsv()`; writing: **missing** | `str_getcsv` |
| PHP's own formats | | | `Serialized.encode`, `Serialized.decode(text, allowedClasses)`, `PhpLiteral.of(value)` | `serialize`, `var_export` |
| Hash, compare in constant time | `sha2` crate | `SHA256.HashData`, `CryptographicOperations.FixedTimeEquals` | `Hash.of(algorithm, data)`, `Hash.equals` | `hash`, `hash_equals` |
| HMAC | `hmac` crate | `HMACSHA256.HashData` | `Hmac.of(algorithm, data, key)`, `Hmac.verify` | `hash_hmac` |
| Password hashing | `argon2` crate | `Rfc2898DeriveBytes.Pbkdf2` | `Password.hash`, `Password.verify` | `password_hash` |
| Encryption | `aes-gcm` crate | `AesGcm`, `RSA` | **missing** | |

## Networking

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| HTTP requests | `reqwest` crate | `HttpClient.GetAsync`, `SendAsync`, `HttpRequestMessage` | `Http.get(url)`, `Http.send(request)` with an `HttpRequest` and an `HttpResponse` | `curl` |
| A URL: parse, read its parts, write | `url` crate | `Uri`, `UriBuilder` | `Url.parse`, `Url.tryParse`, `scheme`, `host`, `port`, `path`, `query`, `queryValues()`, `toString()`, `Url.isValid` | `parse_url` |
| IP addresses | `IpAddr::from_str`, `octets` | `IPAddress.Parse`, `TryParse`, `GetAddressBytes` | `Ip.isValid`, `Ip.toBinary`; an address value: **missing** | `inet_pton` |
| Mail | `lettre` crate | `SmtpClient`, `MailMessage`, `MailAddress` | `Mail.send(message)`, `MailMessage`, `MailAddress` | `mail` |
| TCP and UDP sockets | `TcpStream`, `TcpListener`, `UdpSocket` | `Socket`, `TcpClient`, `UdpClient` | **missing** | |
| Name lookup | `ToSocketAddrs` | `Dns.GetHostAddresses` | **missing** | |
| An HTTP server | `hyper` crate | `HttpListener` | **missing**, the web framework serves | |

## Processes and environment

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Run a program and capture its output | `Command::output` | `Process.Start`, `StandardOutput` | `Process.run(program, arguments, timeout)`, a `ProcessResult` with `exitCode`, `output`, `errorOutput` | `proc_open` |
| Start a program without waiting, pipe its input | `Command::spawn`, `Child::stdin` | `Process.Start`, `StandardInput` | **missing** | |
| Exit, this process's id | `process::exit`, `process::id` | `Environment.Exit`, `Environment.ProcessId` | `Process.exit(code)`, `Process.id()` | `exit`, `getmypid` |
| Arguments | `env::args` | `Environment.GetCommandLineArgs` | `Environment.arguments` | |
| Environment variables | `env::var`, `vars`, `set_var` | `GetEnvironmentVariable`, `GetEnvironmentVariables`, `SetEnvironmentVariable` | `Environment.variable(name)`, `Environment.require(name)`, `Environment.variables()`, `Environment.setVariable` | `getenv`, `putenv` |
| The current directory | `env::current_dir`, `set_current_dir` | `Environment.CurrentDirectory` | `Environment.currentDirectory()`, `Environment.changeDirectory` | `getcwd`, `chdir` |
| The machine: its system, processors, name | `env::consts::OS`, `thread::available_parallelism` | `Environment.OSVersion`, `ProcessorCount`, `MachineName` | **missing** | |
| Signals | `signal-hook` crate | `PosixSignalRegistration` | **missing** | |

## Concurrency

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Threads, tasks | `thread::spawn`, `thread::scope` | `Thread`, `Task.Run`, `Parallel.ForEach` | **missing** | |
| Asynchronous code | `async`, `Future` | `async`, `Task`, `ValueTask` | **missing** | |
| Channels | `mpsc::channel` | `Channel<T>` | **missing** | |
| Locks, atomics | `Mutex`, `RwLock`, `AtomicUsize` | `lock`, `SemaphoreSlim`, `Interlocked` | **missing**; a `Database` transaction and a `Cache` hold what processes share | |
| A value made once | `OnceLock`, `LazyLock` | `Lazy<T>` | a `lazy` property (section 6.5) | |
| A timeout, cancelling | none | `CancellationToken` | a `timeout` on `Process.run` and `HttpRequest`; cancelling: **missing** | |

## Data and caching

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| Query rows, the first row, one value | `sqlx` crate | `DbCommand.ExecuteReader`, `ExecuteScalar` | `database.query(sql, parameters)`, `queryFirst`, `scalar` | `PDO` |
| Run a statement | `sqlx` crate | `DbCommand.ExecuteNonQuery` | `database.execute(sql, parameters)` | `PDO` |
| Transactions | `sqlx` crate | `DbTransaction` | `beginTransaction()`, `commit()`, `rollback()` | `PDO` |
| A cache: read, write, remove, read or compute | `moka` crate | `MemoryCache.Get`, `Set`, `Remove`, `GetOrCreate` | `cache.get`, `set`, `remove`, `getOrCreate` | `PDO` on SQLite |

## Types, reflection and events

| Operation | Rust | .NET | PHP# | Runs on |
| --- | --- | --- | --- | --- |
| A type's value, a value's type, its name | `TypeId::of`, `type_name` | `typeof`, `GetType()`, `Type.Name` | `typeof(T)`, `Class.of(value)`, `type.name()` (section 25) | |
| Test a type | `Any::downcast_ref` | `is`, `as` | `value is T`, `value as T` (section 21) | |
| Members, their attributes, type arguments | none | `GetMethods`, `GetProperties`, `GetCustomAttributes`, `GetGenericArguments` | `methods()`, `properties()`, `attributes<T>()`, `typeArguments()` | `ReflectionClass` |
| Call, read and write a member by its reflection | none | `MethodInfo.Invoke`, `PropertyInfo.GetValue`, `SetValue` | `method.invoke(receiver, arguments)`, `property.get(receiver)`, `property.set(receiver, value)` | |
| Create an instance from a type | none | `Activator.CreateInstance` | `new (type)(arguments)` (section 25) | |
| A result or an error | `Result<T, E>` | exceptions | `Result<T, E>` with `Ok` and `Err`, and exceptions | |
| Events | none | `event`, `EventHandler` | `emit` (section 15), `Events.on<TEvent>(listener)`, a `Dispatcher` | |
| Services | none | `IServiceProvider` | a `Container`, modules (section 16), `Scope.run(body)` | |

## Gaps

Every operation marked **missing** above, by domain, for later batches to plan:

- Text: compare and find ignoring case, title case, replace the first n, a code point to text, a list of bytes to text, character classes, Unicode normalization.
- Collections: the first that matches or null, remove all that match, the index of the first that matches, minimum and maximum by a key, sort descending, then by and by a comparison, zip, sliding windows, partition, binary search, a map's read or add, set union, intersection, difference and subset, a sorted map and set, a priority queue.
- I/O and files: streams that read a chunk and seek, walking a tree, the temporary directory, joining paths, an absolute path, permissions, file locks, watching.
- Time: a time of day, writing with a custom pattern.
- Math and random: `log10`, `exp`, trigonometry, checked and saturating arithmetic, big integers and decimals, `PI` and `E`, `isNaN`, reading a number in another base.
- Encoding and cryptography: URL-safe Base64, integers to and from bytes, XML, writing CSV, encryption.
- Networking: an IP address value, TCP and UDP sockets, name lookup, an HTTP server.
- Processes and environment: starting a program without waiting and piping its input, the machine's system, processors and name, signals.
- Concurrency: threads and tasks, asynchronous code, channels, locks and atomics, cancelling.
