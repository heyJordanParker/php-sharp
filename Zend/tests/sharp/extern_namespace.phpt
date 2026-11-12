--TEST--
extern namespace Blog for Blog_Post gives the folder of a namespace-less library the PHP# namespace Blog: Blog.blog_add_hook runs PHP's \blog_add_hook, and Blog.Blog_Post is PHP's \Blog_Post, in a parameter, a new and a result
--FILE--
<?php
require __DIR__ . '/harness/Blog/blog.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Publishing');

use Publishing\Feed;

var_dump(Feed::hook());
var_dump(Feed::hooks());
var_dump(Feed::title(new Blog_Post('Hello')));
var_dump(get_class(Feed::draft('Draft')));
?>
--EXPECT--
bool(true)
array(1) {
  [0]=>
  string(8) "init:ran"
}
string(5) "Hello"
string(9) "Blog_Post"
