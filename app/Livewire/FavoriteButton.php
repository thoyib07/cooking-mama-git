<?php

namespace App\Livewire;

use App\Models\Favorite;
use App\Support\FavoritorToken;
use Livewire\Component;

class FavoriteButton extends Component
{
    public int $recipeId;

    public bool $isFavorited = false;

    public function mount(int $recipeId): void
    {
        $this->recipeId = $recipeId;
        $this->refreshState();
    }

    public function toggle(): void
    {
        $token = FavoritorToken::get();
        $existing = Favorite::where('recipe_id', $this->recipeId)
            ->where('favoritor_token', $token)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            Favorite::create(['recipe_id' => $this->recipeId, 'favoritor_token' => $token]);
        }

        $this->refreshState();
    }

    private function refreshState(): void
    {
        $this->isFavorited = Favorite::where('recipe_id', $this->recipeId)
            ->where('favoritor_token', FavoritorToken::get())
            ->exists();
    }

    public function render()
    {
        return view('livewire.favorite-button');
    }
}
