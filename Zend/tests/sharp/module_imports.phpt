--TEST--
Once its folder's Module.sharp gains an imports line that leaves out a namespace it imports, a file does not run, refused at that import, and a file that keeps the list runs
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('module-imports', ['composer.json' => "{\"autoload\": {\"psr-4\": {\"App\\\\\": \"app/\"}}}\n"]);
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

file_put_contents("$root/app/Shared/Module.sharp", <<<'SHARP'
namespace App.Shared;

module
{
    imports App.Tenant.Community;
}

SHARP);
mkdir("$root/app/Tenant/Community", recursive: true);
file_put_contents("$root/app/Tenant/Community/Member.sharp", <<<'SHARP'
namespace App.Tenant.Community;

public class Member
{
}

SHARP);
refusal(
    ["$root/app/Shared/Ledger.sharp", "$root/app/Tenant/Store/Receipts.sharp"],
    ['sharp.compile_command' => escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check compile'],
);
remove_project($root);
?>
--EXPECT--
CompileError: app/Shared/Ledger.sharp has 1 error:
line 3: `App.Shared` imports only `App.Tenant.Community`.
  The list is the `imports` line of app/Shared/Module.sharp.
ran
