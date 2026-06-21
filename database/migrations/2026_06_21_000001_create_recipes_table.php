<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('instructions');
            $t->string('image_url')->nullable();
            $t->string('source')->default('seed');
            $t->unsignedSmallInteger('servings')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
