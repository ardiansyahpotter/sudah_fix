Cara menjalankan sistem klinik sederhana:

1. Letakkan folder proyek ini di dalam folder XAMPP htdocs, misalnya:
   C:\xampp\htdocs\rhinelab

2. Pastikan XAMPP Apache dan MySQL sudah dijalankan.

3. Buka browser dan akses:
   http://localhost/rhinelab/

Halaman utama menampilkan slot tersedia, jumlah pasien terdaftar, dan estimasi
nomor antrean. Pengunjung menghubungi admin untuk reservasi; nomor antrean resmi
ditetapkan oleh admin atau dokter pada halaman petugas.

4. Halaman admin:
   http://localhost/rhinelab/admin

5. Halaman dokter:
   http://localhost/rhinelab/dokter

Login:
   http://localhost/rhinelab/login

URL dengan ekstensi PHP juga dapat digunakan, misalnya:
   http://localhost/rhinelab/admin.php
   http://localhost/rhinelab/dokter.php
   http://localhost/rhinelab/login.php

Pengaturan database:
- Koneksi database tersedia di file `config.php`.
- Nama database: `klinik_sederhana`
- User default: `root`
- Password default: kosong

Pengisian data awal:
- Aplikasi akan membuat database dan tabel otomatis jika belum ada.
- Data dokter dan jadwal awal juga akan ditambahkan secara otomatis.

Catatan penting:
- Jika ingin mengganti nomor admin WhatsApp, ubah nilai `ADMIN_PHONE` di file `config.php`.
- Jika database bermasalah, jalankan `schema.sql` di phpMyAdmin atau MySQL untuk membuat ulang struktur tabel.

File penting:
- `config.php` — konfigurasi koneksi MySQL
- `data.php` — lapisan data yang menghubungkan aplikasi ke database
- `index.php` — halaman pasien
- `rekam_medis.php` — pengelolaan rekam medis dan riwayat kunjungan
- `admin.php` — halaman admin
- `dokter.php` — halaman dokter
- `assets/style.css` — gaya tampilan UI

ID dokter / doctor123
admin / admin123
