<?php

use App\Livewire\RecipeFinder;
use App\Services\Ai\AiRecipeClient;
use App\Services\Ai\IngredientValidator;
use Livewire\Livewire;
use Mockery;

it('explores with AI, imports recipes, and shows them', function () {
    $fake = Mockery::mock(AiRecipeClient::class);
    $fake->shouldReceive('suggest')->once()->andReturn([
        ['name' => 'AI Toast', 'ingredients' => ['bread', 'butter'], 'steps' => ['Toast it.'], 'servings' => 1],
    ]);
    app()->instance(AiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread', 'butter'])
        ->call('exploreWithAi')
        ->assertSet('aiError', null)
        ->assertSet('results.0.name', 'AI Toast');
});

it('reports a friendly error when AI fails', function () {
    $fake = Mockery::mock(AiRecipeClient::class);
    $fake->shouldReceive('suggest')->andThrow(new RuntimeException('boom'));
    app()->instance(AiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Gagal mengambil resep AI. Coba lagi nanti.');
});

it('filters out ingredients that fail AI validation before exploring', function () {
    $fakeValidator = Mockery::mock(IngredientValidator::class);
    $fakeValidator->shouldReceive('isPlausible')->with('bread')->andReturn(true);
    $fakeValidator->shouldReceive('isPlausible')->with('asdrtey12hdj')->andReturn(false);
    app()->instance(IngredientValidator::class, $fakeValidator);

    $fake = Mockery::mock(AiRecipeClient::class);
    $fake->shouldReceive('suggest')->once()->with(['bread'])->andReturn([
        ['name' => 'AI Toast', 'ingredients' => ['bread'], 'steps' => ['Toast it.'], 'servings' => 1],
    ]);
    app()->instance(AiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread', 'asdrtey12hdj'])
        ->call('exploreWithAi')
        ->assertSet('aiError', null)
        ->assertSet('aiNotice', 'Bahan berikut diabaikan karena tidak dikenali AI: asdrtey12hdj.')
        ->assertSet('results.0.name', 'AI Toast');
});

it('does not call the AI validator for ingredients already known in the whitelist', function () {
    $fakeValidator = Mockery::mock(IngredientValidator::class);
    $fakeValidator->shouldReceive('isPlausible')->once()->with('printer')->andReturn(false);
    app()->instance(IngredientValidator::class, $fakeValidator);

    $fake = Mockery::mock(AiRecipeClient::class);
    $fake->shouldReceive('suggest')->once()->with(['telur'])->andReturn([
        ['name' => 'Telur Dadar', 'ingredients' => ['telur'], 'steps' => ['Goreng.'], 'servings' => 1],
    ]);
    app()->instance(AiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['telur', 'printer'])
        ->call('exploreWithAi')
        ->assertSet('aiError', null)
        ->assertSet('results.0.name', 'Telur Dadar');
});

it('shows an error when no ingredients are AI-plausible', function () {
    $fakeValidator = Mockery::mock(IngredientValidator::class);
    $fakeValidator->shouldReceive('isPlausible')->andReturn(false);
    app()->instance(IngredientValidator::class, $fakeValidator);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['asdrtey12hdj'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Tidak ada bahan yang dikenali untuk dicari dengan AI.');
});
