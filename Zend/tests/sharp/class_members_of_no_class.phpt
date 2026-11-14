--TEST--
The class value of a type that is no class, as the root bound Object, int and List<int>, has no properties(), methods() or attributes<Object>(), as .NET's typeof(object).GetProperties() gives none
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/ClassMembers.sharp';
require __DIR__ . '/../../../sharp/composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/RootBound/Members.sharp';

use RootBound\Members;

echo json_encode(Members::ofObject()), ' ', json_encode(Members::ofInt()), ' ', json_encode(Members::ofList()), "\n";
?>
--EXPECT--
[0,0,0] [0,0,0] [0,0,0]
