--TEST--
A generic method that handles an error raised inside a generic call gets the bounds of its type parameters, never the type arguments of the call that raised it
--FILE--
<?php
// PHP# compiled `Converter.convert<Order>(order)` against the generic Legacy.Converter, but here the name reaches a
// plain class whose method is deprecated, so the call raises the deprecation while its type arguments wait for it.
class PlainConverter
{
    #[\Deprecated]
    public static function convert(object $value): object
    {
        return $value;
    }
}
class_alias(PlainConverter::class, 'Legacy\Converter');
require __DIR__ . '/type_arguments_calls.inc';

Lib\Witness::$look = function (object $box): void {
    echo shown($box), "\n";
};
set_error_handler([Calls\Repository::class, 'record']);
echo get_class(Calls\Repository::converted(new App\Order(1))), "\n";
?>
--EXPECT--
App\Box<Any?>
App\Order
