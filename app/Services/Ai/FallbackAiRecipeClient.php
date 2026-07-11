<?php

namespace App\Services\Ai;

use Throwable;

class FallbackAiRecipeClient implements AiRecipeClient
{
    public function __construct(
        private AiRecipeClient $primary,
        private AiRecipeClient $fallback,
    ) {}

    public function suggest(array $ingredientNames): array
    {
        try {
            return $this->primary->suggest($ingredientNames);
        } catch (Throwable $e) {
            report($e);

            return $this->fallback->suggest($ingredientNames);
        }
    }
}
