<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    public const SOURCE_SEED = 'seed';
    public const SOURCE_AI = 'ai';

    protected $fillable = ['name', 'instructions', 'image_url', 'source', 'servings'];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient')->withPivot('quantity');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
