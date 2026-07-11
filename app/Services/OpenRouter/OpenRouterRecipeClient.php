<?php

namespace App\Services\OpenRouter;

use App\Services\Ai\AiRecipeClient;
use App\Services\Ai\RecipePrompt;
use App\Services\Gemini\GeminiResponseParser;
use App\Support\IngredientNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterRecipeClient implements AiRecipeClient
{
    public function __construct(private GeminiResponseParser $parser = new GeminiResponseParser) {}

    public function suggest(array $ingredientNames): array
    {
        $key = 'openrouter:v1:'.md5(collect($ingredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->sort()->implode('|'));

        return Cache::remember($key, now()->addHours(6), function () use ($ingredientNames) {
            return $this->callOpenRouter($ingredientNames);
        });
    }

    private function callOpenRouter(array $ingredientNames): array
    {
        $endpoint = config('services.openrouter.endpoint');
        $key = config('services.openrouter.key');
        $model = config('services.openrouter.model');
        if (! $endpoint || ! $key) {
            throw new RuntimeException('OpenRouter is not configured.');
        }

        $response = Http::timeout(30)
            ->withToken($key)
            ->post($endpoint, [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => RecipePrompt::build($ingredientNames)]],
                'max_tokens' => 4000,
            ]);

        if ($response->failed()) {
            \Log::error('OpenRouter failed', ['status' => $response->status(), 'body' => $response->body()]);
            $msg = $response->status() === 429 ? 'OpenRouter rate limit reached. Try again in a moment.' : 'OpenRouter request failed: '.$response->status();
            throw new RuntimeException($msg);
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($text)) {
            throw new RuntimeException('Unexpected OpenRouter response shape.');
        }

        return $this->parser->parse($text);
    }
}
