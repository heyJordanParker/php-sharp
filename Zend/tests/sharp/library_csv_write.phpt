--TEST--
PHP# List<string>.toCsv writes one CSV record of RFC 4180, the write side of string.parseCsv: a field that holds a comma, a quote, a carriage return or a line feed is quoted with each quote doubled, every other field is written as it is, and parseCsv reads each record back to its fields
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\EncodingQueries;

$records = [
    ['sku', 'name', 'price'],
    ['A-1', 'Mug, large', '12.50'],
    ['B-2', 'The "best" pen', ''],
    ["C-3", "two\nlines", "carriage\rreturn"],
    [' padded ', '"', ','],
    [''],
];
foreach ($records as $fields) {
    $written = EncodingQueries::toCsv($fields);
    echo json_encode($written), ' ', EncodingQueries::parseCsv($written) === $fields ? 'reads back' : 'differs: ' . json_encode(EncodingQueries::parseCsv($written)), "\n";
}
?>
--EXPECT--
"sku,name,price" reads back
"A-1,\"Mug, large\",12.50" reads back
"B-2,\"The \"\"best\"\" pen\"," reads back
"C-3,\"two\nlines\",\"carriage\rreturn\"" reads back
" padded ,\"\"\"\",\",\"" reads back
"" reads back
