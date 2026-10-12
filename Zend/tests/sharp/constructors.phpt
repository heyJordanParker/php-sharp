--TEST--
PHP# constructors: `: super(…)`, named constructors and `init` properties, as plain PHP calls and sees them
--FILE--
<?php
require __DIR__ . '/attempt.inc';
require __DIR__ . '/Constructors.inc';

// Each round runs every form, so the JIT runs compiled code before the last round, whose output is printed.
for ($round = 0; $round < 3; $round++) {
    ob_start();
    // `: super(id)` runs the plain PHP base class's constructor with the argument.
    attempt(fn () => [(new Demo\SalesReport(1))->built, (new Demo\SalesReport(1))->id]);
    // `new Artifact.fromJson(json)` in PHP# and `Artifact::fromJson($json)` in plain PHP create the same object, and
    // the named constructor's `super.describe()` runs on it.
    attempt(function () {
        $sharp = Demo\Artifact::load(' tea ');
        $php = Demo\Artifact::fromJson(' tea ');

        return [get_class($php), $php->manifest->name, $php->seen, $sharp == $php];
    });
    // A plain PHP subclass inherits the static method, which creates the class that declares it.
    attempt(fn () => get_class(Demo\LegacyArtifact::fromJson('x')));
    attempt(fn () => get_class(new Demo\LegacyArtifact(new Demo\Manifest('y'))));
    // The guard clause, in braces, throws for an inactive tenant and passes an active one.
    attempt(function () {
        $tenant = new Demo\Tenant();
        $tenant->active = false;

        return new Demo\Gate($tenant);
    });
    attempt(fn () => (new Demo\Gate(new Demo\Tenant()))->tenant->active);
    // A named constructor sets an `init` property the main constructor already set, and its value is read back.
    attempt(fn () => Demo\Label::shouting('hi')->text);
    // Plain PHP outside the class cannot set it once the object exists.
    attempt(function () {
        $label = new Demo\Label('a');
        $label->text = 'b';

        return $label->text;
    });
    // A plain PHP subclass can, because PHP has no set visibility that ends when the object is created.
    attempt(function () {
        $label = new Demo\LegacyLabel('a');
        $label->rename('b');

        return $label->text;
    });
    $output = ob_get_clean();
}
echo $output;

// Reflection sees the main constructor as the constructor, and the named constructor as a public static method.
$class = new ReflectionClass(Demo\Artifact::class);
$constructor = $class->getConstructor();
$parameter = $constructor->getParameters()[0];
$named = $class->getMethod('fromJson');
var_dump(
    $constructor->getName(),
    $parameter->getName(),
    $parameter->isPromoted(),
    $class->getProperty('manifest')->isReadOnly(),
    $named->isPublic(),
    $named->isStatic(),
    $named->isConstructor(),
    array_map(static fn (ReflectionParameter $parameter): string => $parameter->getType() . ' $' . $parameter->getName(), $named->getParameters()),
    (string) $named->getReturnType(),
    array_map(static fn (ReflectionAttribute $attribute): string => $attribute->getName(), $named->getAttributes()),
);
?>
--EXPECT--
array(2) {
  [0]=>
  string(22) "Report::__construct(1)"
  [1]=>
  int(1)
}
array(4) {
  [0]=>
  string(13) "Demo\Artifact"
  [1]=>
  string(3) "tea"
  [2]=>
  string(10) "entity tea"
  [3]=>
  bool(true)
}
string(13) "Demo\Artifact"
string(19) "Demo\LegacyArtifact"
Demo\InactiveTenant: The tenant is inactive. in Constructors.sharp on line 55
bool(true)
string(2) "HI"
Error: Cannot modify protected(set) property Demo\Label::$text from global scope in constructors.php on line 34
string(1) "b"
string(11) "__construct"
string(8) "manifest"
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
array(1) {
  [0]=>
  string(12) "string $json"
}
string(13) "Demo\Artifact"
array(1) {
  [0]=>
  string(10) "Demo\Retry"
}
