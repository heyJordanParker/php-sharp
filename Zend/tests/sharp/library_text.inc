<?php
// The members of string in Sharp.Text, the List's members they call, Bytes and the Hex encoding a List<int>'s toBytes
// is read through, and the PHP# class that calls them, which library_text.phpt requires and library_text_preload.phpt
// preloads.
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Text/StringMembers.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/ListMembers.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Int.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Bytes.sharp';
require __DIR__ . '/vendor/heyjordanparker/php-sharp-composer/library/Sharp/Encoding/Hex.sharp';
require __DIR__ . '/Demo/TextCalls.sharp';
