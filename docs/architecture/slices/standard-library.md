# The standard library: PHP's built-ins in PHP#

PHP# code reaches PHP only through the standard library (decision 83). Real code sets what the library covers: a production app's plain PHP makes 6195 calls to 239 distinct built-in functions. Each one below maps to a language form, a standard library member, or a leave-out with its reason. A leave-out stays reachable from a plain PHP class of the project, as `Words.distance` reaches `levenshtein` in the engine tests.

## Types, values and names

| PHP | PHP# |
| --- | --- |
| `is_string`, `is_int`, `is_float`, `is_bool`, `is_array`, `is_object`, `is_scalar`, `is_a`, `is_subclass_of`, `array_is_list` | `value is string`, `value is int`, `value is List<Any?>`, `value is Order` (section 21) |
| `is_numeric` | `Float.tryParse(text) != null` |
| `is_finite` | `x.isFinite()` |
| `intval` | `Int.parse(text)`, `(int) x` (section 24) |
| `strval` | `(string) n`, a template `` `${n}` `` |
| `define`, `defined`, `constant` | a class's `const` (section 2) |
| `compact` | a `Map` literal, `["name": name]` (section 12) |
| `func_get_args` | a rest parameter, `int ...rest` (section 7) |
| `gettype`, `get_debug_type` | leave-out: the checker knows every value's type, and its messages name it |
| `function_exists`, `class_exists`, `interface_exists`, `enum_exists`, `method_exists`, `property_exists`, `get_parent_class`, `class_implements`, `class_uses`, `get_declared_classes`, `get_object_vars`, `is_resource`, `spl_object_id` | leave-out: the checker decides what exists when it compiles, and `==` on objects compares them (section 19) |
| `token_get_all` | leave-out: it reads PHP source, which PHP# code never holds |

## Text

| PHP | PHP# |
| --- | --- |
| `strlen`, `mb_strlen` | `text.length()` |
| `substr`, `mb_substr` | `text.substring(start, end)` |
| `strpos`, `strrpos` | `text.indexOf(other, from)`, `text.lastIndexOf(other)` |
| `stripos` | `text.lowercase().indexOf(other.lowercase())` |
| `str_contains`, `str_starts_with`, `str_ends_with` | `contains`, `startsWith`, `endsWith` |
| `trim`, `ltrim`, `rtrim` | `trim()`, `trimStart()`, `trimEnd()` for whitespace, `removePrefix(x)`, `removeSuffix(x)` for given text |
| `strtolower`, `mb_strtolower`, `strtoupper` | `lowercase()`, `uppercase()` |
| `ucfirst`, `lcfirst` | `capitalized()`, `decapitalized()` |
| `ucwords` | `text.split(" ").map(word => word.capitalized()).join(" ")` |
| `str_pad` | `padStart(length, padding)`, `padEnd(length, padding)` |
| `str_repeat` | `repeat(times)` |
| `str_replace`, `strtr` | `replace(old, new)`, once for each pair |
| `substr_replace` | `text.substring(0, start) + replacement + text.substring(end)` |
| `explode` | `split(delimiter, limit)` |
| `implode` | `parts.join(separator)` on a `List<string>` |
| `str_split`, `mb_str_split` | `characters()` |
| `ord`, `mb_ord` | `codePoints()` |
| `chr` | `n.toByteString()` |
| `strcmp`, `strcasecmp` | `a < b`, `a.compareTo(b)`, `a.lowercase() == b.lowercase()` (section 19) |
| `strstr`, `strrchr`, `strpbrk`, `strspn`, `strcspn`, `strtok` | `indexOf`, `lastIndexOf` and `substring`, or `Regex.match` |
| `sprintf` | a template, with `n.format()` and `x.format(decimals)` for numbers (section 18) |
| `printf` | `Console.write(text)` with a template |
| `number_format` | `n.format()`, `x.format(decimals, decimalSeparator, thousandsSeparator)` |
| `ctype_digit`, `ctype_alnum`, `ctype_alpha`, `fnmatch` | `Regex.matches(pattern, text)` |
| `preg_match`, `preg_match_all`, `preg_replace`, `preg_split`, `preg_quote` | `Regex.match`, `Regex.matchAll`, `Regex.replace`, `Regex.split`, `Regex.escape` |
| `preg_replace_callback` | `Regex.matchAll`, and the text built from its matches |
| `htmlspecialchars`, `html_entity_decode`, `strip_tags` | `Html.escape`, `Html.decodeEntities`, `Html.stripTags` |
| `addslashes`, `addcslashes`, `stripcslashes` | leave-out: they escape text for PHP and SQL source, and `Database` takes parameters |

