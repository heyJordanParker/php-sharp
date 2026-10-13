--TEST--
The engine runs a .sharp file while an input that was absent at its compile stays absent, and refuses it, naming the input, once that input appears or a present one goes. An input is there only under its exact name, case included, as on Linux, so on a disk that ignores case position.php is no Position.php, for the engine and for a compile beside it
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('absent-input', ['Shop.sharp' => SHOP, 'Counter.sharp' => COUNTER]);
refusal("$root/Shop.sharp");

file_put_contents("$root/composer.lock", "{}\n");
refusal("$root/Shop.sharp");

run_mago($root);
refusal("$root/Shop.sharp");

unlink("$root/composer.lock");
refusal("$root/Shop.sharp");
remove_project($root);

// Deploy names the standard Position, so a Demo\Position in Position.php would shadow it, and that file is an input
// stamped absent.
$root = compile_project('absent-case', ['Deploy.sharp' => <<<'SHARP'
namespace Demo;

class Deploy
{
    public static string folder() => Position.current().directory;
}

SHARP]);
file_put_contents("$root/position.php", "<?php\n");
refusal("$root/Deploy.sharp");

run_mago($root);
unlink("$root/position.php");
refusal("$root/Deploy.sharp");

file_put_contents("$root/Position.php", "<?php\n");
refusal("$root/Deploy.sharp");
remove_project($root);
?>
--EXPECT--
ran
CompileError: Shop.sharp is out of date (composer.lock changed). Run vendor/bin/mago compile.
ran
CompileError: Shop.sharp is out of date (composer.lock changed). Run vendor/bin/mago compile.
ran
ran
CompileError: Deploy.sharp is out of date (Position.php changed). Run vendor/bin/mago compile.
