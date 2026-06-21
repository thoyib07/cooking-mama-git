<?php

namespace App\Services\Gemini;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiRecipeClient
{
    public function __construct(private GeminiResponseParser $parser = new GeminiResponseParser()) {}

    public function suggest(array $ingredientNames): array
    {
        $key = 'gemini:' . md5(collect($ingredientNames)
            ->map(fn ($n) => \App\Support\IngredientNormalizer::normalize($n))
            ->sort()->implode('|'));

        return \Illuminate\Support\Facades\Cache::remember($key, now()->addHours(6), function () use ($ingredientNames) {
            return $this->callGemini($ingredientNames);
        });
    }

    private function callGemini(array $ingredientNames): array
    {
        $endpoint = config('services.gemini.endpoint');
        $key = config('services.gemini.key');
        if (!$endpoint || !$key) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $list = implode(', ', $ingredientNames);
        $prompt = "Beri 3 ide resep memakai sebagian besar bahan ini: {$list}. "
            . "Balas HANYA JSON array. Tiap item: {name, ingredients (array string), instructions, servings (number)}.";

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $key])
            ->post($endpoint, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: ' . $response->status());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape.');
        }

        return $this->parser->parse($text);
    }
}
