--TEST--
Plain PHP closures, arrow functions, first-class callables and a trait's closure that the preload request already made bind, rebind and call in each request as they do without preloading
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/closure_binding_preload.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
echo binding_report(), "\n";
echo binding_report(), "\n";
echo binding_report(), "\n";
?>
--EXPECT--
{"closure":["A","A",6,"A-tag"],"bind":["B","B",2.5,"B-tag"],"bindTo":["B","B",2.5,"B-tag"],"call":["B","B",2.5,"B-tag"],"bindTo subclass":["A","A2",6,"A-tag"],"static":["A","A"],"static bind scope":["B","B"],"arrow":10,"arrow bind":3,"first-class":"A35","fromCallable":"A45","fromCallable static":"A","first-class static":"A2","trait A":["A","A","A","A-tag"],"trait B":["B","B","B","B-tag"],"trait bind":["B","B","B","B-tag"],"warning":["Cannot bind method A::method() to object of class B, this will be an error in PHP 9","Cannot bind an instance to a static closure, this will be an error in PHP 9"],"first-class bindTo":null,"static bindTo this":null}
{"closure":["A","A",6,"A-tag"],"bind":["B","B",2.5,"B-tag"],"bindTo":["B","B",2.5,"B-tag"],"call":["B","B",2.5,"B-tag"],"bindTo subclass":["A","A2",6,"A-tag"],"static":["A","A"],"static bind scope":["B","B"],"arrow":10,"arrow bind":3,"first-class":"A35","fromCallable":"A45","fromCallable static":"A","first-class static":"A2","trait A":["A","A","A","A-tag"],"trait B":["B","B","B","B-tag"],"trait bind":["B","B","B","B-tag"],"warning":["Cannot bind method A::method() to object of class B, this will be an error in PHP 9","Cannot bind an instance to a static closure, this will be an error in PHP 9"],"first-class bindTo":null,"static bindTo this":null}
{"closure":["A","A",6,"A-tag"],"bind":["B","B",2.5,"B-tag"],"bindTo":["B","B",2.5,"B-tag"],"call":["B","B",2.5,"B-tag"],"bindTo subclass":["A","A2",6,"A-tag"],"static":["A","A"],"static bind scope":["B","B"],"arrow":10,"arrow bind":3,"first-class":"A35","fromCallable":"A45","fromCallable static":"A","first-class static":"A2","trait A":["A","A","A","A-tag"],"trait B":["B","B","B","B-tag"],"trait bind":["B","B","B","B-tag"],"warning":["Cannot bind method A::method() to object of class B, this will be an error in PHP 9","Cannot bind an instance to a static closure, this will be an error in PHP 9"],"first-class bindTo":null,"static bindTo this":null}
