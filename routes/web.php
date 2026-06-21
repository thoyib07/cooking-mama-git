<?php

use App\Models\Recipe;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'));

Route::get('/recipes/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');
