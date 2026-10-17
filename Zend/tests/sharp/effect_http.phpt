--TEST--
PHP# Http sends a request through curl and returns the server's response, a 404 included, and throws when the connection fails
--EXTENSIONS--
curl
--FILE--
<?php
require __DIR__ . '/effect_classes.inc';
require __DIR__ . '/../../../sapi/cli/tests/php_cli_server.inc';

use Sharp\Net\HttpMethod;
use Sharp\Net\HttpRequest;
use Sharp\Time\Duration;

php_cli_server_start(<<<'PHP'
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path === '/missing') {
        http_response_code(404);
        echo 'gone';
        return;
    }
    if ($path === '/slow') {
        sleep(2);
    }
    header('X-Seen: one');
    header('X-Seen: two', false);
    echo $_SERVER['REQUEST_METHOD'], ' ', $_SERVER['HTTP_X_TOKEN'] ?? '-', ' ', file_get_contents('php://input');
    PHP);
$base = 'http://' . PHP_CLI_SERVER_ADDRESS;

$http = new Sharp\Http();
$got = $http->get("$base/");
var_dump($got->status, $got->body, $got->headers['x-seen'], $got->successful);

$posted = $http->send(new HttpRequest(HttpMethod::Post, "$base/orders", ['X-Token' => 'abc'], '{"sku":"tea"}'));
var_dump($posted->status, $posted->body);

$headed = $http->send(new HttpRequest(HttpMethod::Head, "$base/"));
var_dump($headed->status, $headed->body);

$missing = $http->get("$base/missing");
var_dump($missing->status, $missing->body, $missing->successful);

try {
    $http->get('not a url');
} catch (ValueError $e) {
    echo $e::class, ': ', $e->getMessage(), "\n";
}
// A line break in a header's name or value would start a header of its own.
foreach ([fn () => new HttpRequest(HttpMethod::Get, "$base/", ["X-Token\r\nX-Admin" => 'yes']), fn () => new HttpRequest(HttpMethod::Get, "$base/", ['X-Token' => "abc\nX-Admin: yes"])] as $refused) {
    try {
        $refused();
    } catch (ValueError $e) {
        echo $e::class, ': ', $e->getMessage(), "\n";
    }
}
foreach ([new HttpRequest(HttpMethod::Get, "$base/slow", timeout: Duration::ofMilliseconds(200)), new HttpRequest(HttpMethod::Get, 'http://localhost:1/')] as $failing) {
    try {
        $http->send($failing);
    } catch (RuntimeException $e) {
        echo $e::class, ': ', str_starts_with($e->getMessage(), "Http.send: {$failing->url} failed: ") ? 'names the URL and the failure' : $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(200)
string(6) "GET - "
array(2) {
  [0]=>
  string(3) "one"
  [1]=>
  string(3) "two"
}
bool(true)
int(200)
string(22) "POST abc {"sku":"tea"}"
int(200)
string(0) ""
int(404)
string(4) "gone"
bool(false)
ValueError: Http.send: "not a url" is not a valid URL
ValueError: HttpRequest: a header name holds a line break
ValueError: HttpRequest: the value of the header "X-Token" holds a line break
RuntimeException: names the URL and the failure
RuntimeException: names the URL and the failure
