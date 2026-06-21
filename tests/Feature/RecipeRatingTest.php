<?php

use App\Livewire\RecipeRating;
use App\Models\Recipe;
use App\Models\Rating;
use Livewire\Livewire;

it('records a rating once per session', function () {
    $recipe = Recipe::create(['name' => 'X', 'instructions' => 'y', 'source' => 'seed']);

    Livewire::test(RecipeRating::class, ['recipe' => $recipe])
        ->call('rate', 5)
        ->call('rate', 3)
        ->assertSet('count', 1)
        ->assertSet('average', 5.0);

    expect(Rating::count())->toBe(1);
});
