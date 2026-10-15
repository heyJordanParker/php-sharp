<?php

declare(strict_types=1);

// What each path of the fixture prints, under every host. sharp/bin/hosts requests them and compares the text with
// sharp/hosts/expected/.
function serve(\Sharp\Container $container, string $path): string
{
    return match ($path) {
        // The lifetimes: a scoped service, a singleton and a static.
        '/' => $container->get('App\Hosts\Report')->text(),
        // The app's own run-time types, from new and from unserialize, which every request reuses.
        '/types' => types(),
        // New type texts from input, until the app holds as many types as it keeps.
        '/flood' => flood(),
        default => "no such path: $path\n",
    };
}

function types(): string
{
    $box = \App\Hosts\Box::numbers();
    $copy = unserialize(serialize($box));

    return 'types: ' . ($copy == $box ? 'the same after unserialize' : 'changed by unserialize') . "\n"
        . 'memory: ' . memory_get_usage() . "\n";
}

function flood(): string
{
    // Box<List<int>> serialized, with its type argument text replaced by one no other request reads: its elements may
    // also be a Function type whose parameters spell a number's bits.
    $template = serialize(\App\Hosts\Box::numbers());
    for ($number = 0; $number < 1 << 17; $number++) {
        $parameters = [];
        for ($bit = 0; $bit < 17; $bit++) {
            $parameters[] = ($number >> $bit) & 1 ? 'string' : 'float?';
        }
        $text = 'List<Function<void(' . implode(', ', $parameters) . ')>|int>';
        $serialized = str_replace('s:9:"List<int>"', 's:' . strlen($text) . ':"' . $text . '"', $template);
        if (@unserialize($serialized) === false) {
            return "flood: past the limit, a new type text fails\n";
        }
    }

    return "flood: every new type text was read\n";
}
