<?php

use App\Services\Groq\GroqRecipeClient;
use Illuminate\Support\Facades\Http;

it('sends ingredients and returns parsed recipes', function () {
    config()->set('services.groq.endpoint', 'https://groq.test/chat/completions');
    config()->set('services.groq.key', 'test-key');
    config()->set('services.groq.model', 'llama-3.3-70b-versatile');

    $payloadText = json_encode([
        ['name' => 'AI Stew', 'ingredients' => ['carrot', 'water'], 'steps' => ['Cook.'], 'servings' => 3],
    ]);
    Http::fake([
        'groq.test/*' => Http::response([
            'choices' => [['message' => ['content' => $payloadText]]],
        ], 200),
    ]);

    $out = (new GroqRecipeClient())->suggest(['carrot', 'water']);
    expect($out[0]['name'])->toBe('AI Stew');
    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Bearer test-key')
        && data_get($req->data(), 'model') === 'llama-3.3-70b-versatile');
});

it('caches identical ingredient queries', function () {
    config()->set('services.groq.endpoint', 'https://groq.test/chat/completions');
    config()->set('services.groq.key', 'test-key');

    $text = json_encode([['name' => 'Cached', 'ingredients' => ['a'], 'steps' => ['b']]]);
    Http::fake(['groq.test/*' => Http::response(
        ['choices' => [['message' => ['content' => $text]]]], 200
    )]);

    $client = new GroqRecipeClient();
    $client->suggest(['a', 'b']);
    $client->suggest(['b', 'a']);

    Http::assertSentCount(1);
});

it('throws a rate-limit message on 429', function () {
    config()->set('services.groq.endpoint', 'https://groq.test/chat/completions');
    config()->set('services.groq.key', 'test-key');

    Http::fake(['groq.test/*' => Http::response([], 429)]);

    (new GroqRecipeClient())->suggest(['carrot']);
})->throws(RuntimeException::class, 'Groq rate limit reached. Try again in a moment.');
