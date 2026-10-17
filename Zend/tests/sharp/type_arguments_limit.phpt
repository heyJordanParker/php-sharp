--TEST--
An app holds at most 65,536 run-time types read from input: past the limit a new type text from unserialize fails as other bad input does, while a new type the program's own substitution makes and every type the app holds keep working, and the next request starts empty
--SKIPIF--
<?php
if (getenv('SKIP_SLOW_TESTS')) die('skip slow test');
?>
--FILE--
<?php
require __DIR__ . '/type_arguments.inc';

if (($argv[1] ?? null) === 'request') {
    $template = serialize(App\Queue::pair());
    $read = static fn (string $text): mixed
        => unserialize(str_replace('s:14:"App.Order, int"', 's:' . strlen($text) . ':"' . $text . '"', $template));
    // Each text spells a Function type no other text spells, with the parameters of a number's bits.
    $text = static function (int $number): string {
        $parameters = [];
        for ($bit = 0; $bit < 17; $bit++) {
            $parameters[] = ($number >> $bit) & 1 ? 'string' : 'float?';
        }

        return 'App.Order|Function<void(' . implode(', ', $parameters) . ')>, int';
    };

    $read($text(0));
    App\Queue::intNode()->deeper();
    for ($number = 1; $number < 65_536 && @$read($text($number)) !== false; $number++) {
    }
    echo $number < 65_536 ? "a new text fails before 65,536 texts\n" : "every text was read\n";
    echo get_class($read($text(0))), " still reads a text the app holds\n";
    var_dump($read($text(70_000)));
    try {
        echo get_class(App\Queue::stringNode()->deeper()), " still makes a new type the program spells\n";
    } catch (Error $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
    echo get_class(App\Queue::intNode()->deeper()), " still makes a type the app holds\n";

    return;
}

// The CLI's --repeat runs the requests in one process, and its lines are renamed, since run-tests.php removes them from
// a test's output when it runs a test twice itself.
echo strtr(shell_exec(escapeshellarg(PHP_BINARY) . ' -n --repeat 2 ' . escapeshellarg(__FILE__) . ' request'), [
    "Executing for the first time...\n" => "the first request:\n",
    "Finished execution, repeating...\n" => "the next request, in the same process:\n",
]);
?>
--EXPECTF--
the first request:
a new text fails before 65,536 texts
App\Pair still reads a text the app holds

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
App\Node still makes a new type the program spells
App\Node still makes a type the app holds
the next request, in the same process:
a new text fails before 65,536 texts
App\Pair still reads a text the app holds

Warning: unserialize(): Error at offset %d of %d bytes in %s on line %d
bool(false)
App\Node still makes a new type the program spells
App\Node still makes a type the app holds
