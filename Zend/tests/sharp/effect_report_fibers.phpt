--TEST--
Spec section 29: under sharp.effect_report, a declared call that starts a fiber whose own declared call suspends, then runs built-ins while that call waits, records each built-in against the call that ran it
--FILE--
<?php
require __DIR__ . '/project.inc';

$library = 'vendor/heyjordanparker/php-sharp-composer';
$files = [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n",
    "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
    'vendor/acme/jobs/src/Step.php' => <<<'PHP'
        <?php

        namespace Acme\Jobs;

        class Step
        {
            public function run(): void
            {
                strtoupper('before');
                \Fiber::suspend();
                strrev('after');
            }
        }
        PHP,
    'vendor/acme/jobs/src/Job.php' => <<<'PHP'
        <?php

        namespace Acme\Jobs;

        class Job
        {
            public function run(): void
            {
                $fiber = new \Fiber(fn () => (new Step())->run());
                $fiber->start();
                str_repeat('while the step waits', 2);
                $fiber->resume();
                ucfirst('after the step');
            }
        }
        PHP,
    'app/Stubs/Jobs.sharp' => <<<'SHARP'
        namespace App.Stubs;

        import Acme.Jobs.Job;
        import Acme.Jobs.Step;

        extern Job.run uses any;
        extern Step.run uses any;
        SHARP,
    'run.php' => <<<'PHP'
        <?php

        require __DIR__ . '/vendor/acme/jobs/src/Step.php';
        require __DIR__ . '/vendor/acme/jobs/src/Job.php';

        (new Acme\Jobs\Job())->run();
        PHP,
];
foreach (LIBRARY_MODULE as $class) {
    $files["$library/library/Sharp/$class.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp");
}
$root = compile_project('effect-report-fibers', $files);

$report = "$root/effects.json";
echo shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d ' . escapeshellarg("sharp.effect_report=$report") . ' ' . escapeshellarg("$root/run.php"));
echo json_encode(json_decode(file_get_contents($report)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
remove_project($root);
?>
--EXPECT--
{
    "Acme\\Jobs\\Job::run": {
        "calls": 1,
        "ran": {
            "Fiber::__construct": 1,
            "Fiber::start": 1,
            "str_repeat": 1,
            "Fiber::resume": 1,
            "ucfirst": 1
        }
    },
    "Acme\\Jobs\\Step::run": {
        "calls": 1,
        "ran": {
            "strtoupper": 1,
            "Fiber::suspend": 1,
            "strrev": 1
        }
    }
}
