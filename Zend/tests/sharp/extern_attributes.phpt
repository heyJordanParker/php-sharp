--TEST--
An `extern` line's attributes are the plain PHP class's, method's or function's own at run time: PHP#'s typeof(X).attributes<T>() and Method.attributes<T>(), and plain PHP's ReflectionClass, ReflectionMethod and ReflectionFunction getAttributes(), read them after its own, though nothing loads the file that holds the line, and a declaration no line names keeps its own
--FILE--
<?php
require __DIR__ . '/project.inc';

$library = 'vendor/heyjordanparker/php-sharp-composer';
$files = [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n",
    "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
    'vendor/acme/events/Group.php' => <<<'PHP'
        <?php

        namespace Acme\Events;

        #[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
        final class Group
        {
            public function __construct(public string $name) {}
        }
        PHP,
    'vendor/acme/events/Mailer.php' => <<<'PHP'
        <?php

        namespace Acme\Events;

        class Mailer
        {
            #[Group('own send')]
            public function send(): void {}

            public function close(): void {}
        }
        PHP,
    'vendor/acme/events/functions.php' => <<<'PHP'
        <?php

        namespace Acme\Events;

        function sentAt(): int
        {
            return 1;
        }

        function closedAt(): int
        {
            return 2;
        }
        PHP,
    'vendor/acme/events/AsEventListener.php' => <<<'PHP'
        <?php

        namespace Acme\Events;

        #[\Attribute(\Attribute::TARGET_METHOD)]
        #[Group('own')]
        final class AsEventListener
        {
        }
        PHP,
    'vendor/acme/events/Plain.php' => <<<'PHP'
        <?php

        namespace Acme\Events;

        #[Group('plain')]
        final class Plain
        {
            public const KIND = 'events';
        }
        PHP,
    'app/Stubs/Names.sharp' => <<<'SHARP'
        namespace App.Stubs;

        import Acme.Events;
        import Acme.Events.AsEventListener;
        import Acme.Events.Group;
        import Acme.Events.Mailer;
        import Acme.Events.Plain;

        [Group("listeners"), Group(Plain.KIND)]
        extern AsEventListener;

        [Group("line send")]
        extern Mailer.send;

        [Group("line sentAt")]
        extern Events.sentAt;

        public static class Names
        {
            public const string EVENTS = "events";
        }
        SHARP,
    'app/Demo/Reader.sharp' => <<<'SHARP'
        namespace App.Demo;

        import Acme.Events.AsEventListener;
        import Acme.Events.Group;
        import Acme.Events.Mailer;
        import Acme.Events.Plain;

        public static class Reader
        {
            public static List<string> listener() => typeof(AsEventListener).attributes<Group>().map(group => group.name);

            public static List<string> plain() => typeof(Plain).attributes<Group>().map(group => group.name);

            public static List<string> method(string name)
                => typeof(Mailer).methods().first(method => method.name == name).attributes<Group>().map(group => group.name);
        }
        SHARP,
];
// typeof(X).attributes<T>() is a member of a class value, which the library declares with the types it gives.
$members = ['ClassMembers', 'Visibility', 'Property', 'Method', 'Parameter', 'Constructor', 'EnumCase', 'TupleElement', 'TypeKind'];
foreach ([...$members, 'ListMembers', 'Int'] as $class) {
    $files["$library/library/Sharp/$class.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp");
}
$root = compile_project('extern-attributes', $files);

foreach (['Group', 'AsEventListener', 'Plain', 'Mailer', 'functions'] as $class) {
    require "$root/vendor/acme/events/$class.php";
}
foreach (['ClassMembers', 'ListMembers', 'Method', 'Parameter', 'Visibility'] as $class) {
    require "$root/$library/library/Sharp/$class.sharp";
}
require "$root/app/Demo/Reader.sharp";

echo implode(', ', App\Demo\Reader::listener()), "\n";
echo implode(', ', App\Demo\Reader::plain()), "\n";
echo implode(', ', App\Demo\Reader::method('send')), "\n";
echo '[', implode(', ', App\Demo\Reader::method('close')), "]\n";
$reflections = [
    new ReflectionClass(Acme\Events\AsEventListener::class),
    new ReflectionMethod(Acme\Events\Mailer::class, 'send'),
    new ReflectionFunction((new Acme\Events\Mailer())->send(...)),
    new ReflectionFunction('Acme\Events\sentAt'),
    new ReflectionFunction('Acme\Events\closedAt'),
];
foreach ($reflections as $reflection) {
    echo $reflection->getName(), ':';
    foreach ($reflection->getAttributes() as $attribute) {
        echo ' ', $attribute->getName(), ' ', json_encode($attribute->getArguments());
    }
    echo "\n";
}
echo json_encode((new ReflectionFunction('Acme\Events\sentAt'))->getAttributes()[0]->newInstance()), "\n";
echo class_exists(App\Stubs\Names::class, false) ? "Names.sharp loaded\n" : "Names.sharp not loaded\n";
remove_project($root);
?>
--EXPECT--
own, listeners, events
plain
own send, line send
[]
Acme\Events\AsEventListener: Attribute [4] Acme\Events\Group ["own"] Acme\Events\Group ["listeners"] Acme\Events\Group ["events"]
send: Acme\Events\Group ["own send"] Acme\Events\Group ["line send"]
send: Acme\Events\Group ["own send"] Acme\Events\Group ["line send"]
Acme\Events\sentAt: Acme\Events\Group ["line sentAt"]
Acme\Events\closedAt:
{"name":"line sentAt"}
Names.sharp not loaded
