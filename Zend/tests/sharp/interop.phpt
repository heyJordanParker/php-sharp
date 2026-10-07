--TEST--
PHP# calls a global function a library or the app declares, and a namespace function of the same name never replaces it
--FILE--
<?php

namespace Demo {
    function shipping_label(int $cents): string
    {
        return 'namespaced';
    }
}

namespace {
    require __DIR__ . '/harness/Functions.inc';
    require __DIR__ . '/Interop.sharp';

    echo Demo\Interop::shipping(1250), "\n";
    echo Demo\Interop::shippingIn("EUR", 1250), "\n";
}
?>
--EXPECT--
USD 12.50
EUR 25.00
