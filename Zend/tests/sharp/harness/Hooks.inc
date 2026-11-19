<?php declare(strict_types=1);

namespace Lib\Hooks;

/** What each callback a hook ran gave, in the order the hooks ran. */
final class Log
{
    /** @var list<string> */
    public static array $lines = [];
}

/** A plain PHP hook that runs its callback at once with the arguments it takes, and declares no defaults. */
function add_action(string $hook_name, callable $callback, int $priority, int $accepted_args): bool
{
    $arguments = array_slice([7, 'Hello', true], 0, $accepted_args);
    Log::$lines[] = "$hook_name, priority $priority, $accepted_args arguments: " . $callback(...$arguments);

    return true;
}
