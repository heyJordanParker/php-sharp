<?php

namespace Acme\Pdf {
    const DEFAULT_DPI = 300;

    function render(string $html): string
    {
        return "<pdf>{$html}</pdf>";
    }
}

namespace Acme\Clock {
    final class SystemClock
    {
        public static function label(): string
        {
            return 'system clock';
        }
    }

    final class Events
    {
        public static function count(): int
        {
            return 2;
        }
    }
}
