<?php

namespace App\Providers;

use App\Services\Ai\AiRecipeClient;
use App\Services\Ai\FallbackAiRecipeClient;
use App\Services\Gemini\GeminiRecipeClient;
use App\Services\Groq\GroqRecipeClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiRecipeClient::class, fn ($app) => new FallbackAiRecipeClient(
            $app->make(GeminiRecipeClient::class),
            $app->make(GroqRecipeClient::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');
    }
}
