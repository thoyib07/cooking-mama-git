<?php

namespace App\Support;

class IngredientNormalizer
{
    public static function normalize(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));
        $noPunct = trim($lower, " \t\n\r\0\x0B.,;:");
        return preg_replace('/\s+/', ' ', $noPunct);
    }
}
