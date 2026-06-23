<?php

use App\Models\Recipe;
use App\Models\Ingredient;
use App\Services\Matching\RecipeMatcher;

function makeRecipe(string $name, array $ings): Recipe
{
    $r = Recipe::create(['name' => $name, 'steps' => ['x'], 'source' => Recipe::SOURCE_SEED]);
    foreach ($ings as $i) {
        $r->ingredients()->attach(Ingredient::findOrCreateNormalized($i));
    }
    return $r;
}

it('ranks recipes by ingredient match ratio and applies threshold', function () {
    makeRecipe('Full Match', ['egg', 'salt']);
    makeRecipe('Partial', ['egg', 'salt', 'flour', 'milk']);
    makeRecipe('Too Low', ['beef', 'salt', 'pepper', 'oil']);

    $results = (new RecipeMatcher())->search(['Egg', 'salt'], 0.5);

    expect($results)->toHaveCount(2);
    expect($results[0]->recipe->name)->toBe('Full Match');
    expect($results[0]->score)->toBe(1.0);
    expect($results[1]->recipe->name)->toBe('Partial');
    expect($results[1]->missing)->toContain('flour');
    expect($results[1]->matched)->toContain('egg');
});
