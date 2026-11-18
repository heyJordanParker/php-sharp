<?php declare(strict_types=1);

// The plain PHP side of the Constructors fixture: the attribute on `Artifact.fromJson`, and plain PHP subclasses of
// two PHP# classes.

namespace Demo;

require_once __DIR__ . '/harness/Attributes.inc';
require __DIR__ . '/vendor/autoload.php';

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
