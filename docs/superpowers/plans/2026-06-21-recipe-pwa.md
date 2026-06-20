# Recipe Recommender PWA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PWA that recommends recipes from available ingredients via a local Postgres match-score search, with on-demand Gemini AI exploration whose results are saved back to the database.

**Architecture:** Laravel monolith serving Blade + Livewire UI, with a Filament admin panel for recipe management. Ingredient matching runs as a server-side scoring service over a normalized `recipe_ingredient` pivot. A "Explore with AI" action calls Gemini, parses structured JSON, and imports recipes back into the same tables so they become searchable. PWA layer adds an app-shell service worker + manifest. Deployed to a free-tier PaaS with managed Neon Postgres.

**Tech Stack:** Laravel 11, PHP 8.2+, PostgreSQL (Neon), Livewire 3, Filament 3, Pest (testing), Gemini API (current model), vanilla service worker.

## Global Constraints

- Backend is **Laravel (PHP)** — never serverless functions.
- Database is **PostgreSQL**; all migrations/queries must be Postgres-compatible.
- End-users are **anonymous** (no login/registration). Only the Filament **admin** authenticates (single account).
- Gemini API keys and DB credentials live in `.env` / PaaS secrets **only** — never in frontend code.
- Ingredient names are stored **normalized** (lowercase, trimmed, single-spaced) and are **unique**.
- AI-saved recipes use `source = 'ai'` and may have `image_url = null` → UI must use a placeholder.
- Gemini is called **only on explicit user action**, never automatically.
- TDD: write the failing test first; commit after each green task.
- Tests run with: `php artisan test` (Pest).

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Support/IngredientNormalizer.php` | Pure string normalization for ingredient names |
| `app/Models/Recipe.php` | Recipe model + relations |
| `app/Models/Ingredient.php` | Ingredient model + normalized creation helper |
| `app/Models/Rating.php` | Anonymous rating model |
| `app/Services/Matching/MatchResult.php` | Value object: recipe + score + matched/missing |
| `app/Services/Matching/RecipeMatcher.php` | Core match-score search over the pivot |
| `app/Services/Gemini/GeminiRecipeClient.php` | HTTP call to Gemini, returns raw recipe arrays |
| `app/Services/Gemini/GeminiResponseParser.php` | Parse/validate Gemini JSON into recipe arrays |
| `app/Services/Gemini/AiRecipeImporter.php` | Persist AI recipe arrays into DB with dedup |
| `app/Livewire/RecipeFinder.php` | Main UI: ingredient input, search, AI trigger |
| `app/Filament/Resources/RecipeResource.php` | Admin CRUD for recipes |
| `resources/views/livewire/recipe-finder.blade.php` | Finder UI markup |
| `resources/views/recipes/show.blade.php` | Recipe detail page (offline-cacheable) |
| `public/manifest.json` / `public/sw.js` | PWA manifest + service worker |
| `database/migrations/*` | Schema |
| `database/seeders/RecipeSeeder.php` | Initial recipes |
| `tests/**` | Pest tests per task |

---

# PHASE 1 — Database Foundation & Core UI

### Task 1: Project initialization & Postgres connection

**Files:**
- Create: project skeleton (Laravel), `.env`, `.env.example`
- Modify: `config/database.php` (default connection), `phpunit.xml`

**Interfaces:**
- Produces: a bootable Laravel app with a working `pgsql` connection and a passing test suite baseline.

- [ ] **Step 1: Scaffold the Laravel app into the repo root**

Run (the repo already exists, so scaffold into a temp dir and move, preserving git):
```bash
composer create-project laravel/laravel temp-app
# move app contents into repo root (PowerShell-safe via git bash)
cp -r temp-app/. .
rm -rf temp-app
composer require livewire/livewire filament/filament
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
php artisan pest:install
```

- [ ] **Step 2: Configure Postgres + a separate test DB**

Edit `.env`:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=recipe_pwa
DB_USERNAME=postgres
DB_PASSWORD=postgres
```
In `phpunit.xml`, ensure tests use a dedicated DB (or sqlite-in-memory is NOT used — we need Postgres parity). Add inside `<php>`:
```xml
<env name="DB_DATABASE" value="recipe_pwa_test"/>
```
Create the test DB:
```bash
createdb recipe_pwa_test || true
```

- [ ] **Step 3: Add a smoke test**

Create `tests/Feature/SmokeTest.php`:
```php
<?php
it('boots the homepage', function () {
    $this->get('/')->assertStatus(200);
});
```

- [ ] **Step 4: Run it**

Run: `php artisan test --filter=SmokeTest`
Expected: PASS (1 passed).

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel app with Livewire, Filament, Pest, Postgres"
```

---

### Task 2: Ingredient normalizer

**Files:**
- Create: `app/Support/IngredientNormalizer.php`
- Test: `tests/Unit/IngredientNormalizerTest.php`

**Interfaces:**
- Produces: `IngredientNormalizer::normalize(string $raw): string` — lowercases, trims, collapses internal whitespace, strips surrounding punctuation. Used by Ingredient model, matcher, and AI importer.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Support\IngredientNormalizer;

it('normalizes casing and whitespace', function () {
    expect(IngredientNormalizer::normalize('  Tomato '))->toBe('tomato');
    expect(IngredientNormalizer::normalize('Red   Onion'))->toBe('red onion');
    expect(IngredientNormalizer::normalize('GARLIC,'))->toBe('garlic');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=IngredientNormalizerTest`
Expected: FAIL ("Class IngredientNormalizer not found").

- [ ] **Step 3: Implement**

```php
<?php
namespace App\Support;

class IngredientNormalizer
{
    public static function normalize(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));
        $noPunct = trim($lower, " \t\n\r\0\x0B.,;:");
        return preg_replace('/\s+/', ' ', $noPunct);
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=IngredientNormalizerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/IngredientNormalizer.php tests/Unit/IngredientNormalizerTest.php
git commit -m "feat: add ingredient name normalizer"
```

---

### Task 3: Schema & models

**Files:**
- Create: migrations for `recipes`, `ingredients`, `recipe_ingredient`, `ratings`
- Create: `app/Models/Recipe.php`, `app/Models/Ingredient.php`, `app/Models/Rating.php`
- Test: `tests/Feature/RecipeModelTest.php`

**Interfaces:**
- Consumes: `IngredientNormalizer::normalize()`.
- Produces:
  - `Recipe` fillable: `name, instructions, image_url, source, servings`; relation `ingredients()` (belongsToMany), `ratings()` (hasMany).
  - `Ingredient::findOrCreateNormalized(string $raw): Ingredient`.
  - `Recipe` constants `SOURCE_SEED = 'seed'`, `SOURCE_AI = 'ai'`.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Models\Recipe;
use App\Models\Ingredient;

it('attaches normalized ingredients to a recipe', function () {
    $recipe = Recipe::create([
        'name' => 'Omelette',
        'instructions' => 'Beat eggs, fry.',
        'source' => Recipe::SOURCE_SEED,
        'servings' => 2,
    ]);
    $egg = Ingredient::findOrCreateNormalized('  Eggs ');
    $recipe->ingredients()->attach($egg);

    expect($egg->name)->toBe('eggs');
    expect($recipe->ingredients)->toHaveCount(1);
    expect(Ingredient::findOrCreateNormalized('eggs')->id)->toBe($egg->id);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeModelTest`
Expected: FAIL (tables/classes missing).

- [ ] **Step 3: Create migrations**

`database/migrations/2026_06_21_000001_create_recipes_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
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
    public function down(): void { Schema::dropIfExists('recipes'); }
};
```

`..._000002_create_ingredients_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ingredients', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ingredients'); }
};
```

`..._000003_create_recipe_ingredient_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recipe_ingredient', function (Blueprint $t) {
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $t->string('quantity')->nullable();
            $t->primary(['recipe_id', 'ingredient_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('recipe_ingredient'); }
};
```

`..._000004_create_ratings_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('value'); // 1..5
            $t->string('session_token')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ratings'); }
};
```

- [ ] **Step 4: Create models**

`app/Models/Recipe.php`:
```php
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
        return $this->belongsToMany(Ingredient::class)->withPivot('quantity');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
```

`app/Models/Ingredient.php`:
```php
<?php
namespace App\Models;

use App\Support\IngredientNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    protected $fillable = ['name'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }

    public static function findOrCreateNormalized(string $raw): self
    {
        $name = IngredientNormalizer::normalize($raw);
        return static::firstOrCreate(['name' => $name]);
    }
}
```

`app/Models/Rating.php`:
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = ['recipe_id', 'value', 'session_token'];
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=RecipeModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models tests/Feature/RecipeModelTest.php
git commit -m "feat: add recipe/ingredient/rating schema and models"
```

---

### Task 4: Recipe seeder

**Files:**
- Create: `database/seeders/RecipeSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/RecipeSeederTest.php`

**Interfaces:**
- Consumes: `Recipe`, `Ingredient::findOrCreateNormalized()`.
- Produces: at least 5 seeded recipes with attached ingredients, usable by search tests.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Models\Recipe;
use Database\Seeders\RecipeSeeder;

it('seeds recipes with ingredients', function () {
    (new RecipeSeeder())->run();
    expect(Recipe::count())->toBeGreaterThanOrEqual(5);
    expect(Recipe::has('ingredients')->count())->toBe(Recipe::count());
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeSeederTest`
Expected: FAIL (seeder missing).

- [ ] **Step 3: Implement seeder**

`database/seeders/RecipeSeeder.php`:
```php
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
```

Modify `database/seeders/DatabaseSeeder.php` `run()` to call it:
```php
$this->call(RecipeSeeder::class);
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=RecipeSeederTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders tests/Feature/RecipeSeederTest.php
git commit -m "feat: add initial recipe seeder"
```

---

### Task 5: Match-score search service

**Files:**
- Create: `app/Services/Matching/MatchResult.php`, `app/Services/Matching/RecipeMatcher.php`
- Test: `tests/Feature/RecipeMatcherTest.php`

**Interfaces:**
- Consumes: `Recipe`, `Ingredient`, `IngredientNormalizer`.
- Produces:
  - `MatchResult` with readonly public props: `Recipe $recipe`, `float $score` (0..1), `array $matched` (string names), `array $missing` (string names).
  - `RecipeMatcher::search(array $rawIngredientNames, float $threshold = 0.5): array` → `MatchResult[]` ordered by `score` desc. Excludes recipes below threshold. A recipe's score = matchedCount / totalRecipeIngredients.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Models\Recipe;
use App\Models\Ingredient;
use App\Services\Matching\RecipeMatcher;

function makeRecipe(string $name, array $ings): Recipe {
    $r = Recipe::create(['name' => $name, 'instructions' => 'x', 'source' => Recipe::SOURCE_SEED]);
    foreach ($ings as $i) $r->ingredients()->attach(Ingredient::findOrCreateNormalized($i));
    return $r;
}

it('ranks recipes by ingredient match ratio and applies threshold', function () {
    makeRecipe('Full Match', ['egg', 'salt']);          // 2/2 = 1.0
    makeRecipe('Partial', ['egg', 'salt', 'flour', 'milk']); // 2/4 = 0.5
    makeRecipe('Too Low', ['beef', 'salt', 'pepper', 'oil']); // 1/4 = 0.25 -> excluded

    $results = (new RecipeMatcher())->search(['Egg', 'salt'], 0.5);

    expect($results)->toHaveCount(2);
    expect($results[0]->recipe->name)->toBe('Full Match');
    expect($results[0]->score)->toBe(1.0);
    expect($results[1]->recipe->name)->toBe('Partial');
    expect($results[1]->missing)->toContain('flour');
    expect($results[1]->matched)->toContain('egg');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeMatcherTest`
Expected: FAIL (classes missing).

- [ ] **Step 3: Implement MatchResult**

`app/Services/Matching/MatchResult.php`:
```php
<?php
namespace App\Services\Matching;

use App\Models\Recipe;

class MatchResult
{
    public function __construct(
        public readonly Recipe $recipe,
        public readonly float $score,
        public readonly array $matched,
        public readonly array $missing,
    ) {}
}
```

- [ ] **Step 4: Implement RecipeMatcher**

`app/Services/Matching/RecipeMatcher.php`:
```php
<?php
namespace App\Services\Matching;

use App\Models\Recipe;
use App\Support\IngredientNormalizer;

class RecipeMatcher
{
    public function search(array $rawIngredientNames, float $threshold = 0.5): array
    {
        $have = collect($rawIngredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->filter()
            ->unique()
            ->values();

        if ($have->isEmpty()) {
            return [];
        }

        $results = [];
        $recipes = Recipe::with('ingredients')->has('ingredients')->get();

        foreach ($recipes as $recipe) {
            $names = $recipe->ingredients->pluck('name');
            $total = $names->count();
            $matched = $names->filter(fn ($n) => $have->contains($n))->values();
            $missing = $names->reject(fn ($n) => $have->contains($n))->values();
            $score = $total > 0 ? round($matched->count() / $total, 4) : 0.0;

            if ($score >= $threshold) {
                $results[] = new MatchResult($recipe, $score, $matched->all(), $missing->all());
            }
        }

        usort($results, fn ($a, $b) => $b->score <=> $a->score);
        return $results;
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=RecipeMatcherTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Matching tests/Feature/RecipeMatcherTest.php
git commit -m "feat: add match-score recipe search service"
```

---

### Task 6: Filament admin for recipes

**Files:**
- Create: Filament panel + `app/Filament/Resources/RecipeResource.php` (+ generated pages)
- Create: an admin user via seeder `database/seeders/AdminSeeder.php`
- Test: `tests/Feature/FilamentAdminTest.php`

**Interfaces:**
- Consumes: `Recipe`.
- Produces: an authenticated `/admin` panel where the admin can CRUD recipes (name, instructions, image upload, servings, ingredients).

- [ ] **Step 1: Install the panel**

Run:
```bash
php artisan filament:install --panels
php artisan make:filament-resource Recipe --generate
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/FilamentAdminTest.php`:
```php
<?php
use App\Models\User;

it('blocks guests from the admin panel', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets an admin view the recipe list', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin)->get('/admin/recipes')->assertStatus(200);
});
```

- [ ] **Step 3: Run to verify it fails (or guides config)**

Run: `php artisan test --filter=FilamentAdminTest`
Expected: FAIL until the panel route + resource exist.

- [ ] **Step 4: Configure the Recipe form/table**

Edit `app/Filament/Resources/RecipeResource.php` form schema to include:
```php
use Filament\Forms;
// inside form(Form $form):
return $form->schema([
    Forms\Components\TextInput::make('name')->required(),
    Forms\Components\Textarea::make('instructions')->required()->rows(8),
    Forms\Components\FileUpload::make('image_url')->image()->directory('recipes')->nullable(),
    Forms\Components\TextInput::make('servings')->numeric()->nullable(),
    Forms\Components\Select::make('source')->options([
        'seed' => 'Seed', 'ai' => 'AI',
    ])->default('seed')->required(),
    Forms\Components\Select::make('ingredients')
        ->relationship('ingredients', 'name')
        ->multiple()->preload()->searchable()->createOptionForm([
            Forms\Components\TextInput::make('name')->required(),
        ]),
]);
```
Add columns in `table()`: `name`, `source`, `servings`, `created_at`.

- [ ] **Step 5: Add admin seeder**

`database/seeders/AdminSeeder.php`:
```php
<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'Admin', 'password' => Hash::make(env('ADMIN_PASSWORD', 'password'))]
        );
    }
}
```
Call it from `DatabaseSeeder::run()`: `$this->call(AdminSeeder::class);`

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=FilamentAdminTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filament app/Providers database/seeders config bootstrap tests/Feature/FilamentAdminTest.php
git commit -m "feat: add Filament admin panel with Recipe CRUD"
```

---

### Task 7: Livewire RecipeFinder + results & detail pages

**Files:**
- Create: `app/Livewire/RecipeFinder.php`, `resources/views/livewire/recipe-finder.blade.php`
- Create: `resources/views/recipes/show.blade.php`, route + controller method in `routes/web.php`
- Modify: `resources/views/welcome.blade.php` → mount the component (or new `home.blade.php`)
- Test: `tests/Feature/RecipeFinderTest.php`

**Interfaces:**
- Consumes: `RecipeMatcher::search()`, `MatchResult`.
- Produces:
  - Livewire component `RecipeFinder` with public `array $ingredients = []`, `string $newIngredient = ''`, `array $results = []`.
  - Methods: `addIngredient()`, `removeIngredient(int $index)`, `search()`.
  - `results` is an array of `['name','score','matched','missing','id']`.
  - Route `GET /recipes/{recipe}` → `recipes.show`.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Livewire\RecipeFinder;
use App\Models\Recipe;
use App\Models\Ingredient;
use Livewire\Livewire;

it('adds ingredients and returns ranked matches', function () {
    $r = Recipe::create(['name' => 'Egg Fry', 'instructions' => 'x', 'source' => 'seed']);
    $r->ingredients()->attach(Ingredient::findOrCreateNormalized('egg'));
    $r->ingredients()->attach(Ingredient::findOrCreateNormalized('salt'));

    Livewire::test(RecipeFinder::class)
        ->set('newIngredient', 'Egg')->call('addIngredient')
        ->set('newIngredient', 'salt')->call('addIngredient')
        ->call('search')
        ->assertSet('results.0.name', 'Egg Fry')
        ->assertSet('results.0.score', 1.0);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeFinderTest`
Expected: FAIL (component missing).

- [ ] **Step 3: Implement the component**

`app/Livewire/RecipeFinder.php`:
```php
<?php
namespace App\Livewire;

use App\Services\Matching\RecipeMatcher;
use App\Support\IngredientNormalizer;
use Livewire\Component;

class RecipeFinder extends Component
{
    public array $ingredients = [];
    public string $newIngredient = '';
    public array $results = [];
    public bool $searched = false;

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

    public function render()
    {
        return view('livewire.recipe-finder');
    }
}
```

- [ ] **Step 4: Implement the view**

`resources/views/livewire/recipe-finder.blade.php`:
```blade
<div class="finder">
    <form wire:submit.prevent="addIngredient" class="finder__add">
        <input type="text" wire:model="newIngredient" placeholder="Tambah bahan..." aria-label="Bahan">
        <button type="submit">Tambah</button>
    </form>

    <ul class="finder__chips">
        @foreach ($ingredients as $i => $ing)
            <li>{{ $ing }} <button wire:click="removeIngredient({{ $i }})" aria-label="Hapus">×</button></li>
        @endforeach
    </ul>

    <button wire:click="search" class="finder__search" @disabled(empty($ingredients))>Cari Resep</button>

    @if ($searched)
        <section class="results">
            @forelse ($results as $r)
                <article class="result">
                    <a href="{{ route('recipes.show', $r['id']) }}">{{ $r['name'] }}</a>
                    <span class="result__score">{{ (int) round($r['score'] * 100) }}% cocok</span>
                    @if (!empty($r['missing']))
                        <p class="result__missing">Kurang: {{ implode(', ', $r['missing']) }}</p>
                    @endif
                </article>
            @empty
                <p>Tidak ada resep yang cukup cocok di database.</p>
            @endforelse
        </section>
    @endif
</div>
```

- [ ] **Step 5: Wire route + home + detail page**

In `routes/web.php`:
```php
use App\Models\Recipe;

Route::get('/', fn () => view('home'));
Route::get('/recipes/{recipe}', function (Recipe $recipe) {
    return view('recipes.show', ['recipe' => $recipe->load('ingredients')]);
})->name('recipes.show');
```
`resources/views/home.blade.php`:
```blade
<x-layout>
    <h1>Cari Resep dari Bahanmu</h1>
    @livewire('recipe-finder')
</x-layout>
```
`resources/views/recipes/show.blade.php`:
```blade
<x-layout>
    <article class="recipe">
        <h1>{{ $recipe->name }}</h1>
        <img src="{{ $recipe->image_url ?? asset('images/recipe-placeholder.png') }}" alt="{{ $recipe->name }}">
        <h2>Bahan</h2>
        <ul>@foreach ($recipe->ingredients as $i)<li>{{ $i->name }}</li>@endforeach</ul>
        <h2>Langkah</h2>
        <p>{!! nl2br(e($recipe->instructions)) !!}</p>
    </article>
</x-layout>
```
Create a minimal layout `resources/views/components/layout.blade.php`:
```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recipe PWA</title>
    @livewireStyles
</head>
<body>
    <main>{{ $slot }}</main>
    @livewireScripts
</body>
</html>
```

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=RecipeFinderTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire resources/views routes/web.php tests/Feature/RecipeFinderTest.php
git commit -m "feat: add Livewire recipe finder, results and detail pages"
```

---

# PHASE 2 — PWA & Gemini Integration

### Task 8: PWA manifest, icons & service worker (app shell)

**Files:**
- Create: `public/manifest.json`, `public/sw.js`, `public/images/recipe-placeholder.png` (placeholder asset), `public/offline.html`
- Modify: `resources/views/components/layout.blade.php` (link manifest + register SW)
- Test: `tests/Feature/PwaAssetsTest.php`

**Interfaces:**
- Produces: installable PWA; service worker caches app shell + offline fallback; previously visited recipe detail pages served from cache when offline.

- [ ] **Step 1: Write the failing test**

```php
<?php
it('serves a valid web manifest', function () {
    $res = $this->get('/manifest.json');
    $res->assertStatus(200);
    $res->assertJsonStructure(['name', 'start_url', 'display', 'icons']);
});

it('serves the service worker at root scope', function () {
    $this->get('/sw.js')->assertStatus(200)
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=PwaAssetsTest`
Expected: FAIL (files missing).

- [ ] **Step 3: Create manifest**

`public/manifest.json`:
```json
{
  "name": "Recipe Recommender",
  "short_name": "Recipes",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2e7d32",
  "icons": [
    { "src": "/images/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/images/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```
Add `public/images/icon-192.png`, `icon-512.png`, and `recipe-placeholder.png` (any PNG assets; generate simple ones).

- [ ] **Step 4: Create the service worker**

`public/sw.js`:
```js
const CACHE = 'app-shell-v1';
const SHELL = ['/', '/offline.html', '/manifest.json', '/images/recipe-placeholder.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // Cache visited recipe detail pages (network-first, fallback to cache).
  if (url.pathname.startsWith('/recipes/')) {
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match('/offline.html')))
    );
    return;
  }

  // App shell: cache-first.
  e.respondWith(caches.match(req).then((r) => r || fetch(req).catch(() => caches.match('/offline.html'))));
});
```

`public/offline.html`:
```html
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Offline</title></head>
<body><h1>Anda sedang offline</h1><p>Pencarian butuh koneksi. Resep yang sudah dibuka tetap bisa dilihat.</p></body></html>
```

- [ ] **Step 5: Register from layout**

In `resources/views/components/layout.blade.php` `<head>` add:
```blade
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#2e7d32">
```
Before `</body>`:
```blade
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
}
</script>
```

- [ ] **Step 6: Run to verify it passes**

Run: `php artisan test --filter=PwaAssetsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add public resources/views/components/layout.blade.php tests/Feature/PwaAssetsTest.php
git commit -m "feat: add PWA manifest, icons and app-shell service worker"
```

---

### Task 9: Gemini client + response parser

**Files:**
- Create: `app/Services/Gemini/GeminiResponseParser.php`, `app/Services/Gemini/GeminiRecipeClient.php`
- Modify: `config/services.php` (add `gemini` key), `.env.example`
- Test: `tests/Feature/GeminiResponseParserTest.php`, `tests/Feature/GeminiRecipeClientTest.php`

**Interfaces:**
- Produces:
  - `GeminiResponseParser::parse(string $rawText): array` → list of `['name'=>string,'ingredients'=>string[],'instructions'=>string,'servings'=>?int]`. Throws `InvalidArgumentException` on unparseable input.
  - `GeminiRecipeClient::suggest(array $ingredientNames): array` → same shape; uses `Http` facade against the configured endpoint; calls the parser. (Mock HTTP in tests — no real network.)

- [ ] **Step 1: Write the parser failing test**

```php
<?php
use App\Services\Gemini\GeminiResponseParser;

it('parses a JSON array of recipes', function () {
    $json = json_encode([
        ['name' => 'Soup', 'ingredients' => ['Water', 'Salt'], 'instructions' => 'Boil.', 'servings' => 2],
    ]);
    $out = (new GeminiResponseParser())->parse($json);
    expect($out[0]['name'])->toBe('Soup');
    expect($out[0]['ingredients'])->toBe(['Water', 'Salt']);
});

it('strips code fences before parsing', function () {
    $raw = "```json\n[{\"name\":\"X\",\"ingredients\":[\"a\"],\"instructions\":\"y\"}]\n```";
    $out = (new GeminiResponseParser())->parse($raw);
    expect($out[0]['name'])->toBe('X');
});

it('throws on garbage', function () {
    (new GeminiResponseParser())->parse('not json');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=GeminiResponseParserTest`
Expected: FAIL.

- [ ] **Step 3: Implement the parser**

`app/Services/Gemini/GeminiResponseParser.php`:
```php
<?php
namespace App\Services\Gemini;

use InvalidArgumentException;

class GeminiResponseParser
{
    public function parse(string $rawText): array
    {
        $clean = trim($rawText);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Gemini response is not valid JSON.');
        }

        // Allow either a bare array or {"recipes":[...]}
        $recipes = array_is_list($data) ? $data : ($data['recipes'] ?? null);
        if (!is_array($recipes)) {
            throw new InvalidArgumentException('No recipe list found in response.');
        }

        return array_map(function ($r) {
            if (!isset($r['name'], $r['ingredients'], $r['instructions']) || !is_array($r['ingredients'])) {
                throw new InvalidArgumentException('Recipe entry missing required fields.');
            }
            return [
                'name' => (string) $r['name'],
                'ingredients' => array_values(array_map('strval', $r['ingredients'])),
                'instructions' => (string) $r['instructions'],
                'servings' => isset($r['servings']) ? (int) $r['servings'] : null,
            ];
        }, $recipes);
    }
}
```

- [ ] **Step 4: Run parser test**

Run: `php artisan test --filter=GeminiResponseParserTest`
Expected: PASS.

- [ ] **Step 5: Add config**

In `config/services.php` add:
```php
'gemini' => [
    'key' => env('GEMINI_API_KEY'),
    'endpoint' => env('GEMINI_ENDPOINT'), // full generateContent URL incl. model; verify current value at build time
],
```
In `.env.example` add:
```dotenv
GEMINI_API_KEY=
GEMINI_ENDPOINT=
```

- [ ] **Step 6: Write the client failing test (HTTP mocked)**

```php
<?php
use App\Services\Gemini\GeminiRecipeClient;
use Illuminate\Support\Facades\Http;

it('sends ingredients and returns parsed recipes', function () {
    config()->set('services.gemini.endpoint', 'https://gemini.test/generate');
    config()->set('services.gemini.key', 'test-key');

    $payloadText = json_encode([
        ['name' => 'AI Stew', 'ingredients' => ['carrot', 'water'], 'instructions' => 'Cook.', 'servings' => 3],
    ]);
    Http::fake([
        'gemini.test/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $payloadText]]]]],
        ], 200),
    ]);

    $out = (new GeminiRecipeClient())->suggest(['carrot', 'water']);
    expect($out[0]['name'])->toBe('AI Stew');
    Http::assertSent(fn ($req) => str_contains($req->body(), 'carrot'));
});
```

- [ ] **Step 7: Implement the client**

`app/Services/Gemini/GeminiRecipeClient.php`:
```php
<?php
namespace App\Services\Gemini;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiRecipeClient
{
    public function __construct(private GeminiResponseParser $parser = new GeminiResponseParser()) {}

    public function suggest(array $ingredientNames): array
    {
        $endpoint = config('services.gemini.endpoint');
        $key = config('services.gemini.key');
        if (!$endpoint || !$key) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $list = implode(', ', $ingredientNames);
        $prompt = "Beri 3 ide resep memakai sebagian besar bahan ini: {$list}. "
            . "Balas HANYA JSON array. Tiap item: {name, ingredients (array string), instructions, servings (number)}.";

        $response = Http::timeout(30)
            ->withQueryParameters(['key' => $key])
            ->post($endpoint, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: ' . $response->status());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!is_string($text)) {
            throw new RuntimeException('Unexpected Gemini response shape.');
        }

        return $this->parser->parse($text);
    }
}
```

- [ ] **Step 8: Run client test**

Run: `php artisan test --filter=GeminiRecipeClientTest`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Gemini config/services.php .env.example tests/Feature/Gemini*
git commit -m "feat: add Gemini client and structured response parser"
```

---

### Task 10: AI recipe importer (persist + dedup)

**Files:**
- Create: `app/Services/Gemini/AiRecipeImporter.php`
- Test: `tests/Feature/AiRecipeImporterTest.php`

**Interfaces:**
- Consumes: `Recipe`, `Ingredient::findOrCreateNormalized()`, parsed recipe arrays from `GeminiResponseParser`/`GeminiRecipeClient`.
- Produces: `AiRecipeImporter::import(array $recipeData): ?Recipe` — creates a `Recipe` with `source='ai'`, attaches normalized ingredients; returns `null` if a recipe with the same normalized name already exists (dedup). `importMany(array $recipes): array` returns the created Recipe models only.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Models\Recipe;
use App\Services\Gemini\AiRecipeImporter;

it('imports an AI recipe with normalized ingredients', function () {
    $recipe = (new AiRecipeImporter())->import([
        'name' => 'AI Curry',
        'ingredients' => ['Coconut Milk', 'curry powder'],
        'instructions' => 'Simmer.',
        'servings' => 4,
    ]);

    expect($recipe)->not->toBeNull();
    expect($recipe->source)->toBe(Recipe::SOURCE_AI);
    expect($recipe->ingredients->pluck('name')->all())->toContain('coconut milk');
});

it('dedups by normalized name', function () {
    Recipe::create(['name' => 'AI Curry', 'instructions' => 'x', 'source' => 'ai']);
    $dup = (new AiRecipeImporter())->import([
        'name' => '  ai curry ', 'ingredients' => ['x'], 'instructions' => 'y',
    ]);
    expect($dup)->toBeNull();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=AiRecipeImporterTest`
Expected: FAIL.

- [ ] **Step 3: Implement the importer**

`app/Services/Gemini/AiRecipeImporter.php`:
```php
<?php
namespace App\Services\Gemini;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\IngredientNormalizer;
use Illuminate\Support\Facades\DB;

class AiRecipeImporter
{
    public function import(array $recipeData): ?Recipe
    {
        $name = trim($recipeData['name']);
        $normalizedName = IngredientNormalizer::normalize($name);

        $exists = Recipe::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->exists();
        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($recipeData, $name) {
            $recipe = Recipe::create([
                'name' => $name,
                'instructions' => $recipeData['instructions'],
                'servings' => $recipeData['servings'] ?? null,
                'image_url' => null,
                'source' => Recipe::SOURCE_AI,
            ]);
            foreach ($recipeData['ingredients'] as $ing) {
                $recipe->ingredients()->syncWithoutDetaching(
                    Ingredient::findOrCreateNormalized($ing)
                );
            }
            return $recipe->load('ingredients');
        });
    }

    public function importMany(array $recipes): array
    {
        $created = [];
        foreach ($recipes as $r) {
            $recipe = $this->import($r);
            if ($recipe) {
                $created[] = $recipe;
            }
        }
        return $created;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=AiRecipeImporterTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gemini/AiRecipeImporter.php tests/Feature/AiRecipeImporterTest.php
git commit -m "feat: add AI recipe importer with dedup"
```

---

### Task 11: Wire "Explore with AI" into RecipeFinder

**Files:**
- Modify: `app/Livewire/RecipeFinder.php`, `resources/views/livewire/recipe-finder.blade.php`
- Test: `tests/Feature/ExploreWithAiTest.php`

**Interfaces:**
- Consumes: `GeminiRecipeClient::suggest()`, `AiRecipeImporter::importMany()`.
- Produces: `RecipeFinder::exploreWithAi()` — calls Gemini with current `$ingredients`, imports results, then re-runs `search()` so new AI recipes appear. Sets `$aiError` (string) on failure. New public props: `bool $aiLoading`, `?string $aiError`.

- [ ] **Step 1: Write the failing test (mock the client binding)**

```php
<?php
use App\Livewire\RecipeFinder;
use App\Services\Gemini\GeminiRecipeClient;
use Livewire\Livewire;
use Mockery;

it('explores with AI, imports recipes, and shows them', function () {
    $fake = Mockery::mock(GeminiRecipeClient::class);
    $fake->shouldReceive('suggest')->once()->andReturn([
        ['name' => 'AI Toast', 'ingredients' => ['bread', 'butter'], 'instructions' => 'Toast it.', 'servings' => 1],
    ]);
    app()->instance(GeminiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread', 'butter'])
        ->call('exploreWithAi')
        ->assertSet('aiError', null)
        ->assertSet('results.0.name', 'AI Toast');
});

it('reports a friendly error when AI fails', function () {
    $fake = Mockery::mock(GeminiRecipeClient::class);
    $fake->shouldReceive('suggest')->andThrow(new RuntimeException('boom'));
    app()->instance(GeminiRecipeClient::class, $fake);

    Livewire::test(RecipeFinder::class)
        ->set('ingredients', ['bread'])
        ->call('exploreWithAi')
        ->assertSet('aiError', 'Gagal mengambil resep AI. Coba lagi nanti.');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=ExploreWithAiTest`
Expected: FAIL.

- [ ] **Step 3: Extend the component**

Add to `app/Livewire/RecipeFinder.php`:
```php
public bool $aiLoading = false;
public ?string $aiError = null;

public function exploreWithAi(
    \App\Services\Gemini\GeminiRecipeClient $client,
    \App\Services\Gemini\AiRecipeImporter $importer,
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
```
> Note: `search()` already accepts an injected `RecipeMatcher`; calling `$this->search($matcher)` passes it explicitly.

- [ ] **Step 4: Add the UI control**

In `resources/views/livewire/recipe-finder.blade.php`, after the results section:
```blade
<div class="ai">
    <button wire:click="exploreWithAi" wire:loading.attr="disabled" @disabled(empty($ingredients))>
        <span wire:loading.remove wire:target="exploreWithAi">Eksplor dengan AI</span>
        <span wire:loading wire:target="exploreWithAi">Mencari ide resep...</span>
    </button>
    @if ($aiError)<p class="ai__error" role="alert">{{ $aiError }}</p>@endif
</div>
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=ExploreWithAiTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/RecipeFinder.php resources/views/livewire/recipe-finder.blade.php tests/Feature/ExploreWithAiTest.php
git commit -m "feat: wire Explore-with-AI into recipe finder"
```

---

# PHASE 3 — UX Polish & Pre-Deploy

### Task 12: Anonymous ratings with session rate-limit

**Files:**
- Create: `app/Livewire/RecipeRating.php`, `resources/views/livewire/recipe-rating.blade.php`
- Modify: `resources/views/recipes/show.blade.php` (mount it)
- Test: `tests/Feature/RecipeRatingTest.php`

**Interfaces:**
- Consumes: `Rating`, `Recipe`.
- Produces: `RecipeRating` Livewire component with `mount(Recipe $recipe)`, `rate(int $value)`; stores one rating per session per recipe (session token); exposes `float $average` and `int $count`.

- [ ] **Step 1: Write the failing test**

```php
<?php
use App\Livewire\RecipeRating;
use App\Models\Recipe;
use App\Models\Rating;
use Livewire\Livewire;

it('records a rating once per session', function () {
    $recipe = Recipe::create(['name' => 'X', 'instructions' => 'y', 'source' => 'seed']);

    Livewire::test(RecipeRating::class, ['recipe' => $recipe])
        ->call('rate', 5)
        ->call('rate', 3) // second attempt ignored for same session
        ->assertSet('count', 1)
        ->assertSet('average', 5.0);

    expect(Rating::count())->toBe(1);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeRatingTest`
Expected: FAIL.

- [ ] **Step 3: Implement the component**

`app/Livewire/RecipeRating.php`:
```php
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
```
`resources/views/livewire/recipe-rating.blade.php`:
```blade
<div class="rating">
    <div class="rating__stars">
        @for ($i = 1; $i <= 5; $i++)
            <button wire:click="rate({{ $i }})" @disabled($hasRated) aria-label="Beri {{ $i }} bintang">★</button>
        @endfor
    </div>
    <p>{{ $average }} / 5 ({{ $count }} penilaian)</p>
    @if ($hasRated)<p class="rating__thanks">Terima kasih atas penilaiannya!</p>@endif
</div>
```
Mount in `resources/views/recipes/show.blade.php` (after instructions):
```blade
@livewire('recipe-rating', ['recipe' => $recipe])
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=RecipeRatingTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/RecipeRating.php resources/views/livewire/recipe-rating.blade.php resources/views/recipes/show.blade.php tests/Feature/RecipeRatingTest.php
git commit -m "feat: add anonymous per-session recipe ratings"
```

---

### Task 13: Performance — indexes & Gemini result caching

**Files:**
- Create: migration `..._add_search_indexes.php`
- Modify: `app/Services/Gemini/GeminiRecipeClient.php` (cache identical ingredient queries)
- Test: `tests/Feature/GeminiCacheTest.php`

**Interfaces:**
- Consumes: existing tables, `Cache` facade.
- Produces: index on `ingredients.name` (already unique → covered) and `recipe_ingredient(ingredient_id)`; `GeminiRecipeClient::suggest()` caches results keyed by sorted normalized ingredient list for a short TTL so repeated identical explorations don't re-hit the API.

- [ ] **Step 1: Write the failing cache test**

```php
<?php
use App\Services\Gemini\GeminiRecipeClient;
use Illuminate\Support\Facades\Http;

it('caches identical ingredient queries', function () {
    config()->set('services.gemini.endpoint', 'https://gemini.test/generate');
    config()->set('services.gemini.key', 'k');

    $text = json_encode([['name' => 'Cached', 'ingredients' => ['a'], 'instructions' => 'b']]);
    Http::fake(['gemini.test/*' => Http::response(
        ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], 200
    )]);

    $client = new GeminiRecipeClient();
    $client->suggest(['a', 'b']);
    $client->suggest(['b', 'a']); // same set, different order

    Http::assertSentCount(1);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=GeminiCacheTest`
Expected: FAIL (called twice).

- [ ] **Step 3: Add caching in the client**

Wrap the request body of `suggest()`:
```php
public function suggest(array $ingredientNames): array
{
    $key = 'gemini:' . md5(collect($ingredientNames)
        ->map(fn ($n) => \App\Support\IngredientNormalizer::normalize($n))
        ->sort()->implode('|'));

    return \Illuminate\Support\Facades\Cache::remember($key, now()->addHours(6), function () use ($ingredientNames) {
        return $this->callGemini($ingredientNames);
    });
}
```
Move the existing HTTP+parse body into a new private method `callGemini(array $ingredientNames): array`.

- [ ] **Step 4: Add the index migration**

`database/migrations/..._add_search_indexes.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('recipe_ingredient', function (Blueprint $t) {
            $t->index('ingredient_id');
        });
    }
    public function down(): void {
        Schema::table('recipe_ingredient', function (Blueprint $t) {
            $t->dropIndex(['ingredient_id']);
        });
    }
};
```

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=GeminiCacheTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Gemini/GeminiRecipeClient.php database/migrations tests/Feature/GeminiCacheTest.php
git commit -m "perf: cache Gemini queries and index pivot lookups"
```

---

### Task 14: Full suite + offline smoke verification

**Files:**
- Modify: any failing tests surfaced; no new feature code expected
- Test: run the entire suite

**Interfaces:**
- Produces: green full test suite; manual offline check documented.

- [ ] **Step 1: Run the entire suite**

Run: `php artisan test`
Expected: PASS (all tests).

- [ ] **Step 2: Manual PWA offline check (documented, not automated)**

In a built/served app: load home, open a recipe, enable browser offline, reload the recipe page → it still renders from cache; home shows app shell; a fresh search shows the offline fallback. Record the result in the PR description.

- [ ] **Step 3: Commit (if fixes were needed)**

```bash
git add -A
git commit -m "test: green full suite and offline verification"
```

---

# PHASE 4 — Deployment & Post-Launch

### Task 15: Deployment configuration

**Files:**
- Create: `Procfile` (or `render.yaml` / `fly.toml` per chosen PaaS), `.env.production.example`
- Create: `docs/DEPLOY.md`
- Modify: `config/database.php` (honor `DATABASE_URL` for Neon)
- Test: `tests/Feature/ProductionConfigTest.php`

**Interfaces:**
- Produces: a deploy recipe to a free-tier PaaS with Neon Postgres; migrations + seeders run on release; secrets via PaaS env.

- [ ] **Step 1: Write the failing config test**

```php
<?php
it('parses DATABASE_URL into the pgsql connection', function () {
    config()->set('database.connections.pgsql.url', 'postgres://u:p@host:5432/dbname');
    expect(config('database.default'))->toBe('pgsql');
    // Laravel parses the `url` key automatically at connection time; assert the key is honored.
    expect(config('database.connections.pgsql.url'))->toContain('host:5432');
});
```

- [ ] **Step 2: Run to verify it fails or guides**

Run: `php artisan test --filter=ProductionConfigTest`
Expected: FAIL until `url` key is wired.

- [ ] **Step 3: Honor DATABASE_URL**

In `config/database.php`, ensure the `pgsql` array has:
```php
'url' => env('DATABASE_URL'),
```

- [ ] **Step 4: Add deploy artifacts**

`Procfile` (Render/Heroku-style):
```
web: vendor/bin/heroku-php-apache2 public/
release: php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force
```
`.env.production.example`:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=
DATABASE_URL=
GEMINI_API_KEY=
GEMINI_ENDPOINT=
ADMIN_EMAIL=
ADMIN_PASSWORD=
SESSION_DRIVER=database
CACHE_STORE=database
```
`docs/DEPLOY.md` — document: create Neon DB → copy `DATABASE_URL`; create PaaS web service from repo; set env vars (above); confirm release command runs migrations; note free-tier cold-start trade-off; remind to verify Gemini/Neon/PaaS free-tier limits at deploy time.

- [ ] **Step 5: Run to verify it passes**

Run: `php artisan test --filter=ProductionConfigTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add Procfile .env.production.example docs/DEPLOY.md config/database.php tests/Feature/ProductionConfigTest.php
git commit -m "chore: add free-tier PaaS deploy config and docs"
```

---

### Task 16: Basic error monitoring hook

**Files:**
- Modify: `bootstrap/app.php` (or `app/Exceptions/Handler.php` depending on Laravel 11 structure), `config/services.php`, `docs/DEPLOY.md`
- Test: `tests/Feature/MonitoringConfigTest.php`

**Interfaces:**
- Produces: optional Sentry-style DSN read from env; when unset, app runs normally (no hard dependency). Documented as a free-tier option.

- [ ] **Step 1: Write the failing test**

```php
<?php
it('runs without a monitoring DSN configured', function () {
    config()->set('services.sentry.dsn', null);
    $this->get('/')->assertStatus(200);
});
```

- [ ] **Step 2: Run to verify it passes/fails**

Run: `php artisan test --filter=MonitoringConfigTest`
Expected: PASS (app must not require monitoring). If it fails, remove any hard dependency.

- [ ] **Step 3: Add optional config + docs**

In `config/services.php`:
```php
'sentry' => ['dsn' => env('SENTRY_DSN')],
```
In `docs/DEPLOY.md`, add a "Monitoring (optional, free tier)" section: install `sentry/sentry-laravel` only if desired; set `SENTRY_DSN`; otherwise rely on PaaS logs. Keep it optional so the zero-budget path needs nothing.

- [ ] **Step 4: Commit**

```bash
git add config/services.php docs/DEPLOY.md tests/Feature/MonitoringConfigTest.php
git commit -m "chore: add optional error monitoring hook and docs"
```

---

## Self-Review

**Spec coverage:**
- Phase 1 (DB, ingredient input, match search, basic UI) → Tasks 1–7. ✅
- Filament admin (resolved decision) → Task 6. ✅
- Phase 2 PWA (manifest/SW/offline scope) → Task 8; Gemini integration + parse + save + dedup → Tasks 9–10; UI switch DB↔AI → Task 11. ✅
- Phase 3 UX (loading/error states) → built into Task 11 (`wire:loading`, `aiError`); ratings → Task 12; perf (indexes, caching) → Task 13; full testing + offline check → Task 14; image placeholder → implemented in Task 7 detail view + Task 8 placeholder asset. ✅
- Phase 4 deploy → Task 15; monitoring → Task 16. ✅
- Global constraints (anonymous users, Postgres, secrets in env, AI source/null image, Gemini only on explicit action, normalized unique ingredients) → enforced across Tasks 2, 3, 8, 9, 11, 15. ✅

**Placeholder scan:** No "TBD/TODO/implement later" in steps; every code step shows full code. External-service specifics (Gemini model/endpoint, free-tier numbers) are intentionally read from env / "verify at build time" per the spec's guidance, not hardcoded. ✅

**Type consistency:**
- `IngredientNormalizer::normalize()` — consistent everywhere.
- `Ingredient::findOrCreateNormalized()` — consistent (Tasks 3, 5, 10).
- `RecipeMatcher::search(array, float): MatchResult[]` and `MatchResult{recipe,score,matched,missing}` — consistent (Tasks 5, 7, 11).
- `GeminiResponseParser::parse(string): array` and `GeminiRecipeClient::suggest(array): array` — consistent (Tasks 9, 11, 13).
- `AiRecipeImporter::import()/importMany()` — consistent (Tasks 10, 11).
- `RecipeFinder::search(RecipeMatcher)` injected and reused by `exploreWithAi()` — consistent (Tasks 7, 11). ✅
