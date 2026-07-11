<?php

use App\Support\IngredientNormalizer;

it('normalizes casing and whitespace', function () {
    expect(IngredientNormalizer::normalize('  Tomato '))->toBe('tomato');
    expect(IngredientNormalizer::normalize('Red   Onion'))->toBe('red onion');
    expect(IngredientNormalizer::normalize('GARLIC,'))->toBe('garlic');
});

it('strips trailing spec/quantity qualifiers to keep names generic', function () {
    expect(IngredientNormalizer::normalize('Alkohol 30%'))->toBe('alkohol');
    expect(IngredientNormalizer::normalize('Tepung Tinggi Protein 30%'))->toBe('tepung tinggi protein');
    expect(IngredientNormalizer::normalize('Susu 3.5%'))->toBe('susu');
    expect(IngredientNormalizer::normalize('Santan 65'))->toBe('santan');
});
