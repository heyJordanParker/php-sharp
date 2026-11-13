--TEST--
A plain PHP closure answers `is Function<…>` by its declared PHP signature, spec section 21: an undeclared parameter type takes anything, a declared result fits a wider one, an undeclared result is `Any?`, and no effects, as the checker gives a PHP signature none
--FILE--
<?php
require __DIR__ . '/harness/fixture.inc';
fixture('PlainClosures');

use App\PlainClosures\Holder;
use App\PlainClosures\Item;

Holder::setLoader(static fn (): ?Item => new Item());
Holder::setPureLoader(static fn (): ?Item => new Item());
echo 'stored for uses Database: ', Holder::loaderMatches(), "\n";
echo 'stored for uses none: ', Holder::pureLoaderMatches(), "\n";
echo '?Item: ', Holder::testAny(static fn (): ?Item => new Item()), "\n";
echo 'Item: ', Holder::testAny(static fn (): Item => new Item()), "\n";
echo 'no result type: ', Holder::testAny(static fn () => new Item()), "\n";
echo 'no result type as Any?: ', Holder::testAnyResult(static fn () => new Item()), "\n";
echo 'string: ', Holder::testAny(static fn (): string => 'item'), "\n";
echo 'PHP# lambda: ', Holder::testAny(Holder::lambda()), "\n";
echo 'no parameter type: ', Holder::testTakesInt(static fn ($id): ?Item => new Item()), "\n";
echo 'int|string parameter: ', Holder::testTakesInt(static fn (int|string $id): ?Item => new Item()), "\n";
echo 'string parameter: ', Holder::testTakesInt(static fn (string $id): ?Item => new Item()), "\n";
echo 'a required parameter more: ', Holder::testAny(static fn (int $id): ?Item => new Item()), "\n";
echo 'an optional parameter more: ', Holder::testAny(static fn (int $id = 0): ?Item => new Item()), "\n";
echo 'strlen(...): ', Holder::testLength(strlen(...)), "\n";
?>
--EXPECT--
stored for uses Database: matched
stored for uses none: matched
?Item: matched
Item: matched
no result type: not matched
no result type as Any?: matched
string: not matched
PHP# lambda: matched
no parameter type: matched
int|string parameter: matched
string parameter: not matched
a required parameter more: not matched
an optional parameter more: matched
strlen(...): matched
