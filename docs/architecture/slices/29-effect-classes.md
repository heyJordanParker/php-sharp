# The effect classes

The standard library ships one `foreign` class per effect: `Clock`, `Random`, `Console`, `Process`, `Files`, `Http`, `Cache`, `Mail`, `Database` and `Environment`. Each lives in namespace `Sharp`, one file each, beside `sharp/composer/library/Sharp/Environment.sharp`. A module creates the object and passes it down through constructors. Each member calls the plain PHP it stands for.

`Environment` ships with foreign classes. The other nine ship in their own PR, after the structs and generic methods PRs merge, because six of them need structs and `Random` needs generic methods.

## How a plain PHP function maps to a member

Every bare call to a plain PHP function is a compile error. A User who still wants the function declares it, as in `extern global.fopen uses Files;`, and calls `global.fopen(…)`.

The bare-call message names the library member that does the same work, as in "write `files.read(path)` on the `Files` your constructor takes". The checker holds that mapping in one table. The comment on each member below lists the PHP functions its row maps to it. A function with no member gets the message without a suggestion.

The library declares no attribute. A member's effect comes from its class, as for every `foreign` class.

## Failures

- Bad input throws `ValueError`.
- An expected failure is a returned value, such as `null` for a missing file or a response with status 404.
- A failure of the environment, such as a full disk, a refused connection or an unreadable directory, throws `RuntimeException`.

Both exceptions carry the message shape `"Class.method: …"`.

## Environment

The precedents are Go's `os.Getenv`, `os.Getwd` and `os.Chdir`, and Rust's `std::env`. `currentDirectory()` reads the directory on every call, so it follows `changeDirectory`.

```csharp
public foreign class Environment
{
    public List<string> arguments { get; }           // the command line, as PHP's $argv holds it. Read once, in the constructor.

    public string? variable(string name);            // maps getenv. null when the variable is not set.

    public string currentDirectory();                // maps getcwd. RuntimeException when the directory can't be read.

    public void changeDirectory(string path);        // maps chdir. RuntimeException when the directory can't be entered.

    public static string require(string name);       // the variable's value. ValueError when it is not set.

    private static extern List<string> commandLine(); // the native body behind arguments, in ext/sharp
}
```

`putenv` has no member.

## Clock

The precedent is .NET `TimeProvider`: `GetUtcNow`, `GetTimestamp`, `GetElapsedTime` and `Delay`. A moment stays an `int` of Unix seconds, as `Date.format` and `Date.parse` take it.

```csharp
public foreign class Clock
{
    public int now();                                // maps time, gettimeofday. Unix seconds now. Never fails.

    public int timestamp();                          // maps hrtime, microtime. Monotonic nanoseconds, only for measuring. Never fails.

    public Duration elapsedSince(int start);         // the time since a timestamp(). ValueError when start is later than now.

    public void sleep(Duration duration);            // maps sleep, usleep, time_nanosleep, time_sleep_until. ValueError on a negative duration.
}
```

`date_default_timezone_get` reads configuration, not the clock, so it has no member. `localtime`, `getdate` and `mktime` read the clock only when called without a timestamp. They belong with `Date`, as `date` and `strtotime` do.

## Random

The precedent is .NET `System.Random`: `Next(min, max)` with `max` excluded, `NextDouble`, `NextBytes`, `Shuffle` and `GetItems`. The backend is PHP's `Random\Randomizer`.

```csharp
public foreign class Random
{
    public Random(int? seed = null);                 // maps mt_srand, srand. Seeded for a fake in tests, secure without a seed.

    public int nextInt(int min, int max);            // maps random_int, rand, mt_rand. min ≤ n < max, and min when they are equal. ValueError when max < min.

    public float nextFloat();                        // maps lcg_value. 0.0 ≤ n < 1.0. Never fails.

    public string nextBytes(int length);             // maps random_bytes. ValueError when length < 1.

    public List<T> shuffle<T>(List<T> items);        // maps shuffle, str_shuffle. A new list in random order.

    public T? pick<T>(List<T> items);                // maps array_rand. One random element, null for an empty list.
}
```

`str_shuffle` shuffles a string, so the message for it names `shuffle` over `Text.split`.

## Console

The precedent is .NET `Console`: `Write`, `WriteLine`, `Error.Write` and `ReadLine`. PHP# strings interpolate with `${}`, so no member takes a format string.

