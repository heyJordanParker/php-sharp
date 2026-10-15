--TEST--
emit creates each listener's object through the container for every event, with services from its namespace's modules, whatever lifetime a module gives the listener's class
--FILE--
<?php
require __DIR__ . '/Container.inc';

container_project('events-container', [
    'app/Shop/Paid.sharp' => <<<'SHARP'
        namespace App.Shop;

        public event Paid(int orderId);
        SHARP,
    'app/Shop/Notices.sharp' => <<<'SHARP'
        namespace App.Shop;

        import App.Mail.Mailer;

        public class Notices
        {
            public static List<Notices> made { get; set; } = [];

            public Notices(private Mailer mailer, private Stamp stamp) { }

            public Mailer usedMailer() => this.mailer;

            public Stamp usedStamp() => this.stamp;

            public void hear(Paid e) on Paid
            {
                Notices.made.add(this);
            }
        }
        SHARP,
    'app/Shop/Bound/Module.sharp' => <<<'SHARP'
        namespace App.Shop.Bound;

        module
        {
            singleton Ledger = Ledger;
        }
        SHARP,
    'app/Shop/Bound/Ledger.sharp' => <<<'SHARP'
        namespace App.Shop.Bound;

        import App.Shop.Paid;

        public class Ledger
        {
            public static List<Ledger> made { get; set; } = [];

            public void record() on Paid
            {
                Ledger.made.add(this);
            }
        }
        SHARP,
    'app/Shop/Fragile.sharp' => <<<'SHARP'
        namespace App.Shop;

        import RuntimeException;

        public class Fragile
        {
            public static bool broken { get; set; } = false;

            public Fragile()
            {
                if (Fragile.broken) {
                    throw new RuntimeException("Fragile could not start");
                }
            }

            public void hear() on Paid { }
        }
        SHARP,
]);

Sharp\Internal\emit(new App\Shop\Paid(1));
Sharp\Internal\emit(new App\Shop\Paid(2));
[$first, $second] = App\Shop\Notices::$made;
echo 'a new object for every event: ', var_export($first !== $second, true), "\n";
echo 'its singleton service: ', $first->usedMailer()->name(), ', the same object: ', var_export($first->usedMailer() === $second->usedMailer(), true), "\n";
echo 'its transient service, new each time: ', var_export($first->usedStamp() !== $second->usedStamp(), true), "\n";
[$ledger, $next] = App\Shop\Bound\Ledger::$made;
echo 'a class a module binds as a singleton, still new for every event: ', var_export($ledger !== $next, true), "\n";

Sharp\Internal\testModule(['Tests\NoticesTest'], [['app\shop', 'app\mail\mailer', null, 'singleton', 'App\Mail\QueueMailer']]);
Sharp\Internal\startTest('Tests\NoticesTest');
Sharp\Internal\emit(new App\Shop\Paid(3));
Sharp\Internal\endTest();
echo 'while a test runs, its module\'s service: ', end(App\Shop\Notices::$made)->usedMailer()->name(), "\n";

App\Shop\Fragile::$broken = true;
try {
    Sharp\Internal\emit(new App\Shop\Paid(4));
} catch (RuntimeException $exception) {
    echo get_class($exception), ' leaves emit: ', $exception->getMessage(), "\n";
}
?>
--EXPECT--
a new object for every event: true
its singleton service: smtp, the same object: true
its transient service, new each time: true
a class a module binds as a singleton, still new for every event: true
while a test runs, its module's service: queue
RuntimeException leaves emit: Fragile could not start
