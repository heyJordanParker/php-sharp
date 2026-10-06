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
    require $dir . '/Header.inc';
    require $dir . '/Header.sharp';
    \$page = new Demo\Page();
    \$interfaces = class_implements(\$page);
    ksort(\$interfaces);
    echo get_parent_class(\$page), ' ', implode(',', \$interfaces), ' ', \$page->number(), "\n";
    \$card = new Demo\Card();
    \$interfaces = class_implements(\$card);
    ksort(\$interfaces);
    echo get_parent_class(\$card), ' ', implode(',', \$interfaces), ' ', \$card->copy()->link(), "\n";
    PHP, null, ['-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1']);

for ($i = 0; $i < 3; $i++) {
    echo file_get_contents('http://' . PHP_CLI_SERVER_ADDRESS . '/index.php');
}
?>
--EXPECT--
Lib\Entity Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
Lib\Entity Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
Lib\Entity Demo\Linkable,Lib\Named 7
Lib\Shelf Demo\Linkable,Lib\Named /card
