<?php

use App\Livewire\RecipeFinder;
use App\Services\Gemini\GeminiRecipeClient;
use Livewire\Livewire;
use Mockery;

it('explores with AI, imports recipes, and shows them', function () {
    $fake = Mockery::mock(GeminiRecipeClient::class);
    $fake->shouldReceive('suggest')->once()->andReturn([
        ['name' => 'AI Toast', 'ingredients' => ['bread', 'butter'], 'steps' => ['Toast it.'], 'servings' => 1],
    ]);
    app()->instance(GeminiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread', 'butter'])
        ->call('exploreWithAi')
        ->assertSet('aiError', null)
        ->assertSet('results.0.name', 'AI Toast');
});

it('reports a friendly error when AI fails', function () {
    $fake = Mockery::mock(GeminiRecipeClient::class);
    $fake->shouldReceive('suggest')->andThrow(new RuntimeException('boom'));
    app()->instance(GeminiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Gagal mengambil resep AI. Coba lagi nanti.');
});
