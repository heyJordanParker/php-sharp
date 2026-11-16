--TEST--
An extern line states a plain PHP function's signature: a backed case crosses to its string parameter as its value, "checkout", a nullable one to ?string as its value or null, and a keyof argument as the member's name
--FILE--
<?php
require __DIR__ . '/harness/Routing.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('TypedExterns');

use TypedExterns\Links;

var_dump(Links::checkout(), Links::label(TypedExterns\RouteName::Checkout), Links::label(null), Links::byTotal());
?>
--EXPECT--
string(9) "/checkout"
string(8) "checkout"
string(8) "no route"
string(14) "order by total"