## Numbers

| PHP | PHP# |
| --- | --- |
| `abs` | `n.abs()` |
| `round`, `floor`, `ceil` | `x.round(decimals)`, `x.floor()`, `x.ceil()` |
| `max`, `min` | `Math.max(a, b)`, `Math.min(a, b)`, `list.max()`, `list.min()` |
| `intdiv` | `a / b` on two `int`s (section 24), and `a.floorDiv(b)` and `a.mod(b)` to round down |
| `sqrt` | `Math.sqrt(x)` |
| `dechex` | `n.toHex()` |
| `bin2hex` | `Hex.encode(bytes)` |
| `random_int`, `mt_rand`, `mt_getrandmax` | `random.nextInt(min, max)` |
| `random_bytes` | `random.nextBytes(length)` |
| `uniqid` | `Hex.encode(random.nextBytes(8))` |
| `array_rand` | `random.pick(items)` |
| `range`, `array_fill`, `array_pad` | a `for (let i = 0; i < n; i++)` loop that adds to a `List` (section 17) |

## Collections

| PHP | PHP# |
| --- | --- |
| `count` | `count()` |
| `array_map`, `array_filter`, `array_any`, `array_all`, `array_find`, `array_reduce` | `map`, `filter`, `any`, `all`, `first`, `fold` |
| `in_array`, `array_search`, `array_find_key` | `contains`, `indexOf` |
| `array_keys`, `array_values`, `array_key_exists` | `keys()`, `values()`, `has(key)` on a `Map` |
| `array_push`, `array_unshift` | `add(value)`, `insert(0, value)` |
| `array_pop`, `array_shift`, `array_splice` | `last()`, `getOrNull(0)` and `slice(start, end)` |
| `array_merge`, `array_replace` | a spread literal, `[...a, ...b]` (section 12) |
| `end`, `reset`, `array_key_first`, `array_key_last` | `last()`, `getOrNull(0)`, `keys().getOrNull(0)`, `keys().last()` |
| `array_unique`, `array_reverse`, `array_slice`, `array_chunk` | `distinct()`, `reversed()`, `slice(start, end)`, `chunked(size)` |
| `array_sum` | `sumOf(n => n)` |
| `array_column` | `rows.map(row => row.id)` |
| `array_flip`, `array_combine`, `array_fill_keys` | `associateBy(key)`, with `map` for the values |
| `array_diff`, `array_intersect`, `array_diff_key`, `array_intersect_key` | `filter` with `contains` or `has` |
| `sort`, `usort`, `uasort`, `uksort`, `ksort`, `asort`, `array_multisort` | `sortedBy(selector)` |
| `iterator_to_array` | `toList()` |
| `array_replace_recursive` | a spread literal at each level of the `Map` |

## Files, processes and the runtime

| PHP | PHP# |
| --- | --- |
| `file_get_contents`, `file`, `fopen`, `fread`, `feof`, `rewind`, `stream_get_contents` | `Files.read(path)`, `Files.readLines(path)`, `Files.readLinesLazily(path)` |
| `file_put_contents`, `fwrite`, `fclose` | `Files.write(path, text)`, `Files.append(path, text)` |
| `file_exists`, `is_file`, `is_readable`, `is_dir` | `Files.exists(path)`, `Files.isDirectory(path)` |
| `unlink`, `rename`, `copy`, `mkdir`, `rmdir`, `glob` | `Files.delete`, `Files.move`, `Files.copy`, `Files.createDirectory`, `Files.deleteDirectory`, `Files.list` |
| `filesize`, `filemtime` | `Files.size(path)`, `Files.lastModified(path)` |
| `tempnam`, `sys_get_temp_dir` | `Files.temporaryFile()` |
| `basename`, `dirname`, `pathinfo` | `Path.fileName`, `Path.directory`, `Path.extension`, `Path.fileNameWithoutExtension` |
| `hash_file` | `Hash.of(algorithm, contents)` on `Files.read(path)` |
| `proc_open`, `proc_close`, `proc_terminate` | `Process.run(program, arguments, timeout)` |
| `getmypid` | `Process.id()` |
| `usleep` | `Clock.sleep(duration)` |
| `fputcsv` | leave-out: the library reads CSV with `parseCsv` and writes none, so a project writes it through a CSV library |
| `realpath`, `clearstatcache`, `flock` | leave-out: `Files` takes paths as given, and `Database` and `Cache` hold what processes share |
| `mime_content_type`, `getimagesize` | leave-out: an upload or image library reads them |
| `stream_socket_client`, `stream_set_blocking`, `stream_select`, `dns_get_record` | leave-out: `Http` reaches the network |
| `header`, `setcookie`, `http_response_code`, `ob_start`, `ob_get_clean` | leave-out: the web framework writes the response |
| `error_log`, `error_reporting`, `ini_set`, `set_error_handler`, `extension_loaded`, `php_sapi_name`, `opcache_invalidate` | leave-out: they configure the PHP runtime |

