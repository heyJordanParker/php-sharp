--TEST--
A PHP# class calls every method of Sharp.Text.Html, none of which can fail
--FILE--
<?php
require __DIR__ . '/../../../sharp/composer/library/Sharp/Text/Html.sharp';
require __DIR__ . '/HtmlCalls.sharp';

use Demo\HtmlCalls;

var_dump(HtmlCalls::escape('<a href="x">Tom & \'Jo\'</a>'));
var_dump(HtmlCalls::unescape('&lt;b&gt; &amp;amp; &#039;'));
var_dump(HtmlCalls::decodeEntities('caf&eacute; &euro;5 &lt;'));
var_dump(HtmlCalls::stripTags('<p>Hi <b>there</b></p>'));
?>
--EXPECT--
string(62) "&lt;a href=&quot;x&quot;&gt;Tom &amp; &#039;Jo&#039;&lt;/a&gt;"
string(11) "<b> &amp; '"
string(12) "café €5 <"
string(8) "Hi there"
