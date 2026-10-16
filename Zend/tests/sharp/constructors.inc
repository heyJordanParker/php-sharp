<?php declare(strict_types=1);

// The plain PHP side of the Constructors fixture: the base class `SalesReport` extends, the attribute on
// `Artifact.fromJson`, and plain PHP subclasses of two PHP# classes.

namespace Demo;

require_once __DIR__ . '/harness/Report.inc';
require_once __DIR__ . '/harness/Attributes.inc';
require_once __DIR__ . '/harness/fixture.inc';
\fixture('Constructors');

class LegacyArtifact extends Artifact
{
}

class LegacyLabel extends Label
{
    public function rename(string $text): void
    {
        $this->text = $text;
    }
}
