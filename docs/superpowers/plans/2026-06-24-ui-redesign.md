# UI Redesign — Cooking Mama PWA Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti semua tampilan publik dari class CSS kustom kosong ke Tailwind utility classes bergaya hangat & nyaman, sekaligus aktifkan Tailwind yang belum terhubung.

**Architecture:** Pure blade template edits — tidak ada PHP baru, tidak ada logika Livewire baru. Semua perubahan adalah class HTML. Tailwind v4 sudah terpasang di `package.json`; hanya butuh `@vite` di layout untuk mengaktifkannya. Lima file blade diubah secara berurutan dari luar ke dalam (layout → halaman → komponen).

**Tech Stack:** Tailwind CSS v4, Vite, Laravel Blade, Livewire 4. Node.js untuk build asset (`npm run dev`).

## Global Constraints

- Tailwind v4 sudah ada di `package.json` — jangan install ulang atau tambah dependency.
- `app.css` sudah benar (`@import 'tailwindcss'`) — jangan diubah.
- Semua Livewire directive (`wire:model`, `wire:click`, `wire:loading`, `@disabled`) harus dipertahankan persis.
- Admin Filament (`/admin`) di luar scope — jangan disentuh.
- `welcome.blade.php` di luar scope.
- Routing tidak berubah: `/` → home, `/recipes/{id}` → detail.
- Tidak ada tes PHP baru (tidak ada logika baru) — verifikasi visual di browser.
- Bottom nav "Resep" dan "Favorit" adalah tautan mati (`href="#"`) untuk saat ini.

---

## File Map

| File | Aksi |
|------|------|
| `resources/views/components/layout.blade.php` | Modifikasi — aktifkan Vite, tambah header + bottom nav + body class |
| `resources/views/home.blade.php` | Modifikasi — tambah teks sambutan |
| `resources/views/livewire/recipe-finder.blade.php` | Modifikasi — ganti semua class kustom ke Tailwind |
| `resources/views/recipes/show.blade.php` | Modifikasi — ganti semua class kustom ke Tailwind |
| `resources/views/livewire/recipe-rating.blade.php` | Modifikasi — ganti semua class kustom ke Tailwind |
| `docs/ROADMAP.md` | Modifikasi — catat Bottom nav Resep & Favorit sebagai TODO |

---

### Task 1: Aktifkan Tailwind + Layout Shell

**Files:**
- Modify: `resources/views/components/layout.blade.php`

**Interfaces:**
- Produces: `<x-layout>` dengan header hijau, body `bg-amber-50`, bottom nav, `$slot` terbungkus padding bawah.

- [ ] **Step 1: Jalankan dev server asset di terminal terpisah**

```bash
npm run dev
```

Biarkan berjalan. Ini akan hot-reload CSS setiap kali blade berubah.

- [ ] **Step 2: Ganti isi `layout.blade.php`**

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cooking Mama</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2e7d32">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-amber-50 font-sans text-stone-800">

    <header class="bg-green-700 text-white px-4 py-3 flex items-center gap-2 sticky top-0 z-10 shadow-sm">
        <span class="text-2xl">🍳</span>
        <span class="font-bold text-lg tracking-tight">Cooking Mama</span>
    </header>

    <main class="pb-24 px-4 pt-4 max-w-lg mx-auto">
        {{ $slot }}
    </main>

    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-stone-200 flex justify-around py-2 z-10">
        <a href="/" class="flex flex-col items-center gap-0.5 text-xs {{ request()->is('/') ? 'text-green-700 font-semibold' : 'text-stone-400' }}">
            <span class="text-xl">🔍</span>
            <span>Cari</span>
        </a>
        <a href="#" class="flex flex-col items-center gap-0.5 text-xs text-stone-400">
            <span class="text-xl">📖</span>
            <span>Resep</span>
        </a>
        <a href="#" class="flex flex-col items-center gap-0.5 text-xs text-stone-400">
            <span class="text-xl">⭐</span>
            <span>Favorit</span>
        </a>
    </nav>

    @livewireScripts
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
    </script>
</body>
</html>
```

- [ ] **Step 3: Buka browser ke `http://localhost:8000`**

Pastikan:
- Header hijau muncul di atas
- Background halaman krem (`amber-50`)
- Bottom nav putih dengan 3 ikon di bawah
- Tidak ada error di konsol browser

- [ ] **Step 4: Commit**

```bash
git add resources/views/components/layout.blade.php
git commit -m "feat: activate Tailwind via @vite and add warm layout shell with bottom nav"
```

---

### Task 2: Styling Recipe Finder

**Files:**
- Modify: `resources/views/livewire/recipe-finder.blade.php`

**Interfaces:**
- Consumes: layout dari Task 1 (body padding, max-width sudah ada)
- Produces: tampilan finder dengan chip hijau, kartu hasil, badge %, tombol AI oranye

- [ ] **Step 1: Ganti isi `recipe-finder.blade.php`**

