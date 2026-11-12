--TEST--
A plain PHP class's __toString(), its own or inherited, is its toString(): a template shows it and a call runs it, beside a PHP# class's toString() in one value
--FILE--
<?php
require __DIR__ . '/harness/Mail.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('Mailing');

use Lib\Email;
use Lib\WorkEmail;
use Mailing\Badge;
use Mailing\Labels;

echo Labels::to(new Email('ada@example.com')), "\n";
echo Labels::text(new Email('ada@example.com')), "\n";
echo Labels::copied(new WorkEmail('bob@example.com')), "\n";
echo Labels::either(new Email('ada@example.com')), "\n";
echo Labels::either(new Badge('A-1')), "\n";
?>
--EXPECT--
To: ada@example.com
ada@example.com
Cc: bob@example.com bob@example.com
From: ada@example.com ada@example.com
From: #A-1 #A-1