## Time, encoding, security and the network

| PHP | PHP# |
| --- | --- |
| `time`, `microtime` | `Clock.now()`, an `Instant`, and `Clock.timestamp()` for measuring |
| `date('Y-m-d')` | `date.toString()` |
| `date('c')`, `gmdate('c')` | `dateTime.toOffsetString()`, and `instant.inZone(TimeZone.utc).toOffsetString()` for UTC |
| `gmdate('Y-m-d\TH:i:s\Z')` | `instant.toString()` |
| `date('Y-m-d H:i:s')` | `dateTime.toSqlString()`, a SQL `DATETIME` |
| `gmdate('D, d M Y H:i:s \G\M\T')` | `instant.toHttpDate()`, the HTTP date of RFC 9110 |
| `date('l')`, `date('F')`, `date('N')`, `date('n')` | `date.weekday` and `date.monthOfYear`, the enums `Weekday` and `Month`, whose `toString()` is the English name |
| `date('t')`, `date('Y-m-01')`, `date('Y-m-t')`, `date('Y-01-01')` | `date.lengthOfMonth`, and `new Date(date.year, date.month, 1)`, `new Date(date.year, date.month, date.lengthOfMonth)` and `new Date(date.year, 1, 1)` |
| `date('YmdHis')`, `format('Y-m-d\TH:i:s.uP')` | a template of a `DateTime`'s `date`, `hour`, `minute`, `second` and `nanosecond` |
| `strtotime` | `Instant.parse(text)`, `Date.parse(text)`, `DateTime.parseLocal(text, zone)` for a SQL `DATETIME` or a datetime-local value, and `plus`, `plusDays` and `plusMonths` for a relative time such as `-6 days` |
| `timezone_identifiers_list` | `TimeZone.names()` |
| `json_encode`, `json_decode` | `Json.encode(value)`, `Json.parse(text)` for any JSON value, and `Json.decode<T>(text)` for a struct or a PHP# class `T` |
| `serialize`, `unserialize` | `Serialized.encode(value)`, `Serialized.decode(text, allowedClasses)`: PHP's serialized text, which a plain PHP library's cache or queue holds, keeping each object's class |
| `var_export` | `PhpLiteral.of(value)`: the PHP code that writes the value, as a plain PHP library's generated config file holds it |
| `pack`, `unpack` | leave-out: no PHP# consumer calls them (the real app, the spec and the inventory hold none), and `Hex`, `Base64` and `Ip.toBinary` write the bytes that real code writes |
| `base64_encode`, `base64_decode` | `Base64.encode`, `Base64.decode` |
| `hash`, `md5`, `sha1`, `crc32`, `hash_equals` | `Hash.of`, `Hash.md5`, `Hash.sha1`, `Hash.crc32`, `Hash.equals` |
| `hash_hmac` | `Hmac.of(algorithm, data, key)`, and `Hmac.verify(algorithm, data, key, signature)` to check a signature |
| `password_hash` | `Password.hash`, `Password.verify` |
| `rawurlencode`, `urlencode`, `http_build_query`, `parse_str` | `Url.encode`, `Url.encodeForm`, `Url.buildQuery`, `Url.parseQuery` |
| `parse_url` | `Url.parse(text)`, a value with `scheme`, `host`, `port`, `path`, `query`, `fragment`, `user` and `password` |
| `filter_var` | `Url.isValid`, `Ip.isValid`, `MailAddress.isValid`, `Int.tryParse`, `Bool.tryParse` |
| `inet_pton` | `Ip.toBinary(address)` |
