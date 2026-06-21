<?php

use App\Models\Recipe;
use App\Services\Gemini\AiRecipeImporter;

it('imports an AI recipe with normalized ingredients', function () {
    $recipe = (new AiRecipeImporter())->import([
        'name' => 'AI Curry',
        'ingredients' => ['Coconut Milk', 'curry powder'],
        'instructions' => 'Simmer.',
        'servings' => 4,
    ]);

    expect($recipe)->not->toBeNull();
    expect($recipe->source)->toBe(Recipe::SOURCE_AI);
    expect($recipe->ingredients->pluck('name')->all())->toContain('coconut milk');
});

it('dedups by normalized name', function () {
    Recipe::create(['name' => 'AI Curry', 'instructions' => 'x', 'source' => 'ai']);
    $dup = (new AiRecipeImporter())->import([
        'name' => '  ai curry ', 'ingredients' => ['x'], 'instructions' => 'y',
    ]);
    expect($dup)->toBeNull();
});