```blade
<div class="space-y-4">
    {{-- Input tambah bahan --}}
    <form wire:submit.prevent="addIngredient" class="flex gap-2">
        <input
            type="text"
            wire:model="newIngredient"
            placeholder="Tambah bahan... (misal: telur)"
            aria-label="Nama bahan"
            class="flex-1 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-green-600 placeholder-stone-400"
        >
        <button
            type="submit"
            class="rounded-xl bg-green-700 px-4 py-3 text-sm font-bold text-white active:bg-green-800"
        >
            Tambah
        </button>
    </form>

    {{-- Chip bahan yang sudah ditambahkan --}}
    @if ($ingredients)
    <div class="flex flex-wrap gap-2">
        @foreach ($ingredients as $i => $ing)
            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                {{ $ing }}
                <button wire:click="removeIngredient({{ $i }})" aria-label="Hapus {{ $ing }}" class="text-green-600 hover:text-green-900 leading-none">&times;</button>
            </span>
        @endforeach
    </div>
    @endif

    {{-- Tombol cari --}}
    <button
        wire:click="search"
        @disabled(empty($ingredients))
        class="w-full rounded-xl bg-green-700 py-3 font-bold text-white shadow-sm active:bg-green-800 disabled:opacity-40 disabled:cursor-not-allowed"
    >
        🔍 Cari Resep
    </button>

    {{-- Tombol AI --}}
    <div>
        <button
            wire:click="exploreWithAi"
            wire:loading.attr="disabled"
            @disabled(empty($ingredients))
            class="w-full rounded-xl border border-orange-200 bg-orange-50 py-3 font-bold text-orange-600 active:bg-orange-100 disabled:opacity-40 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="exploreWithAi">✨ Eksplor dengan AI</span>
            <span wire:loading wire:target="exploreWithAi">Mencari ide resep AI...</span>
        </button>
        @if ($aiError)
            <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600" role="alert">{{ $aiError }}</p>
        @endif
    </div>

    {{-- Hasil pencarian --}}
    @if ($searched)
        <section class="space-y-3">
            @forelse ($results as $r)
                <a href="{{ route('recipes.show', $r['id']) }}" class="block rounded-xl bg-white p-4 shadow-sm active:shadow-none">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-stone-800">{{ $r['name'] }}</span>
                        @php $pct = (int) round($r['score'] * 100); @endphp
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold
                            {{ $pct >= 80 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $pct }}% cocok
                        </span>
                    </div>
                    @if (!empty($r['missing']))
                        <p class="mt-1 text-xs text-stone-400">Kurang: {{ implode(', ', $r['missing']) }}</p>
                    @endif
                </a>
            @empty
                <p class="rounded-xl bg-white p-4 text-center text-sm text-stone-400 shadow-sm">
                    Tidak ada resep yang cukup cocok. Coba Eksplor dengan AI!
                </p>
            @endforelse
        </section>
    @endif
</div>
```

- [ ] **Step 2: Verifikasi di browser**

Cek di `http://localhost:8000`:
- Input teks muncul dengan rounded corners
- Setelah tambah bahan, chip hijau muncul dengan tombol `×`
- Tombol "Cari Resep" hijau, disabled (abu-abu) saat bahan kosong
- Tombol "Eksplor dengan AI" oranye outline
- Setelah cari, kartu hasil muncul dengan badge % di kanan
- Badge hijau untuk ≥80%, kuning untuk <80%

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/recipe-finder.blade.php
git commit -m "feat: style recipe finder with Tailwind — chips, cards, and AI button"
```

---

### Task 3: Styling Recipe Detail

**Files:**
- Modify: `resources/views/recipes/show.blade.php`

**Interfaces:**
- Consumes: layout dari Task 1, rating component dari Task 4 (boleh dikerjakan dulu karena di-include via `@livewire`)
- Produces: halaman detail dengan gambar, pill bahan, langkah bernomor, dan slot rating

- [ ] **Step 1: Ganti isi `show.blade.php`**

```blade
<x-layout>
    <article class="space-y-5">
        {{-- Gambar --}}
        <div class="overflow-hidden rounded-2xl bg-amber-100 h-48 flex items-center justify-center">
            @if ($recipe->image_url)
                <img src="{{ $recipe->image_url }}" alt="{{ $recipe->name }}" class="w-full h-full object-cover">
            @else
                <span class="text-6xl">🍳</span>
            @endif
        </div>

        {{-- Judul + meta --}}
        <div>
            <h1 class="text-2xl font-bold text-stone-800">{{ $recipe->name }}</h1>
            @if ($recipe->servings)
                <span class="mt-1 inline-block rounded-full bg-stone-100 px-3 py-0.5 text-xs text-stone-500">
                    🍽️ {{ $recipe->servings }} porsi
                </span>
            @endif
        </div>

        {{-- Bahan --}}
        <section>
            <h2 class="mb-2 font-bold text-stone-700">Bahan</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($recipe->ingredients as $ingredient)
                    <span class="rounded-lg bg-stone-100 px-3 py-1 text-sm text-stone-700">
                        {{ $ingredient->name }}
                    </span>
                @endforeach
            </div>
        </section>

        {{-- Langkah --}}
        <section>
            <h2 class="mb-3 font-bold text-stone-700">Langkah Memasak</h2>
            <ol class="space-y-3">
                @foreach ($recipe->steps as $index => $step)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-700 text-xs font-bold text-white">
                            {{ $index + 1 }}
                        </span>
                        <span class="text-sm leading-relaxed text-stone-700">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Rating --}}
        <section class="rounded-xl bg-white p-4 shadow-sm">
            @livewire('recipe-rating', ['recipe' => $recipe])
        </section>
    </article>
