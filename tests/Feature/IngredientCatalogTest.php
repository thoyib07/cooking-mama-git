<?php

use App\Models\Ingredient;
use App\Support\IngredientCatalog;

it('recognizes ingredients already in the database', function () {
    Ingredient::create(['name' => 'daun kunyit muda']);

    expect(IngredientCatalog::isKnown('daun kunyit muda'))->toBeTrue();
});

it('recognizes ingredients from the curated common list', function () {
    expect(IngredientCatalog::isKnown('bawang merah'))->toBeTrue();
    expect(IngredientCatalog::isKnown('kiwi'))->toBeFalse();
});

it('tolerates small typos against the common list', function () {
    expect(IngredientCatalog::isKnown('bawang mera'))->toBeTrue(); // 1-char typo (missing "h")
});

it('rejects unknown gibberish', function () {
    expect(IngredientCatalog::isKnown('asdrtey12hdj'))->toBeFalse();
});
