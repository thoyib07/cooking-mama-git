<?php

use App\Support\RecipeSteps;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $t) {
            $t->json('steps')->nullable()->after('name');
        });

        foreach (DB::table('recipes')->select('id', 'instructions')->get() as $row) {
            DB::table('recipes')->where('id', $row->id)
                ->update(['steps' => json_encode(RecipeSteps::normalize($row->instructions))]);
        }

        Schema::table('recipes', function (Blueprint $t) {
            $t->dropColumn('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $t) {
            $t->text('instructions')->default('');
        });

        foreach (DB::table('recipes')->select('id', 'steps')->get() as $row) {
            $steps = json_decode($row->steps ?? '[]', true) ?: [];
            DB::table('recipes')->where('id', $row->id)
                ->update(['instructions' => implode("\n", $steps)]);
        }

        Schema::table('recipes', function (Blueprint $t) {
            $t->dropColumn('steps');
        });
    }
};
