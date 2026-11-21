--TEST--
Secret<T> keeps a value encrypted in its stored form under the newest key of SHARP_KEYS and gives it back as value, and Hashed<T> keeps only a value's Argon2id hash and checks a value with verify, spec section 24
--ENV--
SHARP_KEYS=k2:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=,k1:Hx4dHBsaGRgXFhUUExIREA8ODQwLCgkIBwYFBAMCAQA=
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\SecretCalls;

[$value, $shown, $stored, $decoded, $built] = SecretCalls::secret();
var_dump($value, $shown, $decoded, $built);
var_dump(preg_match('~^\{"host":"smtp\.example\.com","password":\{"encrypted":"[A-Za-z0-9_-]+"\}\}$~', $stored));

[$verified, $shown, $stored, $decoded, $built, $number] = SecretCalls::hashed();
var_dump($verified, $shown, $decoded, $built, $number);
var_dump(preg_match('~^\{"email":"ada@example\.com","password":\{"hashed":"\$argon2id\$v=19\$m=65536[^"]+"\}\}$~', $stored));
?>
--EXPECT--
string(7) "hunter2"
string(20) "password: **********"
string(7) "hunter2"
string(7) "hunter2"
int(1)
bool(true)
string(20) "password: **********"
bool(true)
bool(true)
bool(true)
int(1)
