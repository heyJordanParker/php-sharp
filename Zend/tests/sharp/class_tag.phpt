--TEST--
Class<T>.tag(), spec section 25, gives the name a family stores for a variant, the inverse of withTag: a backed machine's case by its value, a number as its text, a [Tag] family's variant by its [Key], and null for a class that is no variant, so typeof(F).withTag of c's tag is c for every variant c
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use VariantTags\Tags;

var_dump(Tags::completed());
var_dump(Tags::completedByTag());
var_dump(Tags::card());
var_dump(Tags::machine());
var_dump(Tags::casesRoundTrip());
var_dump(Tags::low());
var_dump(Tags::numberedCasesRoundTrip());
var_dump(Tags::variantsRoundTrip());
var_dump(Tags::voucherCode());
try {
    Tags::unknown();
} catch (LogicException $error) {
    echo get_class($error), ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
string(9) "completed"
bool(true)
string(4) "card"
NULL
bool(true)
string(1) "1"
bool(true)
bool(true)
string(3) "V-7"
LogicException: type: expected 1, bank or card, got string 'cash'
