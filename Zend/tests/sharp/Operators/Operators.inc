<?php declare(strict_types=1); namespace Billing;

class Operators
{
    public function sameOrder(\Billing\Order $order, \Billing\Order $sameOrderLoadedAgain): bool { return \Billing\DatabaseEntity::op_Equality($order, $sameOrderLoadedAgain); }

    public function sameCart(\Billing\Cart $cart, \Billing\Cart $sameCartAgain): bool { return $cart === $sameCartAgain; }

    public function same(string $a, string $b): bool { return $a === $b; }

    public function sameNumber(int $a, float $b): bool { return (float) $a === $b; }

    public function before(string $a, string $b): bool { return \strcmp($a, $b) < 0; }

    public function unpriced(?\Billing\Money $price): bool { return $price === null; }

    public function priceIsZero(?\Billing\Money $price, \Billing\Money $zero): bool { return \Billing\Money::op_Equality($price, $zero); }

    public function priceIsNotZero(?\Billing\Money $price, \Billing\Money $zero): bool { return !\Billing\Money::op_Equality($price, $zero); }

    public function totalIsZero(\Billing\Money $total, \Billing\Money $zero): bool { return \Billing\Money::op_Equality($total, $zero); }

    public function priced(?\Billing\Money $price): bool
    {
        if ($price !== null) {
            return true;
        }
        return false;
    }

    public function present(?\Billing\Money $price): bool
    {
        if (!($price === null)) {
            return true;
        }
        return false;
    }

    public function sum(\Billing\Money $a, \Billing\Money $b): \Billing\Money { return \Billing\Money::op_Addition($a, $b); }

    public function difference(\Billing\Money $a, \Billing\Money $b): \Billing\Money { return \Billing\Money::op_Subtraction($a, $b); }

    public function scaled(\Billing\Money $a, int $factor): \Billing\Money { return \Billing\Money::op_Multiply($a, $factor); }

    public function split(\Billing\Money $a, int $parts): \Billing\Money { return \Billing\Money::op_Division($a, $parts); }

    public function leftover(\Billing\Money $a, int $parts): \Billing\Money { return \Billing\Money::op_Modulus($a, $parts); }

    public function raised(\Billing\Money $a, int $power): \Billing\Money { return \Billing\Money::op_Exponent($a, $power); }

    public function refund(\Billing\Money $a): \Billing\Money { return \Billing\Money::op_UnaryNegation($a); }

    public function cheaper(\Billing\Money $a, \Billing\Money $b): bool { return \Billing\Money::op_Comparison($a, $b) < 0; }

    public function compared(\Billing\Money $a, \Billing\Money $b): int { return \Billing\Money::op_Comparison($a, $b); }

    public function settled(\Billing\Money $total, \Billing\Money $paid, int $parts): \Billing\Money
    {
        $left = $total;
        $left = \Billing\Money::op_Subtraction($left, $paid);
        $left = \Billing\Money::op_Modulus($left, $parts);
        return $left;
    }

    public function byPrice(array $lines): array { return ((object) $lines)->sortedBy(fn ($l) => $l->price, \Billing\Money::op_Comparison(...)); }

    public function byCode(array $codes): array { return ((object) $codes)->sortedBy(fn ($code) => $code); }

    public function flags(int $read, int $write, int $delete): int
    {
        $permissions = $read | $write;
        $permissions |= $delete;
        if (($permissions & $write) !== 0) {
            $permissions ^= $read;
        }
        if (($permissions & $write) !== 0) {
            $permissions = $permissions << 1;
        }
        return $permissions;
    }

    public function cleared(int $permissions, int $flag): int { return $permissions & ~$flag; }

    public function remainder(int $total, int $size): int
    {
        $left = $total;
        $left %= $size;
        return $left;
    }

    public function mask(int $count): int
    {
        $mask = 1 << $count;
        return $mask;
    }

