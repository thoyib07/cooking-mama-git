<?php

namespace App\Services\Matching;

use App\Models\Recipe;
use App\Support\IngredientNormalizer;

class RecipeMatcher
{
    public function search(array $rawIngredientNames, float $threshold = 0.5): array
    {
        $have = collect($rawIngredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->filter()
            ->unique()
            ->values();

        if ($have->isEmpty()) {
            return [];
        }

        $results = [];
        $recipes = Recipe::with('ingredients')->has('ingredients')->get();

        foreach ($recipes as $recipe) {
            $names = $recipe->ingredients->pluck('name');
            $total = $names->count();
            $matched = $names->filter(fn ($n) => $have->contains($n))->values();
            $missing = $names->reject(fn ($n) => $have->contains($n))->values();
            $score = $total > 0 ? round($matched->count() / $total, 4) : 0.0;

            if ($score >= $threshold) {
                $results[] = new MatchResult($recipe, $score, $matched->all(), $missing->all());
            }
        }

        usort($results, fn ($a, $b) => $b->score <=> $a->score);
        return $results;
    }
}
