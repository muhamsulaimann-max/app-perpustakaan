# Sistem Perpustakaan Digital Kampus

Aplikasi perpustakaan berbasis Laravel 12 untuk mengelola buku, kategori, anggota, dan peminjaman. Aplikasi menyediakan halaman web yang dilindungi login serta REST API JSON untuk buku, anggota, peminjaman, dan statistik.

## Persyaratan

- PHP 8.2 atau lebih baru dan ekstensi PHP yang dibutuhkan Laravel (termasuk `pdo_sqlite`)
- Composer
- Git

## Instalasi

```powershell
git clone <URL-REPOSITORY>
cd app-perpustakaan
composer install
Copy-Item .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
```

Konfigurasi `.env.example` sudah menggunakan SQLite, locale Faker Indonesia (`id_ID`), dan alamat API internal `http://127.0.0.1:8011`. Jika mengubah alamat atau port server API internal, sesuaikan nilai `INTERNAL_API_URL` di `.env`.

> **Perhatian:** `php artisan migrate:fresh --seed` menghapus semua tabel dan data pada database yang sedang dikonfigurasi, lalu membuat ulang database demo. Gunakan hanya jika memang ingin mereset data.

## Menjalankan aplikasi

Dashboard dan laporan mengambil data melalui HTTP dari API internal. Jalankan dua server Laravel di dua terminal berbeda dari folder proyek.

**Terminal 1 — halaman web:**

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2 — API internal:**

```powershell
php artisan serve --host=127.0.0.1 --port=8011
```

Buka `http://127.0.0.1:8000/login`. Akun admin demo:

- Email: `admin@pens.ac.id`
- Password: `password`

Seeder juga menyediakan akun petugas `petugas1@pens.ac.id` dan `petugas2@pens.ac.id`, keduanya dengan password `password`. Ganti kredensial demo sebelum aplikasi dipakai di lingkungan selain lokal.

## Halaman dan endpoint

- `/` — Dashboard statistik
- `/books`, `/members`, `/loans` — halaman pengelolaan
- `/loans/report` — laporan peminjaman dari API internal
- `/api/books` — daftar, detail, dan operasi buku
- `/api/members` — daftar dan detail anggota
- `/api/loans` — daftar dan pembuatan peminjaman
- `/api/loans/{id}/kembalikan` — proses pengembalian melalui API
- `/api/stats` — ringkasan statistik JSON

Endpoint API pada praktikum ini belum memakai autentikasi token dan ditujukan untuk pengembangan lokal.

## Pengujian

```powershell
php artisan test
```

Skenario demonstrasi langkah demi langkah tersedia di [DEMO.md](./DEMO.md).
