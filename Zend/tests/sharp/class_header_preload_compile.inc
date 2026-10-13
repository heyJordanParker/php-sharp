<?php

require __DIR__ . '/Header.inc';
opcache_compile_file(__DIR__ . '/Header.sharp');
require __DIR__ . '/harness/Model.inc';
opcache_compile_file(__DIR__ . '/Traits.sharp');
