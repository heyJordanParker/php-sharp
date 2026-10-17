<?php

require __DIR__ . '/PreloadedLambdas/Shapes.sharp';
require __DIR__ . '/PreloadedLambdas/StoredStep.sharp';

function lambda_shapes(): string
{
    $shapes = new PreloadedLambdas\Shapes();

    return implode(' ', [
        $shapes->capturesNothing(),
        $shapes->capturesLocal(),
        $shapes->usesThis(),
        PreloadedLambdas\Shapes::inStaticMethod(),
        $shapes->passedToPlainPhp(),
        (new PreloadedLambdas\StoredStep())->run(),
    ]);
}

lambda_shapes();
