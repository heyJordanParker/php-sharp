<?php

namespace {
    function shipping_label(int $cents, string $currency = 'USD'): string
    {
        return sprintf('%s %d.%02d', $currency, intdiv($cents, 100), $cents % 100);
    }
}

namespace Demo {
    function shipping_note(int $cents): string
    {
        return "ships for {$cents} cents";
    }
}
