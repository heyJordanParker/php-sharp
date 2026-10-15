--TEST--
PHP# fields, properties, computed properties, constants and named constructors written without an access modifier are private
--FILE--
<?php
require __DIR__ . '/default_access.inc';
?>
--EXPECT--
11
count private: true
views private: true
size private: true
MAX private: true
empty private: true
Cannot access private property Defaults\Counter::$views
Cannot access private property Defaults\Counter::$size
Cannot access private constant Defaults\Counter::MAX
Call to private method Defaults\Counter::empty() from global scope
