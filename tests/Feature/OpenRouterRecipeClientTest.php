<?php

use App\Services\OpenRouter\OpenRouterRecipeClient;
use Illuminate\Support\Facades\Http;

it('sends ingredients and returns parsed recipes', function () {
    config()->set('services.openrouter.endpoint', 'https://openrouter.test/chat/completions');
    config()->set('services.openrouter.key', 'test-key');
    config()->set('services.openrouter.model', 'deepseek/deepseek-r1:free');

    $payloadText = json_encode([
        ['name' => 'AI Stew', 'ingredients' => ['carrot', 'water'], 'steps' => ['Cook.'], 'servings' => 3],
    ]);
    Http::fake([
        'openrouter.test/*' => Http::response([
            'choices' => [['message' => ['content' => $payloadText]]],
        ], 200),
    ]);

    $out = (new OpenRouterRecipeClient())->suggest(['carrot', 'water']);
    expect($out[0]['name'])->toBe('AI Stew');
    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer test-key')
        && data_get($req->data(), 'model') === 'deepseek/deepseek-r1:free');
});

it('caches identical ingredient queries', function () {
    config()->set('services.openrouter.endpoint', 'https://openrouter.test/chat/completions');
    config()->set('services.openrouter.key', 'test-key');

    $text = json_encode([['name' => 'Cached', 'ingredients' => ['a'], 'steps' => ['b']]]);
    Http::fake(['openrouter.test/*' => Http::response(
        ['choices' => [['message' => ['content' => $text]]]], 200
    )]);

    $client = new OpenRouterRecipeClient();
    $client->suggest(['a', 'b']);
    $client->suggest(['b', 'a']);

    Http::assertSentCount(1);
});

it('throws a rate-limit message on 429', function () {
    config()->set('services.openrouter.endpoint', 'https://openrouter.test/chat/completions');
    config()->set('services.openrouter.key', 'test-key');

    Http::fake(['openrouter.test/*' => Http::response([], 429)]);

    (new OpenRouterRecipeClient())->suggest(['carrot']);
})->throws(RuntimeException::class, 'OpenRouter rate limit reached. Try again in a moment.');
