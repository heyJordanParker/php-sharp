--TEST--
A PHP# class imports PHP 8.4's XML DOM, which the standard library names, and reads and writes a document with a namespace through it: the root's namespace, an element's attribute, the document with that element edited as saveXml writes it, and the element's markup as outerHTML and innerHTML give it
--EXTENSIONS--
dom
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use XmlDocuments\Feed;

$xml = '<?xml version="1.0" encoding="UTF-8"?><feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><entry id="1" title="Old">Hello <media:thumbnail url="a.png"/></entry></feed>';
var_dump(Feed::namespaceOf($xml), Feed::title($xml));
echo Feed::retitled($xml, 'New'), "\n";
var_dump(Feed::markup($xml), Feed::content($xml));
?>
--EXPECT--
string(27) "http://www.w3.org/2005/Atom"
string(3) "Old"
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><entry id="1" title="New">Hello <media:thumbnail url="a.png"/><![CDATA[<b>new</b>]]></entry></feed>
string(150) "<entry xmlns="http://www.w3.org/2005/Atom" id="1" title="Old">Hello <media:thumbnail xmlns:media="http://search.yahoo.com/mrss/" url="a.png"/></entry>"
string(80) "Hello <media:thumbnail xmlns:media="http://search.yahoo.com/mrss/" url="a.png"/>"
