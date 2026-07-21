<?php

use App\Livewire\RecipeList;
use App\Models\Recipe;
use Livewire\Livewire;

it('shows newest recipes first', function () {
    $old = Recipe::create(['name' => 'Old Recipe', 'steps' => ['x'], 'source' => 'seed', 'created_at' => now()->subDay()]);
    $new = Recipe::create(['name' => 'New Recipe', 'steps' => ['x'], 'source' => 'seed', 'created_at' => now()]);

    Livewire::test(RecipeList::class)
        ->assertSeeInOrder([$new->name, $old->name]);
});

it('filters recipes by search term', function () {
    Recipe::create(['name' => 'Egg Fry', 'steps' => ['x'], 'source' => 'seed']);
    Recipe::create(['name' => 'Rice Bowl', 'steps' => ['x'], 'source' => 'seed']);

    Livewire::test(RecipeList::class)
        ->set('search', 'egg')
        ->assertSee('Egg Fry')
        ->assertDontSee('Rice Bowl');
});

it('loads more recipes without duplicates', function () {
    foreach (range(1, 15) as $i) {
        Recipe::create(['name' => "Recipe {$i}", 'steps' => ['x'], 'source' => 'seed']);
    }

    Livewire::test(RecipeList::class)
        ->assertSet('perPage', 12)
        ->call('loadMore')
        ->assertSet('perPage', 24);
});

it('resets perPage when the search term changes', function () {
    Livewire::test(RecipeList::class)
        ->call('loadMore')
        ->assertSet('perPage', 24)
        ->set('search', 'egg')
        ->assertSet('perPage', 12);
});
