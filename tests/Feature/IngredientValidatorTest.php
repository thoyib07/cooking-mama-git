<?php

use App\Services\Ai\IngredientValidator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.groq.endpoint', 'https://groq.test/chat/completions');
    config()->set('services.groq.key', 'test-key');
    config()->set('services.groq.model', 'llama-3.3-70b-versatile');

    // Blank by default so tests never fall through to the real Gemini
    // endpoint/key from a developer's local .env. Tests that exercise the
    // Gemini fallback override these explicitly with a fake endpoint.
    config()->set('services.gemini.endpoint', null);
    config()->set('services.gemini.key', null);
});

it('accepts a plausible ingredient answered by groq', function () {
    Http::fake([
        'groq.test/*' => Http::response(['choices' => [['message' => ['content' => 'YA']]]], 200),
    ]);

    expect((new IngredientValidator)->isPlausible('telur'))->toBeTrue();
});

it('rejects an implausible ingredient answered by groq', function () {
    Http::fake([
        'groq.test/*' => Http::response(['choices' => [['message' => ['content' => 'TIDAK']]]], 200),
    ]);

    expect((new IngredientValidator)->isPlausible('asdrtey12hdj'))->toBeFalse();
});

it('caches the verdict so the same ingredient is never re-checked', function () {
    Http::fake([
        'groq.test/*' => Http::response(['choices' => [['message' => ['content' => 'YA']]]], 200),
    ]);

    $validator = new IngredientValidator;
    $validator->isPlausible('telur');
    $validator->isPlausible('Telur '); // same after normalization

    Http::assertSentCount(1);
});

it('falls back to gemini when groq is unreachable', function () {
    config()->set('services.gemini.endpoint', 'https://gemini.test/generate');
    config()->set('services.gemini.key', 'test-key');

    Http::fake([
        'groq.test/*' => Http::response([], 500),
        'gemini.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'YA']]]]],
        ], 200),
    ]);

    expect((new IngredientValidator)->isPlausible('telur'))->toBeTrue();
});

it('defaults to plausible when both providers are unreachable', function () {
    Http::fake([
        'groq.test/*' => Http::response([], 500),
    ]);

    expect((new IngredientValidator)->isPlausible('telur'))->toBeTrue();
});

it('does not cache a fail-open verdict so a later outage recovery re-checks it', function () {
    Http::fake([
        'groq.test/*' => Http::response([], 500),
    ]);

    $validator = new IngredientValidator;
    $validator->isPlausible('telur');
    $validator->isPlausible('telur');

    Http::assertSentCount(2);
});

it('returns false for an empty ingredient without calling AI', function () {
    Http::fake();

    expect((new IngredientValidator)->isPlausible('   '))->toBeFalse();
    Http::assertNothingSent();
});
