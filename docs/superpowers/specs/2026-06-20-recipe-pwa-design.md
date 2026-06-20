# Development Plan — Recipe Recommender PWA

**Tanggal:** 2026-06-20
**Status:** Disetujui (siap masuk tahap penulisan implementation plan)

## Ringkasan

Progressive Web App (PWA) yang merekomendasikan resep berdasarkan bahan yang tersedia di tangan pengguna. Pencarian utama dilakukan terhadap database lokal. Untuk rekomendasi yang lebih luas, aplikasi memanggil **Gemini API**, dan resep hasil AI **disimpan kembali ke database** sehingga basis data makin kaya seiring pemakaian.

Proyek ini bersifat personal, zero-budget, dengan target deployment mendekati gratis.

## Keputusan Teknologi (Final)

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Backend | **Laravel (PHP)** | Sesuai keahlian developer; ekosistem matang |
| Database | **PostgreSQL @ Neon** (free tier) | Free tier stabil & generous; cocok untuk fitur teks/JSON |
| Frontend/UI | **Blade + Livewire**, dijadikan **PWA** | Reaktif tanpa SPA berat; input bahan dinamis mulus |
| Admin / kelola resep | **Filament** (1 akun admin) | Admin panel Laravel gratis; CRUD + upload gambar |
| AI | **Gemini API** (model versi terkini saat build) | Rekomendasi resep di luar basis data lokal |
| Hosting | **PaaS free tier** (Render / Fly.io / Railway) | Realistis untuk Laravel; mendekati gratis |
| End-user | **Anonim, tanpa login** | Scope minimal; publik langsung pakai |

### Catatan konsistensi penting

- **"Tanpa login" vs Filament.** "Tanpa login" berlaku untuk **end-user publik** — pengunjung tidak perlu daftar/masuk untuk mencari resep. **Admin tetap login** melalui Filament (satu akun admin). Keduanya tidak bertentangan: berbeda peran. Filament memang mewajibkan autentikasi, dan itu hanya untuk admin.
- **Laravel ≠ serverless.** `prompt-init.md` menyebut serverless functions (Netlify/Vercel), tetapi itu dirancang untuk Node.js dan tidak cocok untuk Laravel. Karena backend dipilih Laravel, deployment memakai **PaaS free tier**, bukan serverless.

## Arsitektur Inti: Alur Dua Jalur

Ini adalah jantung aplikasi.

```
User input bahan  ──►  [Jalur 1] Cari di DB lokal (match-score)
                            │
                            ├─ ada hasil bagus ──► tampilkan hasil terurut
                            │
                            └─ tombol "Eksplor dengan AI" (Jalur 2)
                                    │
                                    ▼
                        Kirim bahan ke Gemini  ──►  parse JSON resep
                                    │
                                    ▼
                        Tampilkan  +  Simpan ke DB (source = 'ai')
                            (resep AI ikut terindeks untuk pencarian berikutnya
                             → basis data makin kaya dari waktu ke waktu)
```

### Jalur 1 — Pencarian DB lokal (match-score)

- Resep diperingkat berdasarkan rasio bahan yang dimiliki pengguna vs bahan yang dibutuhkan resep.
  Contoh: punya 7 dari 9 bahan → skor 78%.
- **Partial match diizinkan** — tidak harus 100%. Hasil diurut dari skor tertinggi ke terendah.
- **Threshold minimal** dapat dikonfigurasi (default ≥50%) agar hasil tidak terlalu noisy.
- Hasil menampilkan bahan yang **cocok** dan bahan yang **kurang** per resep, supaya pengguna tahu apa yang masih perlu dibeli.

### Jalur 2 — Eksplorasi via Gemini