```csharp
public foreign class Console
{
    public void write(string text);                  // maps printf, vprintf, print_r, var_dump, fwrite, fputs, fprintf. To standard output. RuntimeException when the stream is closed.

    public void writeLine(string text);              // the same, then a newline

    public void writeError(string text);             // maps fwrite, fputs, fprintf. To standard error. RuntimeException when the stream is closed.

    public string? readLine();                       // maps readline, fgets. One line of standard input, null at the end of the input.
}
```

`fwrite`, `fputs` and `fprintf` reach standard output or standard error through the handle they take, so their message names both `write` and `writeError`. The output-buffering functions `ob_*` and `flush` have no member.

## Process

The precedents are .NET `Environment.Exit` and `Process.RunAndCaptureText`, which returns the exit code as data. The backend is `proc_open` with an argument list, so no shell string is involved.

```csharp
public foreign class Process
{
    public void exit(int code);                      // maps exit. Ends the run, skipping every finally.

    public int id();                                 // maps getmypid. This process's id.

    public ProcessResult run(string program, List<string> arguments = [], Duration? timeout = null);
                                                     // maps exec, shell_exec, system, passthru, proc_open, popen.
                                                     // A non-zero exit is a returned value. RuntimeException when the program can't start or runs past the timeout.
}

public struct ProcessResult(int exitCode, string output, string errorOutput)
{
    public bool successful => this.exitCode == 0;    // whether the program exited with 0
}
```

Mago parses `exit` as a construct, not as a function call, so the bare-call check reads the `exit` construct too. `pcntl_*` (fork and signals) has no member.

## Files

The precedent is .NET `File` and `Directory`, which read and write whole files. A missing file is a returned `null`, the library's convention for a missing value.

```csharp
public foreign class Files
{
    public string? read(string path);                // maps file_get_contents, readfile. null when the file doesn't exist. ValueError for a URL. RuntimeException when unreadable.

    public List<string>? readLines(string path);     // maps file. The lines without their endings, null when the file doesn't exist.

    public void write(string path, string contents); // maps file_put_contents. Replaces the file. RuntimeException when unwritable.

    public void append(string path, string contents); // adds to the end, as file_put_contents with FILE_APPEND does

    public bool exists(string path);                 // maps file_exists, is_file, is_readable

    public bool isDirectory(string path);            // maps is_dir

    public void delete(string path);                 // maps unlink. A missing file is not an error.

    public void copy(string from, string to);        // maps copy. RuntimeException when from is missing or to is unwritable.

    public void move(string from, string to);        // maps rename. The same failures as copy.

    public List<string>? list(string directory);     // maps scandir, glob, opendir, readdir. Entry names without . and .., null when the directory doesn't exist.

    public void createDirectory(string path);        // maps mkdir. Creates parents too. An existing directory is not an error.

    public void deleteDirectory(string path);        // maps rmdir. RuntimeException when not empty.

    public int? size(string path);                   // maps filesize. Bytes, null when missing.

    public int? lastModified(string path);           // maps filemtime, filectime, fileatime, stat. A Unix timestamp, null when missing.

    public string temporaryFile();                   // maps tempnam, sys_get_temp_dir. A new empty file's path.
}
```

The file-handle functions (`fopen`, `fclose`, `fread`, `fseek`, `ftell`, `feof`, `flock`, `ftruncate`, `fflush`, `rewind` and `tmpfile`) have no member. A User who streams a file declares them, as in `extern global.fopen uses Files;`. These functions have no member either: `chmod`, `chown`, `touch`, `realpath`, `symlink`, `link`, `is_link`, `fileperms`, `disk_free_space` and `is_writable`.

## Http

The precedent is .NET `HttpClient`, with `GetAsync` and `SendAsync`, plus `HttpRequestMessage`, `HttpResponseMessage` and `HttpMethod`. A 404 or a 500 is a returned response. The backend is curl.

