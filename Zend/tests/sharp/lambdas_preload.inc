<?php

require __DIR__ . '/harness/Callbacks.inc';
require __DIR__ . '/PreloadedLambdas/Labeled.sharp';
require __DIR__ . '/PreloadedLambdas/Shapes.sharp';
require __DIR__ . '/PreloadedLambdas/StoredStep.sharp';

function lambda_shapes(): string
{
    $shapes = new PreloadedLambdas\Shapes();
    $stored = new PreloadedLambdas\StoredStep();

    return implode(' ', [
        $shapes->capturesNothing(),
        $shapes->capturesLocal(),
        $shapes->usesThis(),
        PreloadedLambdas\Shapes::inStaticMethod(),
        $shapes->passedToPlainPhp(),
        $stored->run(),
        $shapes->label(),
        $stored->label(),
    ]);
}

lambda_shapes();
