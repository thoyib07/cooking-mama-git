<?php

use App\Livewire\RecipeFinder;
use App\Models\Ingredient;
use App\Models\Recipe;
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

it('rejects gibberish ingredient input with an error message', function () {
    Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'asdrtey12hdj')->call('addIngredient')
        ->assertSet('ingredients', [])
        ->assertSet('ingredientError', 'Bahan "asdrtey12hdj" tidak dikenali. Periksa lagi penulisannya.');
});

function aiButtonIsDisabled(string $html): bool
{
    preg_match('/<button[^>]*wire:click="exploreWithAi"[^>]*>/', $html, $matches);

    return (bool) preg_match('/(^|\s)disabled(\s|>)/', $matches[0] ?? '');
}

it('keeps the AI explore button disabled before the first database search', function () {
    $component = Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'egg')->call('addIngredient');

    expect(aiButtonIsDisabled($component->html()))->toBeTrue();
});

it('enables the AI explore button after the first search, even if the database found matches', function () {
    // AI exploration only guards the *first* search — if the user already tried the
    // database and doesn't want those results either, they should still be able to
    // ask the AI, regardless of whether anything matched.
    $r = Recipe::create(['name' => 'Egg Fry', 'steps' => ['x'], 'source' => 'seed']);
    $r->ingredients()->attach(Ingredient::findOrCreateNormalized('egg'));

    $component = Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'egg')->call('addIngredient')
        ->call('search');

    expect($component->get('results.0.score'))->toBe(1.0)
        ->and(aiButtonIsDisabled($component->html()))->toBeFalse();
});

it('re-disables the AI explore button once all ingredients are removed', function () {
    $component = Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'egg')->call('addIngredient')
        ->call('search');
    expect(aiButtonIsDisabled($component->html()))->toBeFalse();

    $component->call('removeIngredient', 0);
    expect(aiButtonIsDisabled($component->html()))->toBeTrue();
});
