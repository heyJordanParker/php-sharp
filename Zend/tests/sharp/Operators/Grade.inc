<?php declare(strict_types=1); namespace Billing;

class Grade
{
    public readonly int $score;

    public function __construct(int $score)
    {
        $this->score = $score;
    }

    public static function op_Comparison(\Billing\Grade $a, \Billing\Grade $b): int
    {
        if ($a->score < 0 || $b->score < 0) {
            throw new \Billing\Ungraded("a negative grade has no order");
        }
        return $a->score <=> $b->score;
    }

    final public static function ranked(array $grades): array { return ((object) $grades)->sortedBy(fn ($grade) => $grade, \Billing\Grade::op_Comparison(...)); }
}
