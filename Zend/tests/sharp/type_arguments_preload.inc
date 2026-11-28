<?php

class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias/Twin.sharp';
require __DIR__ . '/TypeArgumentsAlias/Maker.sharp';
class_alias(Aliased\Twin::class, 'App\Node');
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Error.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Result.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/ParseError.sharp';
require __DIR__ . '/Demo/Stamp.sharp';
require __DIR__ . '/harness/Probe.inc';
require __DIR__ . '/Structs/Pair.sharp';
require __DIR__ . '/Structs/Wrapped.sharp';
require __DIR__ . '/Structs/Structs.sharp';
