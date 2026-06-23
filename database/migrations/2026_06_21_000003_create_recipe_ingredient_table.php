<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredient', function (Blueprint $t) {
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $t->string('quantity')->nullable();
            $t->primary(['recipe_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredient');
    }
};
