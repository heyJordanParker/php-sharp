--TEST--
`break name` and `continue name` leave or continue the PHP# loop of that name from a loop inside it, from `for`, `for`-`of`, `while` and `do` loops, and a plain `break` leaves the innermost loop
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('LoopNames');
require __DIR__ . '/loop_names.inc';
?>
--EXPECT--
from for-of: ["1","2","|","3","5","|","7"]
from for: ["0.1","0.2","|","1.1","2.1","2.2","|","3.1"]
from while: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
from do: ["1.1","1.2","1.3","|","2.1","3.1","3.2","3.3","|","4.1"]
to a for whose counter each pass keeps: [0,1,2,3]
