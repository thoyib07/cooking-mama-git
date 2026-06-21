<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Telur Dadar', 'Kocok telur, beri garam, goreng.', 2, ['telur', 'garam', 'minyak']],
            ['Nasi Goreng', 'Tumis bumbu, masukkan nasi dan kecap.', 2, ['nasi', 'bawang putih', 'kecap', 'telur', 'minyak']],
            ['Tumis Kangkung', 'Tumis bawang, masukkan kangkung.', 3, ['kangkung', 'bawang putih', 'garam', 'minyak']],
            ['Sup Ayam', 'Rebus ayam dengan sayur dan bumbu.', 4, ['ayam', 'wortel', 'bawang putih', 'garam']],
            ['Mie Rebus', 'Rebus mie, tambahkan bumbu dan telur.', 1, ['mie', 'telur', 'bawang putih', 'kecap']],
        ];

        foreach ($data as [$name, $instructions, $servings, $ingredients]) {
            $recipe = Recipe::create([
                'name' => $name,
                'instructions' => $instructions,
                'servings' => $servings,
                'source' => Recipe::SOURCE_SEED,
            ]);
            foreach ($ingredients as $ing) {
                $recipe->ingredients()->attach(Ingredient::findOrCreateNormalized($ing));
            }
        }
    }
}
