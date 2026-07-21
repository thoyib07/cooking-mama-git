# Saran Pengembangan

Fitur yang ditunda secara sengaja. Tambahkan saat ada kebutuhan nyata.

## Langkah memasak terstruktur (lanjutan)

Resep sekarang menyimpan `steps` sebagai `array<string>` (JSON), satu elemen
per langkah. Guardrail konsistensi ada di `App\Support\RecipeSteps::normalize()`,
dipakai semua jalur tulis lewat mutator di `Recipe`.

Yang belum dibuat:

- **Editor langkah di admin Filament** — sekarang masih `Textarea` (satu langkah
  per baris). Naik ke `Repeater` agar tiap langkah jadi field tersendiri,
  bisa di-drag untuk mengurutkan ulang. Lihat `RecipeForm.php`.
- **Metadata per langkah** — durasi/timer, suhu, dan foto per langkah. Ini
  butuh ubah bentuk `steps` dari `array<string>` jadi `array<object>`
  (mis. `{text, duration_min, image_url}`), sesuaikan `RecipeSteps::normalize()`,
  prompt Gemini, dan tampilan.
- **Mode masak interaktif** — tampilan langkah-per-langkah dengan timer, untuk
  diikuti sambil memasak. Bergantung pada metadata per langkah di atas.

## Bottom navigation

Tab **Resep** (`/recipes`, `App\Livewire\RecipeList`) dan **Favorit**
(`/favorites`, `App\Livewire\FavoritesList`) sudah aktif. Favorit disimpan lewat
cookie anonim jangka panjang (`App\Support\FavoritorToken`), bukan session Laravel
yang cuma bertahan 120 menit — lihat `favorites` table & `App\Livewire\FavoriteButton`.
