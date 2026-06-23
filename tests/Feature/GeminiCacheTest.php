<?php

use App\Services\Gemini\GeminiRecipeClient;
use Illuminate\Support\Facades\Http;

it('caches identical ingredient queries', function () {
    config()->set('services.gemini.endpoint', 'https://gemini.test/generate');
    config()->set('services.gemini.key', 'k');

    $text = json_encode([['name' => 'Cached', 'ingredients' => ['a'], 'steps' => ['b']]]);
    Http::fake(['gemini.test/*' => Http::response(
        ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], 200
    )]);

    $client = new GeminiRecipeClient();
    $client->suggest(['a', 'b']);
    $client->suggest(['b', 'a']);

    Http::assertSentCount(1);
});
