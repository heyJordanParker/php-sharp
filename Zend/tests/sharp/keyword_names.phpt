--TEST--
PHP# `@` before a keyword makes it a name: an override keeps a plain PHP parent's `$event` as `@event`, which a named argument `event:` reaches from plain PHP and PHP#, and `@this` names a parameter, a lambda parameter and a local beside the object `this`
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
