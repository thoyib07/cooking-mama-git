<?php

namespace App\Livewire;

use App\Models\Rating;
use App\Models\Recipe;
use Livewire\Component;

class RecipeRating extends Component
{
    public Recipe $recipe;
    public float $average = 0.0;
    public int $count = 0;
    public bool $hasRated = false;

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe;
        $this->refreshStats();
        $this->hasRated = Rating::where('recipe_id', $recipe->id)
            ->where('session_token', session()->getId())->exists();
    }

    public function rate(int $value): void
    {
        $value = max(1, min(5, $value));
        if ($this->hasRated) {
            return;
        }
        Rating::create([
            'recipe_id' => $this->recipe->id,
            'value' => $value,
            'session_token' => session()->getId(),
        ]);
        $this->hasRated = true;
        $this->refreshStats();
    }

    private function refreshStats(): void
    {
        $ratings = Rating::where('recipe_id', $this->recipe->id);
        $this->count = (clone $ratings)->count();
        $this->average = round((float) (clone $ratings)->avg('value'), 1);
    }

    public function render()
    {
        return view('livewire.recipe-rating');
    }
}
