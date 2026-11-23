<?php
// As the .sharp/preload.php mago compile writes, spec section 27: the Composer autoloader first, which loads the
// standard library as a program's start does, then each type an anonymous class of a file it loads up front names, then
// each file. The fixture folder's own preload.php also requires files whose parents only a test harness declares.
require __DIR__ . '/.sharp/autoload.php';
trait_exists('Illuminate\Support\Traits\ReflectsClosures');
require __DIR__ . '/Lib/Words.sharp';
require __DIR__ . '/Preloaded/Registry.sharp';