- Dipicu eksplisit oleh tombol "Eksplor dengan AI" (menghindari panggilan API tak perlu → hemat kuota).
- Kirim daftar bahan pengguna ke Gemini dengan prompt yang meminta **output JSON terstruktur** (nama, daftar bahan, langkah, porsi).
- Parse & validasi JSON respons. Tangani kasus gagal/format tidak valid dengan retry/fallback.
- Tampilkan ke pengguna, lalu **simpan ke DB** dengan `source = 'ai'`. Resep AI otomatis ikut terindeks untuk pencarian Jalur 1 berikutnya.
- **Dedup:** cek nama/bahan agar resep AI yang sangat mirip tidak menumpuk.

## Skema Data (Inti)

- **`recipes`** — `id`, `name`, `instructions`, `image_url` (**nullable**), `source` (`seed` | `ai`), `servings`, timestamps.
- **`ingredients`** — `id`, `name` (ter-normalisasi: lowercase, trim, singular), **unik**.
- **`recipe_ingredient`** (pivot) — `recipe_id`, `ingredient_id`, `quantity`/`unit` (opsional). Inilah yang membuat perhitungan match-score efisien lewat query agregasi.
- **`ratings`** — `recipe_id`, `value`, timestamps. Anonim/agregat (tanpa user_id, karena tanpa login). Opsional: simpan rate-limit sederhana berbasis sesi untuk mengurangi spam.

Normalisasi nama bahan penting agar "Tomat", "tomat", dan "tomato" tidak terpecah menjadi entri berbeda.

## Strategi PWA & Offline (Realistis)

Pencarian membutuhkan PostgreSQL di server → **fitur pencarian tidak dapat berjalan offline**. Maka scope offline dibatasi secara jujur:

- **App shell** (HTML kerangka, CSS, JS) di-cache via service worker → aplikasi tetap terbuka saat offline.
- **Halaman detail resep yang terakhir dibuka** di-cache → tetap bisa dibaca offline (berguna saat memasak tanpa sinyal).
- **Manifest + installable** (Add to Home Screen) dengan ikon & nama aplikasi.
- Strategi cache: app shell pakai cache-first; data dinamis pakai network-first dengan fallback ke cache untuk halaman yang sudah dikunjungi.

## Risiko & Hal yang Diantisipasi

- **Resep AI tanpa gambar.** Gemini mengembalikan teks, bukan gambar → `image_url` resep AI akan `null`. Sediakan **placeholder default** (ditangani di Phase 3 "image handling").
- **Cold start PaaS.** Web service free tier (mis. Render) "tidur" saat idle → request pertama lambat. Diterima sebagai trade-off zero-budget; bisa dimitigasi dengan loading state yang jelas.
- **Biaya & kuota Gemini.** Bahkan free tier punya batas. Picu panggilan hanya saat eksplisit, cache hasil, dan pantau pemakaian. Angka kuota **diverifikasi saat build** (bisa berubah).
- **Limit Neon/Render.** Batas free tier (storage, koneksi, jam aktif) **diverifikasi saat build**, bukan diasumsikan dari angka yang mungkin sudah usang.
- **Keamanan kredensial.** API key Gemini & kredensial DB hanya di `.env`/secret manager PaaS — **tidak pernah** di kode frontend.

---

# Fase Pengembangan

Mengikuti kerangka 4 fase dari `prompt-init.md`, dikonkretkan dengan keputusan di atas.

## Phase 1 — Fondasi Database & UI Inti

**Objective:** Membangun basis data resep, input bahan pengguna, dan pencarian DB lokal dengan match-score.

**Tasks:**
- Inisialisasi proyek Laravel; koneksikan ke PostgreSQL (Neon untuk staging, atau Postgres lokal saat dev).
- Migrasi skema: `recipes`, `ingredients`, `recipe_ingredient`, `ratings`.
- Normalisasi nama bahan (lowercase/trim) saat input/seed.
- Pasang **Filament**; buat resource CRUD `Recipe` (termasuk pengelolaan bahan & upload gambar) dengan satu akun admin.
- Seeder resep awal (beberapa resep contoh) agar pencarian bisa diuji.
- UI input bahan dengan **Livewire** (tambah/hapus bahan dinamis, autocomplete dari tabel `ingredients`).
- Implement **logika match-score** (query agregasi pivot, urut by skor, threshold konfigurabel).
- Halaman daftar hasil + halaman detail resep dasar (tampilkan bahan cocok vs kurang).

