--TEST--
Data in and out, spec section 10: Json.encode writes a struct by its keys, a value as text by its toString() and a family's variant with its name under the tag's key, leaving out a null field marked omitNull, and Json.decode<T> and Query.decode<T> read it back through the one reader: a list of structs, a tagged data enum, a tagged abstract class and a sealed interface by their names, a union of two structs in the order the field writes them, and a query's number from its text, each bad field one InvalidFields problem under its path, a long text cut at 40 bytes. typeof(Self).withTag builds a family's variant by its name, or gives null
--FILE--
<?php
require __DIR__ . '/attempt.inc';
foreach (['InvalidFields', 'Decimal', 'RoundingMode', 'Math', 'Int', 'Time/Instant', 'Time/Duration', 'Time/Date', 'Time/DateTime', 'Time/TimeZone', 'Time/DateTimeInterfaceMembers', 'Net/Url', 'Encoding/Json', 'Encoding/JsonValue', 'Encoding/Query'] as $file) {
    require __DIR__ . "/../../../sharp/composer/library/Sharp/$file.sharp";
}
require __DIR__ . '/harness/fixture.inc';
fixture('DataInOut');

use DataInOut\Ledger;
use DataInOut\Letter;
use DataInOut\Parcel;
use DataInOut\Shipment;

echo "Payment\n";
echo Ledger::encoded('SAVE10'), "\n";
echo Ledger::encoded(null), "\n";
var_dump(Ledger::roundTrips('SAVE10'), Ledger::roundTrips(null));
var_dump(Ledger::couponOf('{"return_url":"https://shop.test/done","amount":"1.50","paidAt":"2026-10-22T18:30:00Z","method":{"type":"card","last4":"4242"}}'));
attempt(fn () => Ledger::couponOf('{"return_url":"https://shop.test/done","amount":"one","paidAt":"2026-10-22T18:30:00Z","method":{"type":"cash"}}'));

echo "List\n";
var_dump(Ledger::payments('[' . Ledger::encoded(null) . ',' . Ledger::encoded('SAVE10') . ']'));
attempt(fn () => Ledger::payments('[' . Ledger::encoded(null) . ',{"amount":"2"}]'));

echo "Families\n";
var_dump(get_class(Ledger::method('{"type":"bank","iban":"DE89"}')));
attempt(fn () => Ledger::method('{"type":"cash"}'));
attempt(fn () => Ledger::method('{"iban":"DE89"}'));
var_dump(get_class(Ledger::methodOf(['type' => 'card', 'last4' => '1111'])), Ledger::methodOf(['type' => 'cash']));
echo Ledger::paid(), "\n";
var_dump(Ledger::result(Ledger::paid()));
attempt(fn () => Ledger::result('{"status":"refunded"}'));
echo Ledger::shipment(new Letter('to Ann')), "\n";
$shipment = Ledger::shipmentOf(Ledger::shipment(new Letter('to Ann')));
var_dump(get_class($shipment), $shipment->label);
var_dump(get_class(Ledger::shipmentOf('{"kind":"parcel","label":"box 3"}')));

echo "withTag\n";
$built = Shipment::fromRow('letter', 'to Bo');
var_dump(get_class($built), $built->label);
var_dump(Shipment::knows('parcel'), Shipment::knows('crate'));
attempt(fn () => Shipment::fromRow('crate', 'none'));

echo "Query\n";
var_dump(Ledger::search('q=shoes&page=2'));
var_dump(Ledger::search('q=shoes')->page);
attempt(fn () => Ledger::search('q=shoes&page=x'));
attempt(fn () => Ledger::search('q=shoes&page=' . str_repeat('9x', 30)));

echo "Union\n";
$prize = Ledger::prize('{"first":{"code":"A"},"rest":[{"code":"B"}]}');
var_dump(get_class($prize->first), get_class($prize->rest[0]));
?>
--EXPECTF--
Payment
{"return_url":"https://shop.test/done","amount":"1.50","paidAt":"2026-10-22T18:30:00Z","coupon":"SAVE10","method":{"type":"card","last4":"4242"}}
{"return_url":"https://shop.test/done","amount":"1.50","paidAt":"2026-10-22T18:30:00Z","method":{"type":"card","last4":"4242"}}
bool(true)
bool(true)
NULL
Sharp\InvalidFields: amount: Decimal.parse: "one" is not a decimal number, such as "-12.50"; method.type: expected bank or card, got string 'cash' in Json.sharp on line %d
List
int(2)
Sharp\InvalidFields: [1].return_url: missing; [1].paidAt: missing; [1].method: missing in Json.sharp on line %d
Families
string(14) "DataInOut\Bank"
Sharp\InvalidFields: type: expected bank or card, got string 'cash' in Json.sharp on line %d
Sharp\InvalidFields: type: missing in Json.sharp on line %d
string(14) "DataInOut\Card"
NULL
{"status":"paid","transactionId":"tx_1"}
object(DataInOut\PaymentResult\Paid)#%d (1) {
  ["transactionId"]=>
  string(4) "tx_1"
}
Sharp\InvalidFields: status: expected declined or paid, got string 'refunded' in Json.sharp on line %d
{"kind":"letter","label":"to Ann"}
string(16) "DataInOut\Letter"
string(6) "to Ann"
string(16) "DataInOut\Parcel"
withTag
string(16) "DataInOut\Letter"
string(5) "to Bo"
bool(true)
bool(false)
ValueError: No shipment is of the kind crate. in Shipment.sharp on line %d
Query
object(DataInOut\Search)#%d (2) {
  ["q"]=>
  string(5) "shoes"
  ["page"]=>
  int(2)
}
int(1)
Sharp\InvalidFields: page: expected int, got string 'x' in Query.sharp on line %d
Sharp\InvalidFields: page: expected int, got string '9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x9x…' in Query.sharp on line %d
Union
string(17) "DataInOut\Voucher"
string(16) "DataInOut\Coupon"