    public function active(\Billing\Status $status): string
    {
        $label = match (true) {
            $status === \Billing\Status::Active => "on",
            $status === \Billing\Status::Paused => "paused",
            $status === \Billing\Status::Closed => "closed",
        };
        return $label;
    }

    public function size(int $n): string
    {
        $size = match (true) {
            $n === 200 => "exact",
            $n < 10 => "small",
            $n >= 1000 && $n < 10000 => "large",
            default => "other",
        };
        return $size;
    }

    public function limited(mixed $value): string
    {
        $limit = 10;
        $label = match (true) {
            \is_int($n = $value) and $n === $limit => "at the limit",
            $value instanceof \Billing\Circle => "a circle",
            default => "other",
        };
        return $label;
    }

    public function shape(\Billing\Response $response): string
    {
        return match (true) {
            \is_object($response) && (${'match#1'} = $response->status) === 200 && \is_string($body = $response->body) => $body,
            default => "",
        };
    }

    public function answered(\Billing\Response $response): bool
    {
        if (\is_object($response) && (${'match#1'} = $response->status) === 200) {
            return true;
        }
        return false;
    }

    public function json(\Billing\Response $response): bool
    {
        if (\is_object($response) && (${'match#1'} = $response->status) === 200 && (\is_object(${'match#2'} = $response->body) && (${'match#3'} = ${'match#2'}->type) === "json")) {
            return true;
        }
        return false;
    }

    public function paid(\Billing\Payment $result): int
    {
        if (($p = $result) instanceof \Billing\Paid) {
            return $p->amount;
        }
        $paid = $result instanceof \Billing\Paid ? $result : null;
        return $paid?->amount ?? 0;
    }

    public function required(\Billing\Payment $result): \Billing\Paid
    {
        $paid = ($result instanceof \Billing\Paid ? $result : null) ?? throw new \Billing\NotPaid($result);
        return $paid;
    }

    public function design(mixed $entity): string
    {
        if ($entity instanceof \Billing\HasDesign) {
            return ((object) $entity)->designKey();
        }
        return "";
    }

    public function total(\Billing\Tab $order): int
    {
        if (\is_int($t = $order->total)) {
            return $t + 1;
        }
        return 0;
    }

    public function area(mixed $shape): float
    {
        $area = 0.0;
        if (($circle = $shape) instanceof \Billing\Circle) {
            $area = $circle->radius;
        }
        return $area;
    }

    public function checkout(\Sharp\Map|array $payload, string $plan, \Sharp\Map|array $prices): \Billing\Receipt { $payload = \is_array($payload) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($payload), $payload))) : $payload; $prices = \is_array($prices) ? new \Sharp\Map(\array_merge([], ...\array_map(fn ($key, $value) => [(string) $key, $value], \array_keys($prices), $prices))) : $prices; // PHP# makes each array a Map, its keys strings

        if (!\is_int($orderId = ((object) $payload)->get("orderId"))) { throw new \Billing\BadPayload("orderId"); }
        if (!\is_int($price = ((object) $prices)->get($plan))) { return \Billing\Receipt::unknownPlan($plan); }
        return $this->charge($orderId, $price);
    }

    private function charge(int $orderId, int $price): \Billing\Receipt { return new \Billing\Receipt("{$orderId}: {$price}"); }

    public function undesigned(mixed $entity): bool
    {
        if (!($entity instanceof \Billing\HasDesign)) {
            return true;
        }
        return false;
    }

    public function unsettled(\Billing\Payment $result): bool
    {
        if (!($result instanceof \Billing\Paid || $result instanceof \Billing\Refunded)) {
            return true;
        }
        return false;
    }

    public function coded(?string $code): bool
    {
        if (!($code === null) && !($code === "")) {
            return true;
        }
        return false;
    }

    public function nested(bool $a, bool $c, int $b, int $d, int $e): int { return $a ? $b : ($c ? $d : $e); }
}
