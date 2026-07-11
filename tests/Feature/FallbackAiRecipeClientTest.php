<?php

use App\Services\Ai\AiRecipeClient;
use App\Services\Ai\FallbackAiRecipeClient;

it('returns the primary result when it succeeds', function () {
    $primary = Mockery::mock(AiRecipeClient::class);
    $primary->shouldReceive('suggest')->once()->andReturn([['name' => 'Primary']]);
    $fallback = Mockery::mock(AiRecipeClient::class);
    $fallback->shouldNotReceive('suggest');

    $client = new FallbackAiRecipeClient($primary, $fallback);

    expect($client->suggest(['carrot']))->toBe([['name' => 'Primary']]);
});

it('falls back when the primary provider throws', function () {
    $primary = Mockery::mock(AiRecipeClient::class);
    $primary->shouldReceive('suggest')->once()->andThrow(new RuntimeException('rate limited'));
    $fallback = Mockery::mock(AiRecipeClient::class);
    $fallback->shouldReceive('suggest')->once()->andReturn([['name' => 'Fallback']]);

    $client = new FallbackAiRecipeClient($primary, $fallback);

    expect($client->suggest(['carrot']))->toBe([['name' => 'Fallback']]);
});

it('lets the fallback error propagate when both providers fail', function () {
    $primary = Mockery::mock(AiRecipeClient::class);
    $primary->shouldReceive('suggest')->once()->andThrow(new RuntimeException('primary down'));
    $fallback = Mockery::mock(AiRecipeClient::class);
    $fallback->shouldReceive('suggest')->once()->andThrow(new RuntimeException('fallback down'));

    $client = new FallbackAiRecipeClient($primary, $fallback);

    $client->suggest(['carrot']);
})->throws(RuntimeException::class, 'fallback down');
