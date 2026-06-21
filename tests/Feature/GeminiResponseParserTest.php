<?php

use App\Services\Gemini\GeminiResponseParser;

it('parses a JSON array of recipes', function () {
    $json = json_encode([
        ['name' => 'Soup', 'ingredients' => ['Water', 'Salt'], 'instructions' => 'Boil.', 'servings' => 2],
    ]);
    $out = (new GeminiResponseParser())->parse($json);
    expect($out[0]['name'])->toBe('Soup');
    expect($out[0]['ingredients'])->toBe(['Water', 'Salt']);
});

it('strips code fences before parsing', function () {
    $raw = "```json\n[{\"name\":\"X\",\"ingredients\":[\"a\"],\"instructions\":\"y\"}]\n```";
    $out = (new GeminiResponseParser())->parse($raw);
    expect($out[0]['name'])->toBe('X');
});

it('throws on garbage', function () {
    (new GeminiResponseParser())->parse('not json');
})->throws(InvalidArgumentException::class);
