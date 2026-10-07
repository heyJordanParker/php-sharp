--TEST--
JIT: FETCH_OBJ_R of a hooked property frees the value its get hook returns
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=function
--FILE--
<?php

class Page
{
    public function __construct(private string $name) {}

    public string $slug {
        get => strtolower($this->name);
    }

    public function link(): string
    {
        return "/page/" . $this->slug;
    }
}

echo (new Page('About Us'))->link(), "\n";

class Post
{
    public string $slug = 'home';

    public function __construct(protected string $name) {}

    public function link(): string
    {
        return "/post/" . $this->slug;
    }
}

class Article extends Post
{
    public string $slug {
        get => strtolower($this->name);
    }
}

echo (new Article('Contact Us'))->link(), "\n";
?>
--EXPECT--
/page/about us
/post/contact us
