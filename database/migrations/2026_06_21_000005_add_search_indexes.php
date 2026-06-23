<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredient', function (Blueprint $t) {
            $t->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredient', function (Blueprint $t) {
            $t->dropIndex(['ingredient_id']);
        });
    }
};
