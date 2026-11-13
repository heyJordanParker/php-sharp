--TEST--
A .sharp file whose input is plain PHP in an includes folder that is a symlink to a folder outside the project runs right after a compile, since the engine reads the input's path as the compile recorded it, and an edit of the file behind the symlink still makes it out of date
--FILE--
<?php
require __DIR__ . '/project.inc';

$outside = sys_get_temp_dir() . '/sharp-test-symlinked-outside-' . getmypid();
$root = sys_get_temp_dir() . '/sharp-test-symlinked-' . getmypid();
remove_project($outside);
remove_project($root);
mkdir("$outside/lib", recursive: true);
mkdir("$root/src", recursive: true);
$root = realpath($root);
file_put_contents("$outside/lib/Helper.php", <<<'PHP'
<?php

namespace Lib;

final class Helper
{
    public static function one(): int
    {
        return 1;
    }
}

PHP);
symlink("$outside/lib", "$root/lib");
file_put_contents("$root/mago.toml", "[source]\npaths = [\"src\"]\nincludes = [\"lib\"]\nextensions = [\"sharp\", \"php\"]\n");
file_put_contents("$root/src/Shop.sharp", <<<'SHARP'
namespace Demo;

import Lib.Helper;

public class Shop
{
    public static int run() => Helper.one();
}

SHARP);
run_mago($root);
refusal("$root/src/Shop.sharp");

file_put_contents("$outside/lib/Helper.php", str_replace('return 1;', 'return 2;', file_get_contents("$outside/lib/Helper.php")));
refusal("$root/src/Shop.sharp");

run_mago($root);
refusal("$root/src/Shop.sharp");

unlink("$root/lib");
remove_project($root);
remove_project($outside);
?>
--EXPECTF--
ran
CompileError: src/Shop.sharp is out of date (%slib/Helper.php changed). Run vendor/bin/mago compile.
ran
