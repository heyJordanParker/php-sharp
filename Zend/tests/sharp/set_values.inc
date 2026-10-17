<?php

use SetValues\Badge;
use SetValues\Labels;
use SetValues\Report;
use SetValues\Tagged;

/** Binds the standard library's dispatcher, as its module does, and loads each of its classes the first time code names it, as events.inc does. */
function library(): void
{
    $library = dirname(__DIR__, 3) . '/sharp/composer/library';
    Sharp\Internal\readModule("$library/Sharp/Module.sharp");
    spl_autoload_register(static function (string $class) use ($library): void {
        $path = "$library/" . str_replace('\\', '/', $class) . '.sharp';
        if (str_starts_with($class, 'Sharp\\') && is_file($path)) {
            require $path;
        }
    });
}

/** Each row compares sets, in PHP# from its literals and from the PHP arrays plain PHP passes. */
function rows(): array
{
    return [
        '[1, 2] and [2, 1] as Set<int>' => fn () => Report::inAnyOrder(),
        'from plain PHP' => fn () => Report::same([1, 2], [2, 1]),
        'sets of different sizes' => fn () => Report::sizesDiffer(),
        'sets of different elements' => fn () => Report::elementsDiffer(),
        'a nullable set' => fn () => [Report::lifted(null, null), Report::lifted([1], null), Report::lifted(null, [1]), Report::lifted([1, 2], [2, 1])],
        'a List<Set<int>>' => fn () => [Report::rowsInAnyOrder(), Report::rows([[1, 2], [3]], [[3], [1, 2]])],
        'a Map<string, Set<int>>' => fn () => [Report::named(['a' => [1, 2]], ['a' => [2, 1]]), Report::named(['a' => [1]], ['a' => [2]])],
        'a Set<Set<int>>' => fn () => Report::groups([[1, 2], [3]], [[3], [2, 1]]),
        'contains on a List<Set<int>>' => fn () => [Report::holdsInAnyOrder(), Report::holds([[1, 2]], [1])],
        'indexOf on a List<Set<int>>' => fn () => [Report::at([[3], [1, 2]], [2, 1]), Report::at([[3]], [1])],
        'remove on a List<Set<int>>' => fn () => Report::left([[3], [1, 2], [2, 1]], [2, 1]),
        'equal sets hash alike in any order' => fn () => Report::distinct([[1, 2], [2, 1], [3]]),
        'a struct with a Set field' => fn () => [
            Report::labels(new Labels(['a', 'b']), new Labels(['b', 'a'])),
            Report::labels(new Labels(['a']), new Labels(['a', 'b'])),
            Report::distinctLabels([new Labels(['a', 'b']), new Labels(['b', 'a'])]),
        ],
        'an event with a Set parameter, emitted and heard' => fn () => Report::emitted(),
        'an event built in plain PHP' => function () {
            $tagged = new Tagged(['vip', 'new'], 8);
            return [Report::described($tagged), $tagged->tags::class];
        },
        'a case with a Set field, built and matched' => function () {
            $marked = new Badge\Marked(['new', 'vip']);
            return [Report::marked(), Report::matched($marked), $marked->tags::class, Report::sameBadges()];
        },
        'a case with a Map field, built in plain PHP' => function () {
            $counted = new Badge\Counted(['vip' => 3]);
            return [Report::matched($counted), $counted->counts::class];
        },
    ];
}

/** Each row's name and what it gave, or the exception it threw. Every row runs twice, so the JIT variants compile it before the last run, whose lines are printed. */
function run_rows(): void
{
    for ($run = 0; $run < 2; $run++) {
        ob_start();
        foreach (rows() as $name => $row) {
            try {
                $given = json_encode($row());
            } catch (Throwable $error) {
                $given = $error::class . ': ' . $error->getMessage();
            }
            echo $name, ': ', $given, "\n";
        }
        $printed = ob_get_clean();
    }
    echo $printed;
}
