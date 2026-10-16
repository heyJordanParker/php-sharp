<?php

require __DIR__ . '/harness/Header.inc';
foreach (glob(__DIR__ . '/Header/*.sharp') as $file) {
    opcache_compile_file($file);
}
