--TEST--
PHP# runs an extension member on the parts of a union receiver that declare no member of its name: label() on an Order|string runs Order's own label() for an Order and the extension for a string, as the caption property and label read as a method's value do, tag() on a Note|string calls the function Note's tag property holds for a Note, badge(), which Order lacks, runs the extension for both, and a value shown as a string or an Order runs the extension or the method alone
--FILE--
<?php
require __DIR__ . '/UnionLabels/Order.sharp';
require __DIR__ . '/UnionLabels/Ticket.sharp';
require __DIR__ . '/UnionLabels/Labeled.sharp';
require __DIR__ . '/UnionLabels/Card.sharp';
require __DIR__ . '/harness/UnionLabels.inc';
require __DIR__ . '/UnionLabels/Note.sharp';
require __DIR__ . '/UnionLabels/Labels.sharp';
require __DIR__ . '/UnionLabels/Shelf.sharp';

use Demo\Labels\Card;
use Demo\Labels\Note;
use Demo\Labels\Order;
use Demo\Labels\PhpOrder;
use Demo\Labels\PlainOrder;
use Demo\Labels\Ticket;
use Demo\Labels\Shelf;

// Each run repeats, so the JIT variants compile the calls before the second run.
for ($run = 0; $run < 2; $run++) {
    echo Shelf::label(new Order()), ' | ', Shelf::label('plain'), "\n";
    echo Shelf::badge(new Order()), ' | ', Shelf::badge('plain'), "\n";
    echo Shelf::text('plain'), ' | ', Shelf::order(new Order()), "\n";
    echo Shelf::caption(new Order()), ' | ', Shelf::caption('plain'), "\n";
    echo Shelf::labeled(new Order()), ' | ', Shelf::labeled('plain'), "\n";
    echo Shelf::tag(new Note()), ' | ', Shelf::tag('plain'), "\n";
    echo Shelf::three(new Ticket()), ' | ', Shelf::three(new Order()), ' | ', Shelf::three('plain'), "\n";
    echo Shelf::maybe(new Order()), ' | ', Shelf::maybe('plain'), ' | ', Shelf::maybe(null) ?? 'null', "\n";
    echo Shelf::maybeCaption(new Order()), ' | ', Shelf::maybeCaption('plain'), ' | ', Shelf::maybeCaption(null) ?? 'null', "\n";
    echo Shelf::named(new Card()), ' | ', Shelf::named('plain'), "\n";
    echo Shelf::plain(new PlainOrder()), ' | ', Shelf::plain('plain'), "\n";
    echo Shelf::traited(new PhpOrder()), ' | ', Shelf::traited('plain'), "\n";
    echo Shelf::boundedOrder(new Order()), ' | ', Shelf::boundedText('plain'), "\n";
    echo Shelf::picked(true), ' | ', Shelf::picked(false), ' | ', Shelf::pickCount(), "\n";
}
?>
--EXPECT--
order | text plain
badge order | badge plain
text plain | order
order caption | caption plain
order | text plain
note tag | tag plain
ticket | order | text plain
order | text plain | null
order caption | caption plain | null
card | text plain
plain order | text plain
trait | text plain
order | text plain
order | text picked | 2
order | text plain
badge order | badge plain
text plain | order
order caption | caption plain
order | text plain
note tag | tag plain
ticket | order | text plain
order | text plain | null
order caption | caption plain | null
card | text plain
plain order | text plain
trait | text plain
order | text plain
order | text picked | 4