</x-layout>
```

- [ ] **Step 2: Buka salah satu resep di browser**

Klik hasil dari pencarian, atau buka `http://localhost:8000/recipes/1`.

Pastikan:
- Kotak kuning besar dengan emoji 🍳 muncul sebagai placeholder gambar
- Judul besar, pill "X porsi" di bawahnya
- Pill abu-abu untuk setiap bahan
- Langkah memasak dengan nomor lingkaran hijau
- Kotak putih di bawah (slot rating dari Task 4)

- [ ] **Step 3: Commit**

```bash
git add resources/views/recipes/show.blade.php
git commit -m "feat: style recipe detail with numbered steps and ingredient pills"
```

---

### Task 4: Styling Recipe Rating

**Files:**
- Modify: `resources/views/livewire/recipe-rating.blade.php`

**Interfaces:**
- Consumes: state `$hasRated`, `$average`, `$count` dari `RecipeRating` Livewire component (sudah ada, tidak berubah)
- Produces: bintang kuning interaktif, pesan terima kasih hijau

- [ ] **Step 1: Ganti isi `recipe-rating.blade.php`**

```blade
<div>
    <p class="mb-2 text-sm font-semibold text-stone-600">Nilai resep ini:</p>
    <div class="flex gap-1" role="group" aria-label="Rating bintang">
        @for ($i = 1; $i <= 5; $i++)
            <button
                wire:click="rate({{ $i }})"
                @disabled($hasRated)
                aria-label="Beri {{ $i }} bintang"
                class="text-2xl leading-none transition-transform active:scale-110 disabled:cursor-default
                    {{ $i <= round($average) ? 'text-amber-400' : 'text-stone-300' }}"
            >★</button>
        @endfor
    </div>
    <p class="mt-1 text-xs text-stone-400">{{ $average }} / 5 &bull; {{ $count }} penilaian</p>
    @if ($hasRated)
        <p class="mt-2 text-sm font-medium text-green-700">✓ Terima kasih atas penilaiannya!</p>
    @endif
</div>
```

- [ ] **Step 2: Verifikasi di halaman detail resep**

- Bintang amber muncul (jumlah sesuai rata-rata), sisanya abu-abu
- Klik bintang → pesan "Terima kasih" hijau muncul, bintang tidak bisa diklik lagi
- Refresh halaman → rating tersimpan (sesi yang sama)

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/recipe-rating.blade.php
git commit -m "feat: style star rating with amber stars and green thank-you message"
```

---

### Task 5: Home page + ROADMAP update

**Files:**
- Modify: `resources/views/home.blade.php`
- Modify: `docs/ROADMAP.md`

**Interfaces:**
- Produces: halaman home dengan sambutan singkat di atas finder

- [ ] **Step 1: Ganti isi `home.blade.php`**

```blade
<x-layout>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-stone-800">Mau masak apa hari ini?</h1>
            <p class="text-sm text-stone-400">Masukkan bahan yang ada di dapur, kami carikan resepnya.</p>
        </div>
        @livewire('recipe-finder')
    </div>
</x-layout>
```

- [ ] **Step 2: Tambah catatan ke `docs/ROADMAP.md`**

Tambahkan section berikut di akhir file:

```markdown
## Bottom navigation (lanjutan)

Tab **Resep** dan **Favorit** di bottom nav saat ini adalah tautan mati (`href="#"`).

Yang perlu dibuat:
- **Halaman daftar semua resep** — route `/recipes` → view dengan list seluruh resep di DB, agar tab "Resep" punya tujuan.
- **Fitur favorit** — simpan resep favorit per sesi/user, agar tab "Favorit" punya konten.
```

- [ ] **Step 3: Verifikasi tampilan akhir**

Buka `http://localhost:8000` dan periksa keseluruhan flow:
1. Halaman home: teks sambutan + finder
2. Tambah bahan → chip hijau muncul
3. Klik "Cari Resep" → kartu hasil dengan badge %
4. Klik kartu resep → halaman detail dengan langkah bernomor
5. Klik bintang → pesan terima kasih muncul
6. Bottom nav terlihat di semua halaman, tab "Cari" aktif (hijau) di home

- [ ] **Step 4: Commit**

```bash
git add resources/views/home.blade.php docs/ROADMAP.md
git commit -m "feat: add home page welcome text and log bottom nav TODOs to roadmap"
```

---

## Verifikasi Akhir

Setelah semua task selesai, jalankan suite test untuk memastikan tidak ada logika yang rusak:

```bash
composer test
```

Expected: semua test pass (tidak ada test yang diubah — ini sanity check bahwa blade edit tidak merusak logika).

Kemudian build production asset:

```bash
npm run build
```

Expected: selesai tanpa error.
