# Inventory Management System

## Deskripsi Proyek

Inventory Management System adalah aplikasi berbasis web yang dikembangkan menggunakan PHP dan MySQL untuk membantu pengelolaan persediaan barang. Sistem ini menyediakan fitur pengelolaan produk, kategori, supplier, hubungan produk dengan supplier, serta pencatatan transaksi stok masuk dan stok keluar.

## Fitur Utama

- **Login dan Logout:** Autentikasi pengguna dengan password yang di-hash.
- **Hak Akses Pengguna:** Terdapat role Admin dan Staff. Manajemen pengguna dibatasi untuk Admin.
- **Manajemen Kategori:** Menambah, melihat, mengedit, dan menghapus kategori.
- **Manajemen Produk:** Mengelola informasi produk, SKU, harga, stok, kategori, dan stok minimum.
- **Manajemen Supplier:** Mengelola data supplier.
- **Relasi Produk dan Supplier:** Menghubungkan produk dengan supplier beserta harga dari supplier.
- **Transaksi Stok IN/OUT:** Mencatat stok masuk dan keluar serta menyimpan riwayat transaksi.
- **Pencarian dan Pagination:** Mencari, memfilter, mengurutkan, dan menampilkan produk berdasarkan jumlah data per halaman.
- **Dashboard dan Laporan:** Menampilkan ringkasan data inventaris, transaksi terbaru, dan produk dengan stok menipis.
- **Validasi dan Keamanan:** Menggunakan validasi input, prepared statements, dan pembatasan akses sesuai role.

## Teknologi yang Digunakan

- PHP
- MySQL/MariaDB
- HTML
- CSS
- JavaScript (jika digunakan pada implementasi)
- XAMPP
- Git dan GitHub

## Persyaratan Sistem

- XAMPP dengan Apache dan MySQL/MariaDB
- Browser web
- Git (opsional, untuk mengelola kode melalui repositori)

## Cara Instalasi dan Menjalankan

1. Clone atau unduh repositori proyek ini dari GitHub.
2. Letakkan folder proyek di direktori `htdocs` XAMPP.
3. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
4. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
5. Buat database bernama `inventory_management`.
6. Impor file `schema.sql` ke database tersebut.
7. Periksa konfigurasi koneksi database pada `config/database.php` dan sesuaikan jika diperlukan.
8. Buka aplikasi melalui:

   `http://localhost/inventory_management/`

9. Login menggunakan akun yang telah dibuat pada database.

## Struktur Proyek

```text
inventory_management/
├── config/
│   └── database.php
├── index.php
├── login.php
├── logout.php
├── users.php
├── categories.php
├── products.php
├── suppliers.php
├── product_suppliers.php
├── stock.php
├── schema.sql
└── README.md
```

Struktur di atas merupakan gambaran file utama. Sesuaikan dengan file aktual di repositori jika terdapat file atau folder tambahan.

## Aturan Sistem

- SKU produk harus unik.
- Harga, stok, dan stok minimum tidak boleh bernilai negatif.
- Transaksi stok keluar tidak boleh melebihi stok yang tersedia.
- Stok tidak boleh menjadi negatif.
- Produk yang memiliki riwayat transaksi tidak dapat dihapus.
- Halaman internal memerlukan login.
- Staff tidak memiliki akses ke halaman manajemen pengguna.

## Pengujian

Pengujian dilakukan terhadap autentikasi dan hak akses, CRUD kategori, produk, dan supplier, relasi produk-supplier, transaksi stok, pencarian, filter, sorting, pagination, validasi input, keamanan akses, serta dashboard dan laporan.

Berdasarkan hasil pengujian yang dilaporkan, fitur-fitur tersebut telah berhasil diuji. Pemeriksaan akhir repositori dan sinkronisasi perubahan ke GitHub dilakukan secara terpisah.

## Pengembang

Inventory Management System — Mini Project 3
g