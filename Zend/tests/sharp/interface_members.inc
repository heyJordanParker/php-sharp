<?php

use InterfaceMembers\Book;
use InterfaceMembers\Priced;
use InterfaceMembers\Rare;
use InterfaceMembers\Report;
use InterfaceMembers\Weight;

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

/** A plain PHP class gets a PHP# interface's default bodies and operator by using the trait `<Interface>\Defaults`, spec section 22. */
final class LegacyBook implements Priced
{
    use Priced\Defaults;

    public function __construct(public int $cents)
    {
    }

    // A computed property's default body is the trait's method that the property runs, and the class writes its type.
    public string $label {
        get => $this->{'label@InterfaceMembers\Priced'}();
    }
}

/** Each row reads an interface's constant, default body or operator, from PHP# and from plain PHP. */
function rows(): array
{
    return [
        'Priced.CURRENCY and Book.CURRENCY' => fn () => Report::currencies(),
        'an interface constant takes every constant-value form' => fn () => Report::constantForms(),
        'a default body and an override' => fn () => Report::labels(),
        'two Priced values' => fn () => Report::priced(new Book(1), new Book(2)),
        'two Rares as Priced, by the interface' => fn () => Report::priced(new Rare(1), new Rare(2)),
        'two Books, by the interface' => fn () => Report::books(),
        'two Rares, by their own' => fn () => Report::rares(),
        'a plain PHP class as Priced' => fn () => Report::priced(new LegacyBook(5), new Book(2)),
        'sortedBy agrees with <: Priced by the interface, Rare by its own' => fn () => [
            Report::sorted([new Book(3), new LegacyBook(1), new Book(2)]),
            Report::sorted([new Rare(1), new Rare(3), new Rare(2)]),
            Report::rareSorted([new Rare(1), new Rare(3), new Rare(2)]),
        ],
        'a Rare and a Book, in either order, by the interface' => fn () => Report::either(new Rare(1), new Book(2), new Book(2)),
        'two Gems by the sub-interface, and as Priced' => fn () => [
            Report::gems(new InterfaceMembers\Gem(1), new InterfaceMembers\Gem(2)),
            Report::priced(new InterfaceMembers\Gem(1), new InterfaceMembers\Gem(2)),
        ],
        'an enum gets the interface operator' => fn () => Report::cash(),
        'sortedBy on a list of Books and Rares' => fn () => Report::mixedSorted(),
        'a Set and a Map of Measure key by its ==' => fn () => Report::keyed(new Weight(1000), new InterfaceMembers\Kilo(1)),
        'enum cases' => fn () => Report::sizes(),
        'an enum compares its cases by its own ==' => fn () => Report::coins(),
        'an interface ==, + and unary -' => fn () => Report::measures(new Weight(2), new Weight(5), null),
        'plain PHP reads the constant and the default body' => fn () => [Priced::CURRENCY, (new LegacyBook(250))->label],
        'the trait holds the operator once' => fn () => array_map(
            static fn (ReflectionMethod $method): string => $method->name,
            (new ReflectionClass(Priced\Defaults::class))->getMethods(ReflectionMethod::IS_STATIC),
        ),
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
