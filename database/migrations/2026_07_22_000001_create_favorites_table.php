<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->string('favoritor_token');
            $t->timestamps();

            $t->unique(['favoritor_token', 'recipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
