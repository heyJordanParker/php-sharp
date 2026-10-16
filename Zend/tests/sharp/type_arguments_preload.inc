<?php

class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias/Twin.sharp';
require __DIR__ . '/TypeArgumentsAlias/Maker.sharp';
class_alias(Aliased\Twin::class, 'App\Node');
require __DIR__ . '/../../../sharp/composer/library/Sharp/InvalidFields.sharp';
require __DIR__ . '/Stamp.sharp';
require __DIR__ . '/Structs/Pair.sharp';
require __DIR__ . '/Structs/Wrapped.sharp';
require __DIR__ . '/Structs/Structs.sharp';
