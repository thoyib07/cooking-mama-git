<?php

use App\Models\Recipe;
use Database\Seeders\RecipeSeeder;

it('seeds recipes with ingredients', function () {
    (new RecipeSeeder())->run();
    expect(Recipe::count())->toBeGreaterThanOrEqual(5);
    expect(Recipe::has('ingredients')->count())->toBe(Recipe::count());
});
