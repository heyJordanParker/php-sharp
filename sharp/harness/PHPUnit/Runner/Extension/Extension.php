<?php

namespace PHPUnit\Runner\Extension;

use PHPUnit\TextUI\Configuration\Configuration;

// PHPUnit's extension, which its runner creates with no argument and bootstraps once, before the first test.
interface Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void;
}
