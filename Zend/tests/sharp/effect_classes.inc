<?php

// The standard library's effect classes and the types their members take and return. A test requires this file, and
// a test that preloads it finds the classes already declared, so it skips the requires.
if (!class_exists(Sharp\Clock::class, false)) {
    foreach ([
        'Time/Duration', 'Net/Url', 'Net/Email', 'Net/HttpMethod', 'Net/HttpRequest', 'Net/HttpResponse',
        'Net/MailAddress', 'Net/MailMessage', 'ProcessResult', 'Environment', 'Clock', 'Random', 'Console', 'Process',
        'Files', 'Http', 'Cache', 'Mail', 'Database',
    ] as $class) {
        require __DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp";
    }
}
