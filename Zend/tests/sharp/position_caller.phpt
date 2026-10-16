--TEST--
A Position.current() default gives a PHP# call's own position, and a call through a function value or from plain PHP the position of the parameter's declaration
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Callers');
require __DIR__ . '/position_caller.inc';
?>
--EXPECT--
Desk.sharp 10:13 Callers.Desk.calls
Given.sharp 1:1 Callers.given
Desk.sharp 12:13 Callers.Desk.calls
Desk.sharp 13:13 Callers.Desk.calls
Reports.sharp 5:70 Callers.Reports.logSlow
== a plain PHP caller
Reports.sharp 5:70 Callers.Reports.logSlow
Reports.sharp 7:54 Callers.Reports.seen
Note.sharp 7:33 Callers.Note.Note
