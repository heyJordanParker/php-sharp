--TEST--
A union of PHP# types that share one PHP type, Map|List, List<int>|List<string> and Bytes|string, loads as a parameter's, a result's, a field's, a property's and a lambda parameter's type, and each takes a value of each member
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('union-erased-once', ['app/Shelves/Shelf.sharp' => <<<'SHARP'
namespace App.Shelves;

public class Shelf
{
    private Map<string, int>|List<int> rows = [1];
    private List<int>|List<string> names = [1];
    private Bytes|string data = "a";

    public Map<string, int>|List<int> heldRows { get; set; } = [1];
    public List<int>|List<string> heldNames { get; set; } = [1];
    public Bytes|string heldData { get; set; } = "a";

    public static Map<string, int>|List<int> rowsOf(Map<string, int>|List<int> value) => value;

    public static List<int>|List<string> namesOf(List<int>|List<string> value) => value;

    public static Bytes|string dataOf(Bytes|string value) => value;

    public static string rowsText(Map<string, int>|List<int> value) => value is List<int> ? `list of ${value.count()}` : `map of ${value.count()}`;

    public static string namesText(List<int>|List<string> value) => `list of ${value.count()}`;

    public static string dataText(Bytes|string value) => value is Bytes ? `bytes of ${value.length()}` : `string ${value}`;

    public List<string> run()
    {
        const rows = (Map<string, int>|List<int> value) => Shelf.rowsText(value);
        const names = (List<int>|List<string> value) => Shelf.namesText(value);
        const data = (Bytes|string value) => Shelf.dataText(value);
        const seen = [rows(["a": 1]), rows([1, 2]), names(["x"]), names([1, 2, 3]), data("hi".toBytes()), data("hi")];

        this.rows = ["a": 1, "b": 2];
        seen.add(Shelf.rowsText(this.rows));
        this.rows = [1];
        seen.add(Shelf.rowsText(this.rows));
        this.names = ["x", "y"];
        seen.add(Shelf.namesText(this.names));
        this.data = "abc".toBytes();
        seen.add(Shelf.dataText(this.data));
        this.data = "abc";
        seen.add(Shelf.dataText(this.data));
        seen.add(Shelf.dataText(Shelf.dataOf("hey".toBytes())));

        return seen;
    }
}
SHARP]);

function describe(mixed $value): string
{
    return match (true) {
        is_array($value) => 'array ' . json_encode($value),
        is_object($value) => get_class($value),
        default => gettype($value) . ' ' . $value,
    };
}

echo "a parameter and a result, from plain PHP:\n";
foreach ([App\Shelves\Shelf::rowsOf(['a' => 1]), App\Shelves\Shelf::rowsOf([1, 2]), App\Shelves\Shelf::namesOf(['x']), App\Shelves\Shelf::namesOf([1]), App\Shelves\Shelf::dataOf('hi')] as $value) {
    echo '  ', describe($value), "\n";
}

echo "a property, from plain PHP:\n";
$shelf = new App\Shelves\Shelf();
foreach ([['heldRows', ['a' => 1]], ['heldRows', [1, 2]], ['heldNames', ['x']], ['heldNames', [1]], ['heldData', 'hi']] as [$property, $value]) {
    $shelf->$property = $value;
    echo '  ', $property, ': ', describe($shelf->$property), "\n";
}

echo "a lambda's parameter, a field, and a parameter and a result, in PHP#:\n";
foreach ($shelf->run() as $line) {
    echo '  ', $line, "\n";
}
remove_project($root);
?>
--EXPECT--
a parameter and a result, from plain PHP:
  array {"a":1}
  array [1,2]
  array ["x"]
  array [1]
  string hi
a property, from plain PHP:
  heldRows: array {"a":1}
  heldRows: array [1,2]
  heldNames: array ["x"]
  heldNames: array [1]
  heldData: string hi
a lambda's parameter, a field, and a parameter and a result, in PHP#:
  map of 1
  list of 2
  list of 1
  list of 3
  bytes of 2
  string hi
  map of 2
  list of 1
  list of 2
  bytes of 3
  string abc
  bytes of 3
