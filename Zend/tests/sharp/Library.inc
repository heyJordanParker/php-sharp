<?php declare(strict_types=1); namespace Demo;




















class Library
{
    final public static function initial(int $code): string { return \Sharp\Text\Text::fromByte($code); }

    final public static function isNumber(string $text): bool { return \Sharp\Text\Regex::matches("/^[0-9]+$/", $text); }

    final public static function escape(string $html): string { return \htmlspecialchars($html); }

    final public static function square(float $side): float { return \Sharp\Math\Math::pow($side, 2.0); }

    final public static function decode(string $json) { return @\json_decode($json, true, 512, \JSON_THROW_ON_ERROR); }

    final public static function digest(string $data): string { return \sha1($data); }

    final public static function base64(string $data): string { return \base64_encode($data); }

    final public static function hexadecimal(string $data): string { return \bin2hex($data); }

    final public static function checks(string $password, string $hash): bool { return @\password_verify($password, $hash); }

    final public static function serialize(mixed $value): string { return \serialize($value); }

    final public static function bigEndian(int $value): string { return \Sharp\Data\Binary::pack("N", $value); }

    final public static function compress(string $data): string { return \Sharp\Data\Compression::compress($data); }

    final public static function extension(string $path): string { return \pathinfo($path, \PATHINFO_EXTENSION); }

    final public static function day(int $timestamp): string { return \Sharp\Time\Date::format($timestamp, "Y-m-d"); }

    final public static function zones(): int { return \count(\array_values(\timezone_identifiers_list())); }

    final public static function query(\Sharp\Map|array $values): string { $values = \is_array($values) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($values), $values))) : $values; return \Sharp\Net\Url::buildQuery($values); } // a form whose parameter is a Map is called, as the method makes plain PHP's array a Map

    final public static function isEmail(string $address): bool { return \Sharp\Net\Email::isValid($address); }

    final public static function isIp(string $address): bool { return \Sharp\Net\Ip::isValid($address); }
}
