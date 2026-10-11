<?php declare(strict_types=1); namespace Billing;

enum Status
{
    case Active;
    case Paused;
    case Closed;
}

interface HasDesign
{
    public function designKey(): string;
}

interface Payment
{
}

final class Paid implements Payment
{
    public function __construct(public int $amount)
    {
    }
}

final class Refunded implements Payment
{
}

final class NotPaid extends \Exception
{
    public function __construct(public mixed $result)
    {
        parent::__construct('not paid');
    }
}

final class BadPayload extends \Exception
{
}

final class Ungraded extends \LogicException
{
}

final class Body
{
    public function __construct(public string $type)
    {
    }
}

final class Response
{
    public function __construct(public int $status, public Body|string $body)
    {
    }
}

final class Circle
{
    public function __construct(public float $radius)
    {
    }
}

final class Square
{
    public function __construct(public float $side)
    {
    }
}

final class Receipt
{
    public function __construct(public string $line)
    {
    }

    public static function unknownPlan(string $plan): Receipt
    {
        return new Receipt("unknown plan $plan");
    }
}

final class Tab
{
    public function __construct(public mixed $total)
    {
    }
}
