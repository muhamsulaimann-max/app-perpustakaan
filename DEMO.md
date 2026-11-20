# Skenario Demo Aplikasi Perpustakaan

## Persiapan

1. Pastikan database sudah dimigrasi dan diisi data demo. Untuk mengulang dari awal, jalankan `php artisan migrate:fresh --seed` hanya jika data lokal boleh dihapus.
2. Jalankan server web pada port `8000` dan API internal pada port `8011` di dua terminal berbeda. Langkah detail ada di [README.md](./README.md).
3. Buka `http://127.0.0.1:8000/login` dan login dengan `admin@pens.ac.id` / `password`.

## Alur presentasi

1. **Login dan Dashboard** — tunjukkan bahwa setelah login pengguna diarahkan ke Dashboard, nama pengguna tampil pada navbar, dan kartu memperlihatkan jumlah buku, anggota, serta peminjaman aktif.
2. **Tambah kategori** — buka menu Kategori, buat kategori demo (misalnya `Koleksi Demo`), lalu tunjukkan bahwa kategori tersimpan.
3. **Tambah buku** — buka menu Buku, tambahkan buku demo dan pilih kategori yang baru dibuat. Tunjukkan buku tersebut di daftar.
4. **Tambah anggota** — buka menu Anggota dan tambahkan anggota demo dengan NIM dan email unik.
5. **Buat peminjaman** — buka menu Peminjaman, pilih anggota dan satu atau lebih buku, lalu simpan transaksi.
6. **Lihat peminjaman aktif** — pada daftar Peminjaman, temukan transaksi yang baru dibuat dan tunjukkan status `Dipinjam`, nama anggota, petugas, dan buku.
7. **Proses pengembalian** — buka Edit pada transaksi tersebut, ubah status menjadi `Dikembalikan`, simpan, lalu pastikan status berubah pada daftar. API juga menyediakan endpoint `PUT /api/loans/{id}/kembalikan`.
8. **Laporan** — buka menu Laporan. Tunjukkan data peminjaman dari API internal beserta anggota, petugas, buku, status, dan navigasi halaman jika datanya lebih dari satu halaman.
9. **Demo API di Postman** — gunakan base URL `http://127.0.0.1:8011/api` dan header `Accept: application/json`. Coba `GET /stats`, `GET /books`, `GET /loans`, serta `GET /books/999` untuk menunjukkan JSON dan respons `404`.

## Catatan

- Data contoh nama dan buku dihasilkan Seeder/Factory; nama dan jumlah peminjaman aktif dapat berbeda setiap kali database di-seed.
- Jika server API internal berhenti, Dashboard dan Laporan tetap dapat dibuka dan menampilkan peringatan bahwa data API tidak tersedia.
- Kredensial seeder hanya untuk demo lokal. Jangan gunakan password tersebut untuk deployment.
