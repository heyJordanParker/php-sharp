<?php declare(strict_types=1);

// A library whose classes and functions have no namespace, as WordPress's: one folder of them.
class Blog_Post
{
    public function __construct(public string $post_title = '')
    {
    }
}

/** @var list<string> */
$GLOBALS['blog_hooks'] = [];

function blog_add_hook(string $hook, Closure $callback): bool
{
    $GLOBALS['blog_hooks'][] = $hook . ':' . $callback();

    return true;
}

/** @return list<string> */
function blog_hooks(): array
{
    return $GLOBALS['blog_hooks'];
}
