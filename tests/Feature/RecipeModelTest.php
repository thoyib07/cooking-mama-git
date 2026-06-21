<?php

use App\Models\Recipe;
use App\Models\Ingredient;

it('attaches normalized ingredients to a recipe', function () {
    $recipe = Recipe::create([
        'name' => 'Omelette',
        'instructions' => 'Beat eggs, fry.',
        'source' => Recipe::SOURCE_SEED,
        'servings' => 2,
    ]);
    $egg = Ingredient::findOrCreateNormalized('  Eggs ');
    $recipe->ingredients()->attach($egg);

    expect($egg->name)->toBe('eggs');
    expect($recipe->ingredients)->toHaveCount(1);
    expect(Ingredient::findOrCreateNormalized('eggs')->id)->toBe($egg->id);
});
