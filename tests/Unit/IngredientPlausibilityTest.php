<?php

use App\Support\IngredientPlausibility;

it('accepts common ingredient names', function (string $name) {
    expect(IngredientPlausibility::looksLikeWord($name))->toBeTrue();
})->with([
    'tomat', 'bawang merah', 'daging sapi', 'kecap manis', 'telur', 'cabai rawit', 'gula', 'es',
]);

it('rejects keyboard-mash gibberish', function (string $name) {
    expect(IngredientPlausibility::looksLikeWord($name))->toBeFalse();
})->with([
    'asdrtey12hdj', 'xzvbnmqwrt', 'qwrtplkjh', 'bcdfghjklm',
]);

it('rejects strings with digits', function () {
    expect(IngredientPlausibility::looksLikeWord('tomat123'))->toBeFalse();
});

it('rejects empty or overly short/long strings', function () {
    expect(IngredientPlausibility::looksLikeWord(''))->toBeFalse();
    expect(IngredientPlausibility::looksLikeWord('a'))->toBeFalse();
    expect(IngredientPlausibility::looksLikeWord(str_repeat('a', 41)))->toBeFalse();
});

it('rejects words with no vowels', function () {
    expect(IngredientPlausibility::looksLikeWord('msg'))->toBeFalse();
});
