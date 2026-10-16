<?php

class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias.sharp';
class_alias(Aliased\Twin::class, 'App\Node');
require __DIR__ . '/../../../sharp/composer/library/Sharp/InvalidFields.sharp';
require __DIR__ . '/Stamp.sharp';
require __DIR__ . '/Structs.sharp';
