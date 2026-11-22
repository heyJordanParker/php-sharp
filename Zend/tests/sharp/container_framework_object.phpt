--TEST--
A binding's expression gives the plain PHP class it binds from a plain PHP call, so a PHP# class that takes it gets the object the call returns, the same one within a scope and the next one in a new scope
--FILE--
<?php
require __DIR__ . '/Container.inc';

// The framework's request and the function that gives it, as Laravel's request() gives its Illuminate\Http\Request.
$root = container_project('container-framework-object', [
    'mago.toml' => "[source]\npaths = [\".\"]\nextensions = [\"sharp\", \"php\"]\n",
    'src/Framework/Request.php' => "<?php\n\nnamespace Framework;\n\nfinal class Request\n{\n}\n",
    'src/Lib/request.php' => "<?php\n\nnamespace Lib;\n\nfunction request(): \\Framework\\Request\n{\n"
        . "    \$request = new \\Framework\\Request();\n    \$GLOBALS['returned'][] = \$request;\n\n    return \$request;\n}\n",
    'app/Stubs/Lib.sharp' => "namespace App.Stubs;\n\nimport Lib;\n\nextern Lib.request uses any;\n",
    'app/Front/Module.sharp' => "namespace App.Front;\n\nimport Lib;\nimport Framework.Request;\n\nmodule\n{\n"
        . "    scoped Request = Lib.request();\n    transient Page = Page;\n}\n",
    'app/Front/Page.sharp' => "namespace App.Front;\n\nimport Framework.Request;\n\npublic class Page\n{\n"
        . "    public Page(private Request request) { }\n\n    public Request usedRequest() => this.request;\n}\n",
]);
require "$root/src/Framework/Request.php";
require "$root/src/Lib/request.php";
$container = new Sharp\Container();
$request = fn () => $container->get('App\Front\Page')->usedRequest();

[$first, $again] = Sharp\Scope::run(fn () => [$request(), $request()]);
$next = Sharp\Scope::run($request);
echo "the object the call returns: ", var_export($first === $GLOBALS['returned'][0], true), "\n";
echo "the same one within a scope: ", var_export($first === $again, true), "\n";
echo "the next one in a new scope: ", var_export($next === $GLOBALS['returned'][1] && $next !== $first, true), "\n";
echo "calls: ", count($GLOBALS['returned']), "\n";
?>
--EXPECT--
the object the call returns: true
the same one within a scope: true
the next one in a new scope: true
calls: 2
