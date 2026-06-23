<?php

use App\Support\RecipeSteps;

it('passes arrays through, trimming and dropping empties', function () {
    expect(RecipeSteps::normalize(['  Mix ', '', 'Bake']))->toBe(['Mix', 'Bake']);
});

it('splits a multi-line string into one step per line', function () {
    expect(RecipeSteps::normalize("Mix\nBake\n\nServe"))->toBe(['Mix', 'Bake', 'Serve']);
});

it('falls back to sentence split for a single-line blob', function () {
    expect(RecipeSteps::normalize('Mix it. Bake it.'))->toBe(['Mix it.', 'Bake it.']);
});

it('strips leading numbering', function () {
    expect(RecipeSteps::normalize("1. Mix\n2) Bake"))->toBe(['Mix', 'Bake']);
});

it('returns an empty list for non-string non-array', function () {
    expect(RecipeSteps::normalize(null))->toBe([]);
});
