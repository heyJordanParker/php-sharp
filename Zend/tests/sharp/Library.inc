<?php declare(strict_types=1); namespace Demo;




















class Library
{
    public static function initial(int $code): string { return \Sharp\Text\Text::fromByte($code); }

    public static function isNumber(string $text): bool { return \Sharp\Text\Regex::matches("/^[0-9]+$/", $text); }

    public static function escape(string $html): string { return \htmlspecialchars($html); }

    public static function square(float $side): float { return \Sharp\Math\Math::pow($side, 2.0); }

    public static function decode(string $json): mixed { return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR); }

    public static function digest(string $data): string { return \sha1($data); }

    public static function base64(string $data): string { return \base64_encode($data); }

    public static function hexadecimal(string $data): string { return \bin2hex($data); }

    public static function checks(string $password, string $hash): bool { return \password_verify($password, $hash); }

    public static function serialize(mixed $value): string { return \serialize($value); }

    public static function bigEndian(int $value): string { return \Sharp\Data\Binary::pack("N", $value); }

    public static function compress(string $data): string { return \Sharp\Data\Compression::compress($data); }

    public static function extension(string $path): string { return \pathinfo($path, \PATHINFO_EXTENSION); }

    public static function day(int $timestamp): string { return \Sharp\Time\Date::format($timestamp, "Y-m-d"); }

    public static function zones(): int { return \count(\array_values(\timezone_identifiers_list())); }

    public static function query(array $values): string { return \http_build_query($values); }

    public static function isEmail(string $address): bool { return \Sharp\Net\Email::isValid($address); }

    public static function isIp(string $address): bool { return \Sharp\Net\Ip::isValid($address); }
}
