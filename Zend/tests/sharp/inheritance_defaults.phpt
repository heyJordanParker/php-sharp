--TEST--
A PHP# interface's default bodies, of methods and computed properties, run in a PHP# class, an enum, a struct, each case of an enum that carries data and a plain PHP class that uses its Defaults trait, and super<Told> runs one of two through a protected alias, in the class and in its subclass
--FILE--
<?php

require __DIR__ . '/Inheritance.sharp';

// A plain PHP class gets a PHP# interface's default bodies by using the trait `<Interface>\Defaults`, spec section 22.
final class LegacySheet implements Demo\HasDesign
{
    use Demo\HasDesign\Defaults;

    public string $designColumn = 'legacy';

    // A computed property's default body is the trait's method that the property runs, which has no PHP type.
    public $designTag {
        get => $this->{'designTag@Demo\HasDesign'}();
    }

    public function designTitle(): string
    {
        return 'Legacy';
    }
}

$sheet = new Demo\Sheet();
var_dump($sheet->designKey(), (new LegacySheet())->designKey(), Demo\Mood::Calm->describe());
var_dump($sheet->designTag, (new LegacySheet())->designTag, (new Demo\Poster())->designTag);
var_dump((new Demo\Memo('memo'))->describe(), Demo\Verdict\Pending::instance()->describe(), (new Demo\Verdict\Ruled('court'))->describe());
var_dump((new Demo\Both())->describe(), (new Demo\Repeater())->repeat());
$coded = new Demo\Coded();
var_dump($coded->keys(), Demo\Seeder::firstKey($coded));

var_dump(
    (new ReflectionMethod(Demo\HasDesign::class, 'designKey'))->isAbstract(),
    (new ReflectionClass(Demo\HasDesign\Defaults::class))->isTrait(),
    (new ReflectionClass(Demo\HasDesign\Defaults::class))->hasMethod('designTitle'),
);
var_dump(
    (new ReflectionClass(Demo\Sheet::class))->getTraitNames(),
    (new ReflectionClass(Demo\Both::class))->getTraitNames(),
    (new ReflectionClass(LegacySheet::class))->getTraitNames(),
);
var_dump(array_map(
    static fn (ReflectionMethod $method): string => $method->name,
    (new ReflectionClass(Demo\Both::class))->getMethods(ReflectionMethod::IS_PROTECTED),
));
?>
--EXPECT--
string(12) "design:sheet"
string(13) "design:legacy"
string(4) "told"
string(9) "tag:sheet"
string(10) "tag:legacy"
string(10) "poster tag"
string(4) "told"
string(4) "told"
string(4) "told"
string(14) "described+told"
string(5) "told!"
array(2) {
  [0]=>
  int(7)
  [1]=>
  int(7)
}
int(7)
bool(true)
bool(true)
bool(false)
array(1) {
  [0]=>
  string(23) "Demo\HasDesign\Defaults"
}
array(2) {
  [0]=>
  string(23) "Demo\Described\Defaults"
  [1]=>
  string(18) "Demo\Told\Defaults"
}
array(1) {
  [0]=>
  string(23) "Demo\HasDesign\Defaults"
}
array(2) {
  [0]=>
  string(23) "describe@Demo\Described"
  [1]=>
  string(18) "describe@Demo\Told"
}
