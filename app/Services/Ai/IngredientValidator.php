<?php

namespace App\Services\Ai;

use App\Support\IngredientNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IngredientValidator
{
    /**
     * Last-resort AI check for ingredient names that pass neither the local
     * heuristic nor the whitelist. Tries Groq first (cheap/fast), falls back
     * to Gemini. Results are cached forever per normalized name so the same
     * word is never re-checked (across users, across time).
     *
     * If both providers are unreachable, defaults to plausible=true rather
     * than blocking the user — the subsequent AI recipe call will fail with
     * its own "AI unavailable" error anyway. This fail-open verdict is never
     * cached, so a transient outage doesn't permanently whitelist a word.
     */
    public function isPlausible(string $rawName): bool
    {
        $name = IngredientNormalizer::normalize($rawName);
        if ($name === '') {
            return false;
        }

        $cacheKey = 'ingredient-valid:v1:'.md5($name);
        $cached = Cache::get($cacheKey);
        if (is_bool($cached)) {
            return $cached;
        }

        $verdict = $this->askProviders($name);
        if ($verdict === null) {
            return true;
        }

        Cache::forever($cacheKey, $verdict);

        return $verdict;
    }

    private function askProviders(string $name): ?bool
    {
        try {
            return $this->askGroq($name);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            return $this->askGemini($name);
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    private function askGroq(string $name): bool
    {
        $endpoint = config('services.groq.endpoint');
        $key = config('services.groq.key');
        $model = config('services.groq.model');
        if (! $endpoint || ! $key) {
            throw new RuntimeException('Groq is not configured.');
        }

        $response = Http::timeout(10)
            ->withToken($key)
            ->post($endpoint, [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $this->prompt($name)]],
                'max_tokens' => 5,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq validator request failed: '.$response->status());
        }

        return $this->parseAnswer(data_get($response->json(), 'choices.0.message.content'));
    }

    private function askGemini(string $name): bool
    {
        $endpoint = config('services.gemini.endpoint');
        $key = config('services.gemini.key');
        if (! $endpoint || ! $key) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $response = Http::timeout(10)
            ->withQueryParameters(['key' => $key])
            ->post($endpoint, [
                'contents' => [['parts' => [['text' => $this->prompt($name)]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini validator request failed: '.$response->status());
        }

        return $this->parseAnswer(data_get($response->json(), 'candidates.0.content.parts.0.text'));
    }

    private function prompt(string $name): string
    {
        return "Apakah \"{$name}\" adalah nama bahan makanan/masakan yang masuk akal, dalam bahasa apa pun? ".
            'Balas HANYA dengan satu kata: YA atau TIDAK.';
    }

    private function parseAnswer(mixed $text): bool
    {
        if (! is_string($text)) {
            throw new RuntimeException('Unexpected validator response shape.');
        }

        return (bool) preg_match('/\bYA\b/i', $text) && ! preg_match('/\bTIDAK\b/i', $text);
    }
}
