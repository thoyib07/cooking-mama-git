<?php

use App\Livewire\RecipeFinder;
use App\Models\Recipe;
use App\Models\Ingredient;
use Livewire\Livewire;

it('adds ingredients and returns ranked matches', function () {
    $r = Recipe::create(['name' => 'Egg Fry', 'steps' => ['x'], 'source' => 'seed']);
    $r->ingredients()->attach(Ingredient::findOrCreateNormalized('egg'));
    $r->ingredients()->attach(Ingredient::findOrCreateNormalized('salt'));

    Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'Egg')->call('addIngredient')
        ->set('newIngredient', 'salt')->call('addIngredient')
        ->call('search')
        ->assertSet('results.0.name', 'Egg Fry')
        ->assertSet('results.0.score', 1.0);
});
