<?php

use App\Livewire\RecipeList;
use App\Models\Recipe;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));

Route::get('/manifest.json', fn () => response(file_get_contents(public_path('manifest.json')), 200, ['Content-Type' => 'application/json']));
Route::get('/sw.js', fn () => response(file_get_contents(public_path('sw.js')), 200, ['Content-Type' => 'application/javascript; charset=UTF-8']));

Route::get('/recipes', RecipeList::class)->name('recipes.index');

Route::get('/recipes/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');