```csharp
public foreign class Http
{
    public HttpResponse get(string url);             // a GET. ValueError when Url.isValid refuses the URL. RuntimeException when the connection fails or times out.

    public HttpResponse send(HttpRequest request);   // maps curl_init, curl_setopt, curl_setopt_array, curl_exec, curl_getinfo, curl_close,
                                                     // curl_multi_init, curl_multi_exec, fsockopen, stream_socket_client. The same failures as get.
}

public enum HttpMethod : string
{
    Get = "GET",
    Post = "POST",
    Put = "PUT",
    Patch = "PATCH",
    Delete = "DELETE",
    Head = "HEAD",
    Options = "OPTIONS",
}

public struct HttpRequest(HttpMethod method, string url, Map<string, string> headers = [:], string body = "", Duration? timeout = null)

public struct HttpResponse(int status, Map<string, List<string>> headers, string body)
{
    public bool successful => this.status >= 200 && this.status < 300;   // whether the status is 2xx
}
```

A JSON request body needs `Json.encode`, which the library doesn't have yet.

## Cache

No PHP built-in carries `Cache`, so no table row maps to it. The store is a SQLite file through PDO SQLite, which the runtime image ships, the way .NET's SQL Server distributed cache keeps entries in a table. The precedent is .NET `IDistributedCache`, with `Get`, `Set` and `Remove`. Values are strings, which in PHP# also cover bytes, so a User encodes a structured value with `Json`.

```csharp
public foreign class Cache
{
    public Cache(string path);                       // the SQLite file. A module sets it.

    public string? get(string key);                  // null on a miss or an expired entry. ValueError for an empty key.

    public void set(string key, string value, Duration? expiresAfter = null);   // RuntimeException when the store is unwritable.

    public void remove(string key);                  // a missing key is not an error

    public string getOrCreate(string key, Function<string()> create, Duration? expiresAfter = null) uses create;
                                                     // each call has the effects of the create it passes
}
```

## Mail

The precedent is .NET `MailMessage`, `MailAddress` and `SmtpClient.Send`. The backend is PHP's `mail()`, which hands the message to the local sendmail.

```csharp
public foreign class Mail
{
    public void send(MailMessage message);           // maps mail, mb_send_mail. RuntimeException when the local mail program refuses it.
}

public struct MailAddress(string address, string? name = null)   // ValueError when Email.isValid refuses the address

public struct MailMessage(
    MailAddress from,
    List<MailAddress> to,
    string subject,
    string textBody,
    string? htmlBody = null,
    List<MailAddress> cc = [],
    List<MailAddress> bcc = [],
    MailAddress? replyTo = null,
)
```

## Database

The precedent is .NET `DbCommand` (`ExecuteReader`, `ExecuteScalar`, `ExecuteNonQuery`) and `DbConnection.BeginTransaction`. The backend is PDO, and the runtime image ships its SQLite, PostgreSQL and MySQL drivers. A row is a `Map<string, Any?>`, which a struct's `parse` turns into a typed value.

```csharp
public foreign class Database
{
    public Database(string connection, string? user = null, string? password = null);
                                                     // maps pg_connect, mysqli_connect, sqlite_open. A PDO connection string. RuntimeException when it can't connect.

    public List<Map<string, Any?>> query(string sql, List<Any?> parameters = []);
                                                     // maps pg_query, pg_query_params, pg_fetch_all, mysqli_query, mysqli_fetch_all. Every row. PDOException for an SQL error.

    public Map<string, Any?>? queryFirst(string sql, List<Any?> parameters = []);   // the first row, null when there are none

    public Any? scalar(string sql, List<Any?> parameters = []);                     // the first column of the first row, null when there are none

    public int execute(string sql, List<Any?> parameters = []);                     // the number of rows a change affected

    public void beginTransaction();                  // PDOException when a transaction is already open

    public void commit();

    public void rollback();
}
```

The `pg_*` and `mysqli_*` families hold about 150 functions. Each maps to the nearest member.

## New types

Each type is its own file:

- `Duration`, a struct in `Sharp.Time`, as .NET's `TimeSpan`. `Clock`, `Process`, `Http` and `Cache` take it.
- `HttpMethod`, an enum, and the `HttpRequest` and `HttpResponse` structs, in `Sharp.Net`.
- `ProcessResult`, a struct in `Sharp`.
- `MailAddress` and `MailMessage`, structs in `Sharp.Net`.

```csharp
public struct Duration(int nanoseconds)
{
    public static Duration ofSeconds(int seconds);           // as .NET TimeSpan.FromSeconds

    public static Duration ofMilliseconds(int milliseconds); // as .NET TimeSpan.FromMilliseconds

    public int seconds => intdiv(this.nanoseconds, 1_000_000_000);   // whole seconds
}
```
