--TEST--
Class<T>.parse(value), spec section 25, builds a T from a value already read into lists, maps and scalars, by the read Json.parse<T> makes: a class holding a struct, a tagged family's variant and a machine case reads from a Map as from its JSON text, and a value that doesn't fit throws the InvalidFields Json.decode throws
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use StoredValues\Rows;

$decoded = Rows::decoded();
var_dump(get_class($decoded->status), $decoded->author->name, get_class($decoded->attachment), $decoded->attachment->url);
var_dump(Rows::encoded($decoded) === Rows::encoded(Rows::fromJson()));
foreach (['misfit', 'misfitFromJson'] as $read) {
    try {
        Rows::$read();
    } catch (Throwable $error) {
        echo get_class($error), ': ', $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(33) "StoredValues\LinkStatus\Published"
string(3) "Ada"
string(17) "StoredValues\Link"
string(14) "https://a.test"
bool(true)
LogicException: title: expected string, got int 5; author: missing; attachment: missing
LogicException: title: expected string, got int 5; author: missing; attachment: missing
