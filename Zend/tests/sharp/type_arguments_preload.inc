<?php

class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias/Twin.sharp';
require __DIR__ . '/TypeArgumentsAlias/Maker.sharp';
class_alias(Aliased\Twin::class, 'App\Node');
