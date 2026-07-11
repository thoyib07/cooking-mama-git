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

it('ranks all recipes by ingredient match ratio without a threshold', function () {
    makeRecipe('Full Match', ['egg', 'salt']);
    makeRecipe('Partial', ['egg', 'salt', 'flour', 'milk']);
    makeRecipe('Too Low', ['beef', 'salt', 'pepper', 'oil']);

    $results = (new RecipeMatcher())->search(['Egg', 'salt']);

    expect($results)->toHaveCount(3);
    expect($results[0]->recipe->name)->toBe('Full Match');
    expect($results[0]->score)->toBe(1.0);
    expect($results[1]->recipe->name)->toBe('Partial');
    expect($results[1]->missing)->toContain('flour');
    expect($results[1]->matched)->toContain('egg');
    expect($results[2]->recipe->name)->toBe('Too Low');
    expect($results[2]->score)->toBe(0.25);
});

it('breaks a score tie by fewest missing ingredients', function () {
    makeRecipe('More Missing', ['egg', 'onion', 'salt', 'flour']);
    makeRecipe('Fewer Missing', ['egg', 'salt']);

    $results = (new RecipeMatcher())->search(['egg', 'onion']);

    expect($results[0]->recipe->name)->toBe('Fewer Missing');
    expect($results[0]->score)->toBe(0.5);
    expect($results[0]->missing)->toHaveCount(1);
    expect($results[1]->recipe->name)->toBe('More Missing');
    expect($results[1]->score)->toBe(0.5);
    expect($results[1]->missing)->toHaveCount(2);
});
