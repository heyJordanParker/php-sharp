<?php

// The standard library's effect classes, the types their members take and return, the members of `string`, whose
// `split` `Http` runs, of a `List`, whose `map` `Mail` runs, of a `Map` and a `Set`, and of a plain PHP date, whose
// `toInstant` `Clock` runs.
// A test requires this file, and a test that preloads it finds the classes already declared, so it skips the requires.
if (!class_exists(Sharp\Time\Clock::class, false)) {
    foreach ([
        'Int', 'Time/Duration', 'Time/Instant', 'Time/DateTimeInterfaceMembers', 'Net/Url', 'Net/HttpMethod', 'Net/HttpRequest', 'Net/HttpResponse',
        'Net/MailAddress', 'Net/MailMessage', 'System/ProcessResult', 'System/Environment', 'Time/Clock', 'Random',
        'IO/Console', 'System/Process', 'IO/Files', 'Text/StringMembers', 'Net/Http', 'Data/Cache', 'Net/Mail',
        'Data/Database', 'ListMembers', 'MapMembers', 'SetMembers',
    ] as $class) {
        require __DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp";
    }
}
