--TEST--
Each PHP# call of a PHP# interface's member checks its result, so mago compile and the engine link a plain PHP class whose method, its own or a package's, declares no return type under a typed member, and the call refuses a result of another type, naming the member as PHP# writes it, while a method that declares another type is refused as before, and a call of a PHP# class's own method checks nothing
--FILE--
<?php
require __DIR__ . '/project.inc';

$root = compile_project('interface-member-checked-at-call', [
    'Relation.sharp' => <<<'SHARP'
namespace Demo;

public class Relation
{
    public string name { get; }

    public Relation(string name)
    {
        this.name = name;
    }
}

SHARP,
    'Taggable.sharp' => <<<'SHARP'
namespace Demo;

public interface Taggable
{
    Relation morphToMany(string related, string name);
}

SHARP,
    'Post.sharp' => <<<'SHARP'
namespace Demo;

public class Post : Taggable
{
    public Post() { }

    public Relation morphToMany(string related, string name) => new Relation("post");
}

SHARP,
    'Tags.sharp' => <<<'SHARP'
namespace Demo;

public static class Tags
{
    public static string of(Taggable owner)
    {
        Relation tags = owner.morphToMany("Tag", "taggable");
        return tags.name;
    }

    public static string ofPost(Post post) => post.morphToMany("Tag", "taggable").name;
}

SHARP,
]);
file_put_contents("$root/Model.php", <<<'PHP'
<?php
namespace Acme;

abstract class Model
{
    public bool $wrong = false;

    public function morphToMany($related, $name)
    {
        return $this->wrong ? 'not a relation' : new \Demo\Relation($name);
    }
}

abstract class TextModel
{
    public function morphToMany($related, $name): string
    {
        return 'not a relation';
    }
}
PHP);
file_put_contents("$root/Contact.php", "<?php\nnamespace Demo;\nclass Contact extends \\Acme\\Model implements Taggable\n{\n}\n");
file_put_contents("$root/mago.toml", "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n");
run_mago($root);
echo "mago compile links Contact\n";

$load = 'foreach (["Relation.sharp", "Taggable.sharp", "Post.sharp", "Tags.sharp", "Model.php", "Contact.php"] as $file) require '
    . var_export("$root/", true) . ' . $file;';
$run = static fn (string $code): string
    => shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE')) . ' -n -d display_errors=1 -r ' . escapeshellarg($load . $code) . ' 2>&1');
echo $run('echo Demo\Tags::of(new Demo\Contact()), "\n";');
echo $run('$contact = new Demo\Contact(); $contact->wrong = true;'
    . ' try { Demo\Tags::of($contact); } catch (TypeError $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }');
echo $run('echo Demo\Tags::of(new Demo\Post()), "\n", Demo\Tags::ofPost(new Demo\Post()), "\n";');

file_put_contents("$root/Contact.php", "<?php\nnamespace Demo;\nclass Contact extends \\Acme\\TextModel implements Taggable\n{\n}\n");
exec('MAGO_REPORTING_FORMAT=emacs ' . escapeshellarg(getenv('TEST_MAGO_EXECUTABLE')) . ' --no-version-check --colors never --workspace ' . escapeshellarg($root) . ' compile 2>&1', $output, $status);
echo "mago compile exits $status\n";
foreach ($output as $line) {
    if (preg_match('/^\w+\.php:\d+:\d+:error - ([\w-]+): (.*)$/', $line, $match)) {
        echo "$match[1]: $match[2]\n";
    }
}
echo preg_replace('/ in \S+:\d+$| in \S+ on line \d+$/m', '', trim(strtok($run('new Demo\Contact();'), "\n"))), "\n";

// The call through the interface pays one check, and the call of Post's own method none.
$dump = shell_exec(escapeshellarg(getenv('TEST_PHP_EXECUTABLE'))
    . ' -n -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x10000 -l ' . escapeshellarg("$root/Tags.sharp") . ' 2>&1');
foreach (['of', 'ofPost'] as $method) {
    preg_match('/^Demo\\\\Tags::' . $method . ':\n((?:[ \d].*\n)*)/m', $dump, $listing);
    echo "Tags::$method\n";
    foreach (explode("\n", $listing[1]) as $line) {
        if (preg_match('/(DO_FCALL|SHARP_VERIFY_TYPE_ARGS.*)$/', $line, $op)) {
            echo "  $op[1]\n";
        }
    }
}

remove_project($root);
?>
--EXPECT--
mago compile links Contact
taggable
TypeError: Demo.Taggable.morphToMany(): Return value must be of type Demo.Relation, string returned
post
post
mago compile exits 1
incompatible-return-type: Return type `string` of `Demo\Contact::morphToMany()` is incompatible with parent return type `Demo\Relation` of `Demo\Taggable::morphToMany()`
Fatal error: Declaration of Acme\TextModel::morphToMany($related, $name): string must be compatible with Demo\Taggable::morphToMany(string $related, string $name): Demo\Relation
Tags::of
  DO_FCALL
  SHARP_VERIFY_TYPE_ARGS V2 string("Demo.Relation")
Tags::ofPost
  DO_FCALL
