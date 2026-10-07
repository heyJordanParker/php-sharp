<?php declare(strict_types=1); namespace Demo;




















class Library
{
    public static function initial(int $code): string { return \Sharp\Text\Text::fromByte($code); }

    public static function isNumber(string $text): bool { return \Sharp\Text\Regex::matches("/^[0-9]+$/", $text); }

    public static function escape(string $html): string { return \Sharp\Text\Html::escape($html); }

    public static function square(float $side): float { return \Sharp\Math\Math::pow($side, 2.0); }

    public static function decode(string $json): mixed { return \Sharp\Json\Json::decode($json); }

    public static function digest(string $data): string { return \Sharp\Data\Hash::sha1($data); }

    public static function base64(string $data): string { return \Sharp\Data\Base64::encode($data); }

    public static function hexadecimal(string $data): string { return \Sharp\Data\Hex::encode($data); }

    public static function checks(string $password, string $hash): bool { return \Sharp\Data\Password::verify($password, $hash); }

    public static function serialize(mixed $value): string { return \Sharp\Data\PhpSerializer::serialize($value); }

    public static function bigEndian(int $value): string { return \Sharp\Data\Binary::pack("N", $value); }

    public static function compress(string $data): string { return \Sharp\Data\Compression::compress($data); }

    public static function extension(string $path): string { return \Sharp\IO\Path::extension($path); }

    public static function day(int $timestamp): string { return \Sharp\Time\Date::format($timestamp, "Y-m-d"); }

    public static function zones(): int { return \count(\Sharp\Time\TimeZone::names()); }

    public static function query(array $values): string { return \Sharp\Net\Url::buildQuery($values); }

    public static function isEmail(string $address): bool { return \Sharp\Net\Email::isValid($address); }

    public static function isIp(string $address): bool { return \Sharp\Net\Ip::isValid($address); }
}
