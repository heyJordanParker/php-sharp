--TEST--
A PHP# class header links the same through opcache's inheritance cache on later requests
--EXTENSIONS--
opcache
--CONFLICTS--
server
--FILE--
<?php

include __DIR__ . '/../../../sapi/cli/tests/php_cli_server.inc';

$dir = var_export(__DIR__, true);
php_cli_server_start(<<<PHP
    require $dir . '/harness/Header.inc';
    require $dir . '/Header.sharp';
    \$article = new Demo\Article();
    \$interfaces = class_implements(\$article);
    ksort(\$interfaces);
    echo get_parent_class(\$article), ' ', implode(',', \$interfaces), ' ', \$article->number(), "\n";
    \$card = new Demo\Card();
    \$interfaces = class_implements(\$card);
    ksort(\$interfaces);
    echo get_parent_class(\$card), ' ', implode(',', \$interfaces), ' ', \$card->copy()->link(), "\n";
    require $dir . '/harness/Traits.inc';
    require $dir . '/Traits.sharp';
    require $dir . '/Inheritance.sharp';
    foreach ([new Demo\Essay(), new Demo\Guide(), new Demo\Both()] as \$object) {
        echo get_parent_class(\$object), ' ', implode(',', class_uses(\$object)), ' ',
            implode(',', (new ReflectionClass(\$object))->getTraitNames()), ' ',
            \$object instanceof Demo\Both ? \$object->describe() : \$object->link(), "\n";
    }
    PHP, null, ['-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1']);

for ($i = 0; $i < 3; $i++) {
    echo file_get_contents('http://' . PHP_CLI_SERVER_ADDRESS . '/index.php');
}
?>
--EXPECT--
Lib\Record Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
Lib\Document Lib\HasSlug Lib\HasSlug /articles/intro
Demo\Essay   /articles/setup
 Demo\Described\Defaults,Demo\Told\Defaults Demo\Described\Defaults,Demo\Told\Defaults described+told
Lib\Record Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
Lib\Document Lib\HasSlug Lib\HasSlug /articles/intro
Demo\Essay   /articles/setup
 Demo\Described\Defaults,Demo\Told\Defaults Demo\Described\Defaults,Demo\Told\Defaults described+told
Lib\Record Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
Lib\Document Lib\HasSlug Lib\HasSlug /articles/intro
Demo\Essay   /articles/setup
 Demo\Described\Defaults,Demo\Told\Defaults Demo\Described\Defaults,Demo\Told\Defaults described+told
