--TEST--
PHP# a write through a plain PHP property it reads as a List changes the property, which holds the List renumbered
--FILE--
<?php
require __DIR__ . '/harness/Roster.inc';
require __DIR__ . '/harness/fixture.inc';
fixture('PlainWrites');

$editor = new PlainWrites\Editor();
$show = static fn (array $list): string => json_encode($list);

$roster = new Lib\Roster();
echo $editor->first($roster), ' ', $show($roster->names), "\n";
$editor->add($roster);
echo $show($roster->names), "\n";

$roster = new Lib\Roster();
$editor->set($roster);
echo $show($roster->names), "\n";

$roster = new Lib\Roster();
echo $editor->bump($roster), ' ', $show($roster->scores), "\n";

$roster = new Lib\Roster();
$editor->remove($roster);
echo $show($roster->names), "\n";

$roster = new Lib\Roster();
$editor->replace($roster);
echo $show($roster->names), "\n";

$editor->addTag();
echo $show(Lib\Roster::$tags), "\n";
$editor->replaceTags();
echo $show(Lib\Roster::$tags), "\n";

$editor->addShared();
echo $show(Lib\Roster::shared()->names), "\n";

$roster = new Lib\Roster();
$editor->addAlias($roster);
$roster->aliases = null;
$editor->addAlias($roster);
echo $show(['set' => (new Lib\Roster())->aliases, 'after' => $roster->aliases]), "\n";
$roster = new Lib\Roster();
$editor->addAlias($roster);
echo $show($roster->aliases), "\n";
?>
--EXPECT--
ada {"3":"ada","7":"bo"}
["ada","bo","cy"]
["zed","bo"]
9 [5,10]
["bo"]
["p","q"]
["x","y","z"]
["p","q"]
["ada","bo","dee"]
{"set":{"5":"al"},"after":null}
["al","ali"]
