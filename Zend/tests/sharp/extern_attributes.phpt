--TEST--
An `extern` line's attributes are the plain PHP class's own at run time: PHP#'s typeof(X).attributes<T>() and plain PHP's ReflectionClass::getAttributes() read them after the class's own, though nothing loads the file that holds the line, and a class no line names keeps its own
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

        #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
        final class Group
        {
            public function __construct(public string $name) {}
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

        import Acme.Events.AsEventListener;
        import Acme.Events.Group;
        import Acme.Events.Plain;

        [Group("listeners"), Group(Plain.KIND)]
        extern AsEventListener;

        public static class Names
        {
            public const string EVENTS = "events";
        }
        SHARP,
    'app/Demo/Reader.sharp' => <<<'SHARP'
        namespace App.Demo;

        import Acme.Events.AsEventListener;
        import Acme.Events.Group;
        import Acme.Events.Plain;

        public static class Reader
        {
            public static List<string> listener() => typeof(AsEventListener).attributes<Group>().map(group => group.name);

            public static List<string> plain() => typeof(Plain).attributes<Group>().map(group => group.name);
        }
        SHARP,
];
// typeof(X).attributes<T>() is a member of a class value, which the library declares with the types it gives.
foreach (['ClassMembers', 'Visibility', 'Property', 'Method', 'Parameter', 'ListMembers', 'Int'] as $class) {
    $files["$library/library/Sharp/$class.sharp"] = file_get_contents(__DIR__ . "/../../../sharp/composer/library/Sharp/$class.sharp");
}
$root = compile_project('extern-attributes', $files);

foreach (['Group', 'AsEventListener', 'Plain'] as $class) {
    require "$root/vendor/acme/events/$class.php";
}
require "$root/$library/library/Sharp/ClassMembers.sharp";
require "$root/$library/library/Sharp/ListMembers.sharp";
require "$root/app/Demo/Reader.sharp";

echo implode(', ', App\Demo\Reader::listener()), "\n";
echo implode(', ', App\Demo\Reader::plain()), "\n";
foreach ((new ReflectionClass(Acme\Events\AsEventListener::class))->getAttributes() as $attribute) {
    echo $attribute->getName(), ' ', json_encode($attribute->getArguments()), "\n";
}
echo class_exists(App\Stubs\Names::class, false) ? "Names.sharp loaded\n" : "Names.sharp not loaded\n";
remove_project($root);
?>
--EXPECT--
own, listeners, events
plain
Attribute [4]
Acme\Events\Group ["own"]
Acme\Events\Group ["listeners"]
Acme\Events\Group ["events"]
Names.sharp not loaded
