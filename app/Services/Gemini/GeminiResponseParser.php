<?php

namespace App\Services\Gemini;

use InvalidArgumentException;

class GeminiResponseParser
{
    public function parse(string $rawText): array
    {
        $clean = trim($rawText);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Gemini response is not valid JSON.');
        }

        $recipes = array_is_list($data) ? $data : ($data['recipes'] ?? null);
        if (!is_array($recipes)) {
            throw new InvalidArgumentException('No recipe list found in response.');
        }

        return array_map(function ($r) {
            if (!isset($r['name'], $r['ingredients'], $r['instructions']) || !is_array($r['ingredients'])) {
                throw new InvalidArgumentException('Recipe entry missing required fields.');
            }
            return [
                'name' => (string) $r['name'],
                'ingredients' => array_values(array_map('strval', $r['ingredients'])),
                'instructions' => (string) $r['instructions'],
                'servings' => isset($r['servings']) ? (int) $r['servings'] : null,
            ];
        }, $recipes);
    }
}
