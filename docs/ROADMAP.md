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

## Bottom navigation (lanjutan)

Tab **Resep** dan **Favorit** di bottom nav saat ini adalah tautan mati (`href="#"`).

Yang perlu dibuat:
- **Halaman daftar semua resep** — route `/recipes` → view dengan list seluruh resep di DB, agar tab "Resep" punya tujuan.
- **Fitur favorit** — simpan resep favorit per sesi/user, agar tab "Favorit" punya konten.
