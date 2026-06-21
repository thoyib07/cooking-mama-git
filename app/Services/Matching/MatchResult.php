<?php

namespace App\Services\Matching;

use App\Models\Recipe;

class MatchResult
{
    public function __construct(
        public readonly Recipe $recipe,
        public readonly float $score,
        public readonly array $matched,
        public readonly array $missing,
    ) {}
}
