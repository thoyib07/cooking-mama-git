<?php

use App\Services\Gemini\GeminiRecipeClient;
use Illuminate\Support\Facades\Http;

it('sends ingredients and returns parsed recipes', function () {
    config()->set('services.gemini.endpoint', 'https://gemini.test/generate');
    config()->set('services.gemini.key', 'test-key');

    $payloadText = json_encode([
        ['name' => 'AI Stew', 'ingredients' => ['carrot', 'water'], 'steps' => ['Cook.'], 'servings' => 3],
    ]);
    Http::fake([
        'gemini.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $payloadText]]]]],
        ], 200),
    ]);

    $out = (new GeminiRecipeClient())->suggest(['carrot', 'water']);
    expect($out[0]['name'])->toBe('AI Stew');
    Http::assertSent(fn ($req) => str_contains($req->body(), 'carrot'));
});
