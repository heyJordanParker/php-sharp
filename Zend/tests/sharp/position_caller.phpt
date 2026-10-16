--TEST--
A Position.current() default gives each direct PHP# call its own position, and a call through a function value, a spread that may fill the parameter, or plain PHP the position of the parameter's declaration
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('Callers');
require __DIR__ . '/position_caller.inc';
?>
--EXPECT--
Desk.sharp 13:13 Callers.Desk.calls
Given.sharp 1:1 Callers.given
Desk.sharp 15:13 Callers.Desk.calls
Desk.sharp 16:13 Callers.Desk.calls
Reports.sharp 5:70 Callers.Reports.logSlow
Desk.sharp 18:13 Callers.Desk.calls
Note.sharp 14:27 Callers.Note.blank
Copy.sharp 5:21 Callers.Copy.Copy
Desk.sharp 21:13 Callers.Desk.calls
Desk.sharp 22:13 Callers.Desk.calls
Desk.sharp 23:13 Callers.Desk.calls
Desk.sharp 24:13 Callers.Desk.calls
Desk.sharp 25:13 Callers.Desk.calls
Reports.sharp 5:70 Callers.Reports.logSlow
== a plain PHP caller
Reports.sharp 5:70 Callers.Reports.logSlow
Reports.sharp 7:54 Callers.Reports.seen
Note.sharp 7:33 Callers.Note.Note
