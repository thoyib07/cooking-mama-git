<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class FavoritorToken
{
    private const COOKIE_NAME = 'favoritor_token';

    private const LIFETIME_MINUTES = 60 * 24 * 365;

    /**
     * Anonymous per-device identity for favorites, backed by a long-lived
     * cookie rather than the Laravel session (SESSION_LIFETIME is only
     * 120 minutes, too short for something meant to be saved for later).
     */
    public static function get(): string
    {
        $token = request()->cookie(self::COOKIE_NAME);

        if (! $token) {
            $token = (string) Str::uuid();
            Cookie::queue(self::COOKIE_NAME, $token, self::LIFETIME_MINUTES);
        }

        return $token;
    }
}
