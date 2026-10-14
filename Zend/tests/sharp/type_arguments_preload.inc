<?php

class PlainPair
{
}
class_alias(PlainPair::class, 'App\Pair');
require __DIR__ . '/TypeArgumentsAlias.sharp';
class_alias(Aliased\Twin::class, 'App\Node');