**Technologies:** Laravel, PostgreSQL, Livewire, Filament, Blade.

## Phase 2 — Fitur PWA & Integrasi Gemini

**Objective:** Menjadikan aplikasi PWA dan menambah rekomendasi AI via Gemini.

**Tasks:**
- **PWA:** web manifest, ikon, registrasi service worker; caching app shell + halaman detail terakhir; installable.
- **Integrasi Gemini:**
  - Konfigurasi kredensial API di `.env` (server-side saja).
  - Service untuk mengirim daftar bahan → Gemini, meminta **output JSON terstruktur**.
  - Parsing, validasi, dan penanganan error/format tidak valid (retry/fallback).
- Simpan resep AI ke DB (`source = 'ai'`, `image_url = null`) + **dedup**.
- Tombol "Eksplor dengan AI" di UI; resep AI otomatis ikut pencarian Jalur 1 berikutnya.

**Technologies:** Laravel, Gemini API, Service Worker, Web Manifest, Livewire.

## Phase 3 — Penyempurnaan UX & Persiapan Deployment

**Objective:** Memoles pengalaman pengguna dan menyiapkan rilis.

**Tasks:**
- Loading state (terutama untuk panggilan Gemini & cold start), error handling yang ramah.
- Halaman detail resep yang rapi; **placeholder gambar** untuk resep AI tanpa gambar.
- Fitur **rating** resep (anonim/agregat) + rate-limit sederhana berbasis sesi.
- Optimasi query match-score (index pada pivot & `ingredients.name`) dan caching hasil Gemini.
- Pengujian menyeluruh: logika match, alur AI, fitur PWA (offline, installable).
- Riset & pilih solusi deployment free tier final.

**Technologies:** Livewire/Blade, framework testing Laravel (Pest/PHPUnit).

## Phase 4 — Deployment & Pasca-Rilis

**Objective:** Deploy PWA dan pantau performanya.

**Tasks:**
- Deploy Laravel ke **PaaS free tier** (Render/Fly.io/Railway); hubungkan ke **Neon Postgres**.
- Konfigurasi secret (Gemini key, DB) via secret manager PaaS.
- Jalankan migrasi + seeder di lingkungan produksi.
- Monitoring dasar error & performa (log PaaS + opsi gratis seperti Sentry free tier).
- Kumpulkan feedback pengguna untuk iterasi berikutnya.

**Technologies:** Render/Fly.io/Railway, Neon, tooling monitoring gratis.

---

# Rekomendasi Deployment (Mendekati Gratis)

Karena backend adalah **Laravel (PHP)** — bukan Node.js — opsi serverless (Netlify/Vercel Functions) **tidak cocok**. Pendekatan yang dipilih:

**Laravel di PaaS free tier + PostgreSQL di Neon.**

- **Backend:** Render / Fly.io / Railway (free tier). Deploy aplikasi Laravel utuh (web + Filament admin + endpoint Gemini).
- **Database:** **Neon** (PostgreSQL free tier) — terkelola, terpisah dari PaaS sehingga data aman saat redeploy.
- **Aset statis & PWA:** dilayani langsung oleh Laravel (atau CDN gratis bila perlu).

**Trade-off yang diterima:** web service free tier "tidur" saat idle → cold start pada request pertama. Mitigasi: loading state yang jelas; opsional ping berkala bila penyedia mengizinkan.

> Semua batas free tier (Gemini, Neon, PaaS) **diverifikasi saat build**, karena angka & kebijakan dapat berubah.
