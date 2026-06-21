<?php

namespace App\Livewire;

use App\Services\Gemini\AiRecipeImporter;
use App\Services\Gemini\GeminiRecipeClient;
use App\Services\Matching\RecipeMatcher;
use App\Support\IngredientNormalizer;
use Livewire\Component;

class RecipeFinder extends Component
{
    public array $ingredients = [];
    public string $newIngredient = '';
    public array $results = [];
    public bool $searched = false;
    public bool $aiLoading = false;
    public ?string $aiError = null;

    public function addIngredient(): void
    {
        $name = IngredientNormalizer::normalize($this->newIngredient);
        if ($name !== '' && !in_array($name, $this->ingredients, true)) {
            $this->ingredients[] = $name;
        }
        $this->newIngredient = '';
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function search(RecipeMatcher $matcher): void
    {
        $this->searched = true;
        $this->results = collect($matcher->search($this->ingredients))
            ->map(fn ($m) => [
                'id' => $m->recipe->id,
                'name' => $m->recipe->name,
                'score' => $m->score,
                'matched' => $m->matched,
                'missing' => $m->missing,
            ])->all();
    }

    public function exploreWithAi(
        GeminiRecipeClient $client,
        AiRecipeImporter $importer,
        RecipeMatcher $matcher
    ): void {
        $this->aiError = null;
        $this->aiLoading = true;
        try {
            $suggestions = $client->suggest($this->ingredients);
            $importer->importMany($suggestions);
            $this->search($matcher);
        } catch (\Throwable $e) {
            report($e);
            $this->aiError = 'Gagal mengambil resep AI. Coba lagi nanti.';
        } finally {
            $this->aiLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.recipe-finder');
    }
}
