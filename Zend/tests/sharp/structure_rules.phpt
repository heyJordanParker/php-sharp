--TEST--
Once its folder's Module.lean gains a structure rule it breaks, a file does not run, refused on the line that breaks it, and a file that keeps every rule runs
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('structure-rules', ['composer.json' => "{\"autoload\": {\"psr-4\": {\"App\\\\\": \"app/\"}}}\n"]);
mkdir("$root/app/Shared", recursive: true);
mkdir("$root/app/Tenant/Store", recursive: true);
file_put_contents("$root/app/Shared/Ledger.sharp", <<<'SHARP'
namespace App.Shared;

import App.Tenant.Store.Receipts;

public class Ledger
{
    public static int refundable(int paid) => Receipts.refundable(paid);
}

SHARP);
file_put_contents("$root/app/Tenant/Store/Receipts.sharp", <<<'SHARP'
namespace App.Tenant.Store;

public class Receipts
{
    public static int refundable(int paid) => paid;
}

SHARP);
run_mago($root);

file_put_contents("$root/app/Shared/Module.lean", <<<'LEAN'
import Code.App.Shared.Module
open Sharp

@[rule] def sharedNeverReachesTenant : Rule := .neverImports App.Tenant.Store

LEAN);
refusal(
    ["$root/app/Shared/Ledger.sharp", "$root/app/Tenant/Store/Receipts.sharp"],
    ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'],
);
remove_project($root);
?>
--EXPECT--
CompileError: app/Shared/Ledger.sharp has 1 error:
line 3: This import of App.Tenant.Store.Receipts breaks the structure rule sharedNeverReachesTenant in app/Shared/Module.lean.
ran
