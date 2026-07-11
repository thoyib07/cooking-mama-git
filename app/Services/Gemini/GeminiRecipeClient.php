<?php

namespace App\Services\Gemini;

use App\Services\Ai\AiRecipeClient;
use App\Services\Ai\RecipePrompt;
use App\Support\IngredientNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiRecipeClient implements AiRecipeClient
{
    public function __construct(private GeminiResponseParser $parser = new GeminiResponseParser) {}

    public function suggest(array $ingredientNames): array
    {
        $key = 'gemini:v2:'.md5(collect($ingredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->sort()->implode('|'));

        return Cache::remember($key, now()->addHours(6), function () use ($ingredientNames) {
            return $this->callGemini($ingredientNames);
        });
    }

    private function callGemini(array $ingredientNames): array
    {
        $endpoint = config('services.gemini.endpoint');
        $key = config('services.gemini.key');
        if (! $endpoint || ! $key) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $key])
            ->post($endpoint, [
                'contents' => [['parts' => [['text' => RecipePrompt::build($ingredientNames)]]]],
                'tools' => [['google_search' => new \stdClass]],
            ]);

        if ($response->failed()) {
            \Log::error('Gemini failed', ['status' => $response->status(), 'body' => $response->body()]);
            $msg = $response->status() === 429 ? 'Gemini rate limit reached. Try again in a moment.' : 'Gemini request failed: '.$response->status();
            throw new RuntimeException($msg);
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape.');
        }

        return $this->parser->parse($text);
    }
}
