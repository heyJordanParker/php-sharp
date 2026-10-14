<?php declare(strict_types=1); namespace Demo;

class Patterns
{
    public function area(\Demo\Figure $shape): float
    {
        if (($circle = $shape) instanceof \Demo\Circle) {
            return 3.0 * $circle->radius * $circle->radius;
        }
        if (!(($square = $shape) instanceof \Demo\Square)) {
            return 0.0;
        }
        return $square->side * $square->side;
    }

    public function side(\Demo\Figure $shape): float
    {
        if ($shape instanceof \Demo\Square) {
            return $shape->side;
        }
        return 0.0;
    }

    public function radius(\Demo\Figure $shape): float
    {
        $circle = $shape instanceof \Demo\Circle ? $shape : null;
        return $circle?->radius ?? 0.0;
    }

    public function describe(?\Demo\Figure $shape): string
    {
        return match (true) {
            null === $shape => "nothing",
            ($c = $shape) instanceof \Demo\Circle and $c->radius > 10.0 => "big circle",
            $shape instanceof \Demo\Circle => "circle",
            default => "square",
        };
    }

    public function grade(int $score): string
    {
        return match (true) {
            $score < 0 => "invalid",
            $score >= 90 => "top",
            $score >= 50 && $score < 70 => "pass",
            default => "fail",
        };
    }

    public function count(?\Demo\Figure $shape): int
    {
        $total = 0;

        if ($shape instanceof \Demo\Circle) {
            $total = 1;
        }
        else if (($s = $shape) instanceof \Demo\Square and $s->side > 1.0) { $total = 2; }
        else {}

        return $total;
    }

    public function wide(\Demo\Circle $circle): bool { return \is_object($circle) && (${'match#1'} = $circle->radius) >= 2.0; }

    public function first(\Demo\Patterns $patterns): string { return match (true) { (${'match#1'} = ((object) $patterns)->grade(95))
        === "top" => "first",
        default => "other",
    }; }

    public function state(\Demo\Status $status): string
    {
        return match (true) {
            $status === \Demo\Status::Open => "open",
            $status === \Demo\Status::Closed || $status === \Demo\Status::Archived => "done",
        };
    }

    public function closed(?\Demo\Status $status): int
    {
        $total = 0;

        if ($status === \Demo\Status::Open) { $total = 1; }
        else if ($status === \Demo\Status::Closed || $status === \Demo\Status::Archived) { $total = 2; }
        else if ($status === null) {}

        return $total;
    }

    public function big(\Demo\Figure $shape): float
    {
        if (($big = $shape) instanceof \Demo\Circle && (${'match#1'} = $big->radius) > 10.0) {
            return $big->radius;
        }
        return $shape instanceof \Demo\Square && (${'match#1'} = $shape->side) === 0.0 ? -1.0 : 0.0;
    }

    public function lines(array $shapes): int { return match (true) {
        \is_array($shapes) && \count($shapes) === 0 => 0,
        \is_array($shapes) && \count($shapes) === 1 && (${'match#1'} = $shapes[0]) instanceof \Demo\Circle => 1,
        \is_array($shapes) && \count($shapes) >= 1 && ((${'match#2'} = $shapes[0]) instanceof \Demo\Square && (${'match#3'} = ${'match#2'}->side) > 1.0) && \is_array($rest = \array_slice($shapes, 1)) => 2 + \count($rest),
        default => -1,
    }; }

    public function head(?array $shapes): float
    {
        if (\is_array($shapes) && \count($shapes) >= 1 && ($first = $shapes[0]) instanceof \Demo\Circle && \is_array($others = \array_slice($shapes, 1)) && \count($others) === 0) {
            return $first->radius;
        }
        return 0.0;
    }

    public function ends(array $shapes): float { return match (true) {
        \is_array($shapes) && \count($shapes) >= 2 && (${'match#1'} = $shapes[0]) instanceof \Demo\Circle && (${'match#2'} = $shapes[\count($shapes) - 1]) instanceof \Demo\Circle && \is_array($inner = \array_slice($shapes, 1, \count($shapes) - 2)) => -(float) \count($inner),
        \is_array($shapes) && \count($shapes) >= 1 && ($last = $shapes[\count($shapes) - 1]) instanceof \Demo\Circle && \is_array($before = \array_slice($shapes, 0, \count($shapes) - 1)) => $last->radius + \count($before),
        \is_array($shapes) && \count($shapes) >= 2 && ($first = $shapes[0]) instanceof \Demo\Square && ($last = $shapes[\count($shapes) - 1]) instanceof \Demo\Square => $first->side + $last->side,
        default => 0.0,
    }; }
}
