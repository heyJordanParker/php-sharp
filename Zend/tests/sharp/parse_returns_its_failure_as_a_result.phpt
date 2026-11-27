--TEST--
A parse returns its failure as a Result: a struct's parse gives an Err whose ParseError lists every bad field, Int.parse gives an Ok or an Err, ok() gives null for an Err and unwrap() throws LogicException with the problem
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

$parsing = new Demo\Parsing();
echo $parsing->request(['customerId' => 'abc']), "\n";
echo $parsing->request(['customerId' => 7, 'plan_code' => 'pro']), "\n";
echo $parsing->number('42'), "\n";
echo $parsing->number('x'), "\n";
var_dump($parsing->maybe('x'));
try {
    $parsing->unwrapped('x');
} catch (LogicException $error) {
    echo $error::class, ': ', $error->getMessage(), "\n";
}
?>
--EXPECT--
Err: customerId: expected int, got string 'abc'; plan_code: missing
Ok: 7 pro
Ok: 42
Err: Int.parse: "x" is not an int
NULL
LogicException: Int.parse: "x" is not an int
