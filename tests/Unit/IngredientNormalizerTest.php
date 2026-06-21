<?php

use App\Support\IngredientNormalizer;

it('normalizes casing and whitespace', function () {
    expect(IngredientNormalizer::normalize('  Tomato '))->toBe('tomato');
    expect(IngredientNormalizer::normalize('Red   Onion'))->toBe('red onion');
    expect(IngredientNormalizer::normalize('GARLIC,'))->toBe('garlic');
});
