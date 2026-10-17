--TEST--
PHP# `@` before a keyword makes it a name: an override keeps a plain PHP parent's `$event` as `@event`, which a named argument `event:` reaches from plain PHP and PHP#, and `@this` names a parameter, a lambda parameter and a local beside the object `this`, as names PHP gives its superglobals run beside them
--FILE--
<?php
require __DIR__ . '/harness/Behavior.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('KeywordNames');

$form = new KeywordNames\Form();
echo $form->submit(form: 'x', event: 'y'), "\n";
echo $form->submit('p', 'q'), "\n";
echo $form->run(match: true), "\n";
echo $form->label('  hi  '), "\n";
echo $form->each(['a', 'b', 'c']), "\n";
echo $form->tested('text'), ' ', $form->tested(4), "\n";
echo $form->wrapped('a'), "\n";
echo $form->named(), "\n";

// The parameter `@this` runs under a PHP name a plain PHP caller never writes, so a named `this:` reaches nothing.
try {
    $form->label(this: 'x');
} catch (Error $error) {
    echo $error->getMessage(), "\n";
}

// A property, a parameter, a local, a lambda parameter and a catch variable named as PHP's superglobals are PHP#'s
// own, and PHP's `$_SERVER` and `$_GET` keep their values.
$_GET = ['page' => '1'];
$request = new KeywordNames\Request('srv');
echo $request->_SERVER, "\n";
echo $request->read('g'), "\n";
echo $request->named(), "\n";
var_dump(isset($_SERVER['argv']), $_GET);
?>
--EXPECT--
x:y
p:q
matched
[hi]form
abc
text none
<a>
a:b d:c [e]form
Unknown named parameter $this
srv
g!g! srv
n!n! srv
bool(true)
array(1) {
  ["page"]=>
  string(1) "1"
}
