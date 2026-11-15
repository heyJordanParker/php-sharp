--TEST--
An exception a listener throws leaves the emit that ran it, through the standard library's dispatcher, and the listeners after it never run, as a C# event's and Symfony's dispatcher's do, while the next emit runs every listener again
--FILE--
<?php
require __DIR__ . '/Container.inc';

$root = container_project('events-listener-throws', [
    'app/Thrown/Paid.sharp' => "namespace App.Thrown;\n\npublic event Paid(int orderId);\n",
    'app/Thrown/Hears.sharp' => <<<'SHARP'
        namespace App.Thrown;

        import RuntimeException;

        public class Hears
        {
            public static List<string> log { get; set; } = [];
            public static bool failing { get; set; } = true;

            public void first(Paid e) on Paid
            {
                Hears.log.add(`first ${e.orderId}`);
                if (Hears.failing) {
                    throw new RuntimeException(`first failed on ${e.orderId}`);
                }
            }

            public void second(Paid e) on Paid
            {
                Hears.log.add(`second ${e.orderId}`);
            }
        }
        SHARP,
    'app/Thrown/Payer.sharp' => <<<'SHARP'
        namespace App.Thrown;

        import RuntimeException;

        public class Payer
        {
            public static List<string> after { get; set; } = [];

            public void pay(int orderId)
            {
                try {
                    emit new Paid(orderId);
                    Payer.after.add("emit returned");
                } catch (RuntimeException failure) {
                    Payer.after.add(`caught: ${failure.getMessage()}`);
                }
            }
        }
        SHARP,
]);

(new App\Thrown\Payer())->pay(1);
echo implode(', ', App\Thrown\Hears::$log), '; ', implode(', ', App\Thrown\Payer::$after), "\n";

App\Thrown\Hears::$log = [];
App\Thrown\Payer::$after = [];
App\Thrown\Hears::$failing = false;
(new App\Thrown\Payer())->pay(2);
echo implode(', ', App\Thrown\Hears::$log), '; ', implode(', ', App\Thrown\Payer::$after), "\n";

App\Thrown\Hears::$log = [];
App\Thrown\Hears::$failing = true;
try {
    Sharp\Internal\emit(new App\Thrown\Paid(3), 'Sharp\Dispatcher');
} catch (RuntimeException $failure) {
    echo 'plain PHP caught: ', $failure->getMessage(), '; ', implode(', ', App\Thrown\Hears::$log), "\n";
}
remove_project($root);
?>
--EXPECT--
first 1; caught: first failed on 1
first 2, second 2; emit returned
plain PHP caught: first failed on 3; first 3
