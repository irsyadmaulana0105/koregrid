# KoreGrid

Portal B2B rantai pasok multi-gudang (Enterprise B2B Supply Chain & Multi-Warehouse Portal).

Proyek portofolio yang menunjukkan rekayasa perangkat lunak, DevOps, dan keamanan sistem dalam satu domain B2B: pembeli korporat memesan dari beberapa gudang dengan harga sesuai tier perusahaannya, pesanan melewati persetujuan Finance dan proses gudang, lalu berakhir dengan invoice.

## Fitur Utama

1. **Harga bertingkat dan MOQ.** Harga per tier perusahaan (Gold, Silver, Bronze) dan syarat Minimum Order Quantity per produk, dihitung dari total jumlah di semua gudang. Harga selalu ditentukan server; klien hanya mengirim produk, gudang, dan jumlah.
2. **Penguncian stok.** Stok gudang terkunci otomatis saat draft PO dibuat sehingga tidak terjadi penjualan berlebih.
3. **Alur persetujuan end-to-end.** Draft -> menunggu persetujuan Finance -> disetujui -> dikemas -> dikirim -> selesai, dengan invoice yang bisa dicetak. Pemisahan tugas berlaku: pembuat PO tidak dapat menyetujui PO-nya sendiri.
4. **RBAC dan jejak audit.** Super Admin, Finance, Staf Gudang, dan B2B Buyer, dengan log untuk perubahan data sensitif serta login berhasil dan gagal.

## Tech Stack

| Area | Teknologi |
|---|---|
| Aplikasi | Laravel 12 (PHP 8.4), Vue 3, Inertia.js, Tailwind CSS 4, Vite |
| Data | PostgreSQL 16, Redis 7 (cache, sesi, antrean) |
| Infrastruktur | Docker Compose (app, queue, scheduler, nginx, postgres, redis), GitHub Actions |
| Keamanan | RBAC (Spatie), audit trail, rate limiting, sanitasi input, security headers |
| Kualitas | Pest, PHPStan, Laravel Pint, tes arsitektur |

## Quick Start

Prasyarat: Docker dengan Docker Compose. Contoh di bawah untuk Linux atau WSL; sesuaikan `user` pada `docker-compose.override.yml` dengan hasil `id -u`:`id -g` jika bukan 1000:1000.

```bash
git clone https://github.com/irsyadmaulana0105/koregrid.git
cd koregrid

# 1. Konfigurasi
cp .env.example .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$(openssl rand -hex 16)/" .env
sed -i "s/^REDIS_PASSWORD=.*/REDIS_PASSWORD=$(openssl rand -hex 16)/" .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env

# 2. Dependensi front-end (sebelum container dijalankan)
docker compose run --rm node npm install

# 3. Jalankan layanan
docker compose up -d --build

# 4. Dependensi PHP, migrasi, dan data demo
docker compose exec app composer install
# Worker antrean dan scheduler menyala sebelum vendor/ ada; mulai ulang agar langsung berjalan
docker compose restart queue scheduler
docker compose exec app php artisan migrate --seed
```

Buka http://localhost:8081. Vite dev server berjalan di port 5173 (hanya dapat diakses dari mesin lokal).

## Akun Demo

Hanya dibuat di lingkungan local dan testing. Semua kata sandi: `password`.

| Peran | Email |
|---|---|
| B2B Buyer (tier Gold, PT Maju Jaya) | buyer.gold@koregrid.test |
| Finance | finance@koregrid.test |
| Staf Gudang Jakarta | warehouse.jkt@koregrid.test |
| Staf Gudang Surabaya | warehouse.sby@koregrid.test |

Alur uji cepat: masuk sebagai buyer, pilih produk di Katalog dan buat draft PO, ajukan dari halaman detail; masuk sebagai Finance dan setujui dari Antrean; masuk sebagai staf gudang, kemas dan kirim dengan nomor resi; kembali sebagai buyer, konfirmasi diterima, lalu buka dan cetak invoice.

## Pengembangan dan Pengujian

```bash
docker compose exec app composer test              # seluruh tes (Pest)
docker compose exec app vendor/bin/pint --test     # gaya kode
docker compose exec app vendor/bin/phpstan analyse # analisis statis
```

CI (GitHub Actions) menjalankan pemeriksaan yang sama pada setiap push ke `main`.

## Struktur Kode

- `app/Domain`: aturan bisnis dan pengecualian domain (MOQ, stok, state machine PO).
- `app/Application`: aksi dan query per modul (Catalog, Ordering).
- `app/Http`: controller, form request, middleware, presenter. Lapisan ini tidak memakai `DB` facade; aturan itu dijaga oleh tes arsitektur.
- `resources/js`: halaman Vue (Inertia) dan layout.

## Keamanan

- Login dibatasi 5 percobaan per menit untuk kombinasi email dan IP, dengan pesan galat yang sama untuk semua jenis kegagalan.
- Sesi diregenerasi saat login dan di-invalidate saat logout.
- Header keamanan (CSP, HSTS, dan lainnya) serta ID unik per request.
- PO milik perusahaan lain dijawab 404, bukan 403, agar keberadaannya tidak bocor.
- Input teks bebas dibersihkan dari tag HTML sebelum divalidasi.

## Dokumentasi

Lihat [`docs/`](docs/README.md): [ARCHITECTURE.md](docs/ARCHITECTURE.md) untuk topologi, alur request, dan matriks RBAC; [DATA-MODEL.md](docs/DATA-MODEL.md) untuk skema tabel, aturan stock locking, dan state machine PO.

## Status

- [x] Tahap 1: inisiasi dan dokumentasi arsitektur
- [x] Tahap 2 sampai 6b: Laravel, migrasi, domain, aturan harga dan stok, alur PO, RBAC, audit
- [x] Tahap 6c: login, katalog, daftar dan detail PO, aksi workflow, antrean, invoice cetak
- [x] Dockerfile production (image nginx terpisah, cache konfigurasi dan rute, cookie sesi aman)
- [x] Penyematan versi GitHub Actions dan Dependabot
