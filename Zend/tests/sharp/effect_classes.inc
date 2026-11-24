<?php

// The standard library's effects, the class a module binds each to and its fake, the types their members take and
// return, `Bytes` among them, the members of `string`, whose `split` `SystemHttp` runs, of a `List`, whose `map`
// `SystemMail` runs, of a `Map` and a `Set`, and of a plain PHP date, whose `toInstant` `SystemClock` runs. Each effect
// comes before the classes that extend it.
// A test requires this file, and a test that preloads it finds the classes already declared, so it skips the requires.
if (!class_exists(Sharp\Time\Clock::class, false)) {
    foreach ([
        'Int', 'ByteOrder', 'Bytes', 'Time/Duration', 'Time/Instant', 'Interop/PhpDates', 'Net/Url', 'Net/HttpMethod', 'Net/HttpRequest', 'Net/HttpResponse',
        'Net/MailAddress', 'Net/MailMessage', 'System/ProcessResult', 'System/Environment', 'Time/Clock', 'Random',
        'IO/Console', 'System/Process', 'System/ChildProcess', 'IO/Files', 'Text/StringMembers', 'Net/Http', 'Data/Cache', 'Net/Mail',
        'Net/IpFamily', 'Net/IpAddress', 'Net/Dns', 'Interop/Superglobal', 'Interop/PhpGlobals', 'Data/Database', 'ListMembers', 'MapMembers', 'SetMembers',
        'System/SystemEnvironment', 'Time/SystemClock', 'SystemRandom', 'IO/SystemConsole', 'System/SystemProcess', 'IO/SystemFiles',
        'Net/SystemHttp', 'Net/SystemMail', 'Net/SystemDns', 'Interop/SystemPhpGlobals', 'Data/SystemDatabase', 'Data/SystemCache',
        'Testing/FakeEnvironment', 'Testing/FakeClock', 'Testing/FakeRandom', 'Testing/FakeConsole', 'Testing/FakeProcess',
        'Testing/FakeFiles', 'Testing/FakeHttp', 'Testing/FakeMail', 'Testing/FakeDns', 'Testing/FakePhpGlobals',
        'Testing/FakeDatabase', 'Testing/FakeCache',
    ] as $class) {
        require __DIR__ . "/vendor/heyjordanparker/php-sharp-composer/library/Sharp/$class.sharp";
    }
}
