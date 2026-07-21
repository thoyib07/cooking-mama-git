<?php

use App\Livewire\FavoriteButton;
use App\Livewire\FavoritesList;
use App\Livewire\RecipeList;
use App\Models\Favorite;
use App\Models\Recipe;
use App\Support\FavoritorToken;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('generates a valid uuid token when none exists yet', function () {
    $token = FavoritorToken::get();

    expect(Str::isUuid($token))->toBeTrue();
});

it('toggles a favorite on and off', function () {
    $recipe = Recipe::create(['name' => 'Egg Fry', 'steps' => ['x'], 'source' => 'seed']);
    $token = (string) Str::uuid();
    Livewire::withCookie('favoritor_token', $token);

    Livewire::test(FavoriteButton::class, ['recipeId' => $recipe->id])
        ->assertSet('isFavorited', false)
        ->call('toggle')
        ->assertSet('isFavorited', true);

    expect(Favorite::where('recipe_id', $recipe->id)->where('favoritor_token', $token)->exists())->toBeTrue();

    Livewire::test(FavoriteButton::class, ['recipeId' => $recipe->id])
        ->assertSet('isFavorited', true)
        ->call('toggle')
        ->assertSet('isFavorited', false);

    expect(Favorite::where('recipe_id', $recipe->id)->exists())->toBeFalse();
});

it('filters the recipe list to only favorited recipes for the current token', function () {
    $favorited = Recipe::create(['name' => 'Favorited Recipe', 'steps' => ['x'], 'source' => 'seed']);
    $other = Recipe::create(['name' => 'Other Recipe', 'steps' => ['x'], 'source' => 'seed']);
    $token = (string) Str::uuid();
    Favorite::create(['recipe_id' => $favorited->id, 'favoritor_token' => $token]);

    Livewire::withCookie('favoritor_token', $token);

    Livewire::test(RecipeList::class)
        ->set('onlyFavorites', true)
        ->assertSee('Favorited Recipe')
        ->assertDontSee('Other Recipe');
});

it('does not leak another visitor\'s favorites', function () {
    $recipe = Recipe::create(['name' => 'Someone Elses Favorite', 'steps' => ['x'], 'source' => 'seed']);
    Favorite::create(['recipe_id' => $recipe->id, 'favoritor_token' => (string) Str::uuid()]);

    $this->withUnencryptedCookie('favoritor_token', (string) Str::uuid());

    Livewire::test(RecipeList::class)
        ->set('onlyFavorites', true)
        ->assertDontSee('Someone Elses Favorite');
});

it('defaults the favorites page to favorites-only without exposing the toggle', function () {
    $favorited = Recipe::create(['name' => 'Favorited Recipe', 'steps' => ['x'], 'source' => 'seed']);
    $other = Recipe::create(['name' => 'Other Recipe', 'steps' => ['x'], 'source' => 'seed']);
    $token = (string) Str::uuid();
    Favorite::create(['recipe_id' => $favorited->id, 'favoritor_token' => $token]);

    Livewire::withCookie('favoritor_token', $token);

    Livewire::test(FavoritesList::class)
        ->assertSee('Favorited Recipe')
        ->assertDontSee('Other Recipe')
        ->assertDontSee('Hanya favorit');
});
