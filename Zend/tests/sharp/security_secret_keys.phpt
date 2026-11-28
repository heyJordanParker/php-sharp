--TEST--
A Secret's stored form decrypts only in the field it was encrypted for, under any key of SHARP_KEYS, so text under an older key reads after a newer key is put first, spec section 24. A Secret outside a field, or with no SHARP_KEYS, never stores, and a plain PHP dump shows no value
--ENV--
SHARP_KEYS=k1:Hx4dHBsaGRgXFhUUExIREA8ODQwLCgkIBwYFBAMCAQA=
--FILE--
<?php
require __DIR__ . '/vendor/autoload.php';

use Demo\SecretCalls;

function outcome(callable $call): void
{
    try {
        var_dump($call());
    } catch (Throwable $thrown) {
        echo $thrown::class, ': ', $thrown->getMessage(), "\n";
    }
}

$stored = SecretCalls::encoded('hunter2');
putenv('SHARP_KEYS=k2:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=,k1:Hx4dHBsaGRgXFhUUExIREA8ODQwLCgkIBwYFBAMCAQA=');
outcome(fn () => SecretCalls::decoded($stored));
$newer = SecretCalls::encoded('hunter2');
outcome(fn () => SecretCalls::decoded($newer));

$moved = json_encode(['token' => json_decode($stored)->password]);
outcome(fn () => SecretCalls::relayed($moved));

putenv('SHARP_KEYS=k2:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=');
outcome(fn () => SecretCalls::decoded($stored));
outcome(fn () => SecretCalls::alone());
var_dump(SecretCalls::held());

putenv('SHARP_KEYS');
outcome(fn () => SecretCalls::encoded('hunter2'));
?>
--EXPECTF--
string(7) "hunter2"
string(7) "hunter2"
LogicException: token: the encrypted text is not one SHARP_KEYS decrypts for Demo.Relay.token
LogicException: password: the encrypted text is not one SHARP_KEYS decrypts for Demo.SmtpAccount.password
Error: `serialize` writes the stored form of Sharp.Security.Secret, the fields `Json.encode` writes, and a Secret is stored only in a field of a class or struct, whose name its encryption is bound to.
object(Sharp\Security\Secret)#%d (1) {
  [0]=>
  string(10) "**********"
}
RuntimeException: a Secret is encrypted with the keys in SHARP_KEYS, which is not set. Generate a key with `vendor/bin/mago generate-key`.
