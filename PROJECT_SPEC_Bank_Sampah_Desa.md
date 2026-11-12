# PROJECT SPEC: Web Bank Sampah Desa

> Dokumen ini adalah spesifikasi lengkap untuk AI coding assistant (Claude Code, Cursor, Copilot, ChatGPT, dll.) atau developer yang akan membangun project ini. Baca seluruh dokumen sebelum menulis kode. Jika ada hal yang tidak jelas, ajukan pertanyaan singkat, atau ambil asumsi paling sederhana dan catat di bagian "Asumsi".

## 0. Cara Menggunakan Dokumen Ini (untuk AI)

1. Bangun **per tahap** sesuai bagian 11 (Fase). Jangan membangun semua fitur sekaligus.
2. Mulai dari Fase 1 (MVP). Setelah selesai dan berjalan, tunggu instruksi untuk Fase berikutnya.
3. Gunakan skema database di bagian 6 **apa adanya** (nama tabel dan kolom jangan diubah).
4. Patuhi bagian 9 (Batasan: Yang TIDAK Boleh Dibuat).
5. Seluruh teks antarmuka (label, tombol, pesan error) memakai **Bahasa Indonesia** sehari-hari. Nama tabel/kolom memakai Bahasa Indonesia seperti di skema. Nama variabel/fungsi di kode boleh bahasa Inggris, konsisten.
6. Setelah tiap fase, berikan: struktur folder, cara instalasi, cara menjalankan, dan akun demo.
7. **Pembagian kerja:** backend (Laravel, REST API) dikerjakan oleh satu developer backend; frontend (React) dikerjakan oleh tim terpisah. Karena itu **kontrak API (bagian 7) adalah dokumen penghubung utama**. Jika kamu mengerjakan backend, jangan membuat tampilan Blade; jika kamu mengerjakan frontend, jangan mengubah kontrak API tanpa kesepakatan.

## 1. Ringkasan Proyek

**Nama:** Web Bank Sampah Desa
**Tujuan:** Aplikasi web untuk mencatat tabungan sampah warga desa: setoran sampah, saldo, penarikan tunai, penjemputan sampah, stok, dan penjualan ke pengepul, beserta laporan.
**Lingkup:** satu desa (satu bank sampah). Bukan multi-tenant.
**Pengguna:** warga lintas usia (muda sampai lanjut usia) dan pengurus bank sampah.

### Prinsip Desain Utama
- **Sederhana di atas segalanya.** Banyak pengguna lansia yang kurang akrab dengan teknologi.
- **Petugas sebagai penghubung utama.** Warga yang gaptek cukup menyetor sampah, petugas yang menginput. Web nasabah bersifat opsional bagi yang mau.
- **Transparan dan dapat diaudit.** Setiap perubahan saldo tercatat di buku besar (`mutasi_saldo`).
- **Ringan.** Akses internet desa bisa tidak stabil. Hindari aset berat.

## 2. Peran Pengguna (Role)

| Role | Nilai di `users.role` | Deskripsi |
|---|---|---|
| Nasabah | `nasabah` | Warga penabung. Melihat saldo/riwayat/harga, mengajukan penarikan dan penjemputan. |
| Petugas | `petugas` | Menimbang dan menginput setoran, kelola penjemputan, rekap harian. |
| Admin | `admin` | Mengelola semuanya: pengguna, harga, persetujuan penarikan, stok, penjualan, laporan, artikel. |

Pengepul (pembeli sampah) **bukan** pengguna login. Hanya data master yang dicatat admin.

Hak akses: gunakan middleware/guard berbasis role. Nasabah hanya boleh mengakses data miliknya sendiri.

## 3. Fitur per Peran

### 3.1 Nasabah
- Registrasi, login, edit profil. **Login memakai nomor HP atau nomor rekening nasabah** (email opsional, tidak wajib).
- Dashboard: saldo, total berat sampah yang pernah disetor, 5 setoran terakhir.
- Riwayat setoran (dengan detail per jenis sampah) dan riwayat penarikan.
- Daftar harga sampah per kategori (harga berlaku saat ini).
- Pengajuan penarikan saldo **tunai**: input jumlah, tidak boleh melebihi saldo tersedia.
- Permintaan penjemputan: alamat, titik lokasi di peta (opsional), jadwal yang diinginkan.
- Lihat notifikasi dan artikel edukasi sederhana.
- Kartu nasabah dengan QR Code (dapat dicetak).

### 3.2 Petugas
- Input setoran: pilih nasabah (cari nama/no rekening atau scan QR), tambah baris jenis sampah + berat, nilai dihitung otomatis dari harga berlaku.
- Cetak bukti setoran (halaman print-friendly atau PDF).
- Kelola penjemputan: lihat daftar, ubah status, tugaskan diri sendiri.
- Rekap setoran harian.
- Menyerahkan uang tunai untuk penarikan yang sudah disetujui admin dan menandai selesai (bila alur memakai langkah ini, lihat 5.3).

### 3.3 Admin
- Manajemen pengguna (tambah, ubah, nonaktifkan, reset password) dan role.
- Manajemen kategori dan jenis sampah, termasuk harga per kg. Perubahan harga otomatis menambah baris di `riwayat_harga`.
- Persetujuan atau penolakan penarikan saldo.
- Stok sampah dan pencatatan penjualan ke pengepul (stok berkurang saat dijual).
- Manajemen data pengepul.
- Laporan (filter periode): setoran per periode, per jenis sampah, per nasabah, penarikan, saldo beredar. Ekspor Excel dan PDF.
- Manajemen artikel edukasi dan pengumuman.

### 3.4 Fitur Inovasi (Fase 3)
- QR Code nasabah untuk mempercepat input setoran.
- Grafik dampak lingkungan (total kg sampah yang dikelola, per jenis, tren bulanan).
- Peta titik penjemputan (Leaflet + OpenStreetMap, gratis tanpa API key).

## 4. Aturan Bisnis Penting (WAJIB DIPATUHI)

1. **Harga historis:** saat setoran disimpan, `detail_setoran.harga_saat_itu` diisi dari `jenis_sampah.harga_per_kg` saat itu. Perubahan harga di kemudian hari TIDAK boleh mengubah transaksi lama.
2. **Perhitungan:** `subtotal = berat * harga_saat_itu`; `total_berat = SUM(berat)`; `total_nilai = SUM(subtotal)`. Hitung di server, jangan percaya angka dari klien.
3. **Saldo = buku besar:** setiap perubahan `nasabah.saldo` WAJIB disertai satu baris `mutasi_saldo` dengan `saldo_akhir` yang benar. Saldo tidak boleh diubah langsung tanpa mutasi.
4. **Transaksi database atomik:** proses setoran (insert transaksi, detail, update saldo, insert mutasi, update stok) dan proses penarikan (kurangi saldo, insert mutasi) harus dalam **satu DB transaction** dengan rollback bila gagal. Gunakan row lock (`SELECT ... FOR UPDATE`) pada baris nasabah agar saldo konsisten saat ada akses bersamaan.
5. **Saldo tidak boleh negatif.** Tolak penarikan bila `jumlah > saldo`.
6. **Pembayaran hanya tunai.** Tidak ada payment gateway, e-wallet, atau transfer bank di sistem. Saldo dan jumlah penarikan tetap tercatat dan tertampil.
7. **Pembulatan:** nilai rupiah disimpan sebagai integer (rupiah penuh) atau `DECIMAL(15,2)`. Berat memakai `DECIMAL(10,2)` (kg). Jangan memakai float.
8. **Soft delete / nonaktif:** jangan hapus permanen data yang punya transaksi. Gunakan kolom `status`.
9. **Stok sampah:** setoran menambah `stok_sampah.berat_tersedia`; penjualan ke pengepul menguranginya. Tolak penjualan melebihi stok.
10. **Nomor rekening nasabah:** unik, dibuat otomatis (contoh format `BS-0001`, `BS-0002`, ...).

## 5. Alur Utama

### 5.1 Setoran
1. Petugas pilih nasabah (cari atau scan QR).
2. Petugas isi baris: jenis sampah + berat (bisa banyak baris).
3. Sistem menampilkan subtotal per baris dan total secara langsung.
4. Simpan, lalu dalam satu DB transaction: buat `transaksi_setoran` + `detail_setoran`, tambah `nasabah.saldo`, buat `mutasi_saldo` (tipe `kredit`, referensi `setoran:{id}`), tambah `stok_sampah`.
5. Buat notifikasi untuk nasabah, tampilkan bukti setoran yang bisa dicetak.

### 5.2 Penjemputan
`menunggu` -> `dijadwalkan` -> `selesai` (atau `dibatalkan`). Saat penjemputan selesai, petugas menginput setoran seperti alur 5.1 (opsional tautkan referensi).

### 5.3 Penarikan Tunai
1. Nasabah mengajukan penarikan (`status = pending`). Saldo belum berkurang.
2. Admin menyetujui (`disetujui`) atau menolak (`ditolak`, beri catatan).
3. Saat disetujui: dalam satu DB transaction, validasi saldo, kurangi `nasabah.saldo`, buat `mutasi_saldo` (tipe `debit`, referensi `penarikan:{id}`), isi `tanggal_proses` dan `diproses_oleh`.
4. Petugas/admin menyerahkan uang tunai secara fisik.
5. Buat notifikasi untuk nasabah.

> Opsi lebih sederhana: petugas/admin bisa langsung mencatat penarikan atas nama nasabah (untuk warga yang tidak memakai web), langsung berstatus `disetujui`.

## 6. Skema Database (MySQL 8 / MariaDB, utf8mb4)

```sql
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(150) NULL UNIQUE,
  no_hp VARCHAR(20) NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('nasabah','petugas','admin') NOT NULL DEFAULT 'nasabah',
  alamat TEXT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE nasabah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  no_rekening VARCHAR(20) NOT NULL UNIQUE,
  qr_code VARCHAR(255) NULL,
  saldo DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE kategori_sampah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(100) NOT NULL,
  deskripsi TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE jenis_sampah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kategori_id BIGINT UNSIGNED NOT NULL,
  nama_jenis VARCHAR(100) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT 'kg',
  harga_per_kg DECIMAL(15,2) NOT NULL DEFAULT 0,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  FOREIGN KEY (kategori_id) REFERENCES kategori_sampah(id)
) ENGINE=InnoDB;

CREATE TABLE riwayat_harga (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jenis_sampah_id BIGINT UNSIGNED NOT NULL,
  harga DECIMAL(15,2) NOT NULL,
  berlaku_mulai DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (jenis_sampah_id) REFERENCES jenis_sampah(id)
) ENGINE=InnoDB;

CREATE TABLE transaksi_setoran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nasabah_id BIGINT UNSIGNED NOT NULL,
  petugas_id BIGINT UNSIGNED NOT NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_berat DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
  catatan TEXT NULL,
  FOREIGN KEY (nasabah_id) REFERENCES nasabah(id),
  FOREIGN KEY (petugas_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE detail_setoran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setoran_id BIGINT UNSIGNED NOT NULL,
  jenis_sampah_id BIGINT UNSIGNED NOT NULL,
  berat DECIMAL(10,2) NOT NULL,
  harga_saat_itu DECIMAL(15,2) NOT NULL,
  subtotal DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (setoran_id) REFERENCES transaksi_setoran(id),
  FOREIGN KEY (jenis_sampah_id) REFERENCES jenis_sampah(id)
) ENGINE=InnoDB;

CREATE TABLE penarikan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nasabah_id BIGINT UNSIGNED NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  status ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending',
  tanggal_ajuan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_proses DATETIME NULL,
  diproses_oleh BIGINT UNSIGNED NULL,
  catatan TEXT NULL,
  FOREIGN KEY (nasabah_id) REFERENCES nasabah(id),
  FOREIGN KEY (diproses_oleh) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE penjemputan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nasabah_id BIGINT UNSIGNED NOT NULL,
  petugas_id BIGINT UNSIGNED NULL,
  alamat TEXT NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  jadwal DATETIME NOT NULL,
  status ENUM('menunggu','dijadwalkan','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu',
  FOREIGN KEY (nasabah_id) REFERENCES nasabah(id),
  FOREIGN KEY (petugas_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE mutasi_saldo (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nasabah_id BIGINT UNSIGNED NOT NULL,
  tipe ENUM('debit','kredit') NOT NULL,
  jumlah DECIMAL(15,2) NOT NULL,
  saldo_akhir DECIMAL(15,2) NOT NULL,
  referensi VARCHAR(50) NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (nasabah_id) REFERENCES nasabah(id)
) ENGINE=InnoDB;

CREATE TABLE stok_sampah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jenis_sampah_id BIGINT UNSIGNED NOT NULL UNIQUE,
  berat_tersedia DECIMAL(12,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (jenis_sampah_id) REFERENCES jenis_sampah(id)
) ENGINE=InnoDB;

CREATE TABLE pengepul (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  kontak VARCHAR(50) NULL,
  alamat TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE penjualan_pengepul (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengepul_id BIGINT UNSIGNED NOT NULL,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total_berat DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_nilai DECIMAL(15,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (pengepul_id) REFERENCES pengepul(id)
) ENGINE=InnoDB;

CREATE TABLE artikel_edukasi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penulis_id BIGINT UNSIGNED NOT NULL,
  judul VARCHAR(200) NOT NULL,
  isi TEXT NOT NULL,
  gambar VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (penulis_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifikasi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  judul VARCHAR(150) NOT NULL,
  pesan TEXT NOT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
```

Catatan skema:
- Disarankan menambahkan indeks pada `transaksi_setoran(nasabah_id, tanggal)`, `mutasi_saldo(nasabah_id, tanggal)`, `penarikan(status)`.
- Catatan implementasi: `penjualan_pengepul` pada versi ini belum punya tabel detail per jenis sampah. Jika perlu mengurangi stok per jenis, tambahkan tabel `detail_penjualan` (id, penjualan_id, jenis_sampah_id, berat, harga) pada Fase 2 dan catat sebagai perubahan skema.

### Relasi
- users 1:1 nasabah
- nasabah 1:N transaksi_setoran, penarikan, penjemputan, mutasi_saldo
- transaksi_setoran 1:N detail_setoran
- kategori_sampah 1:N jenis_sampah
- jenis_sampah 1:N riwayat_harga, detail_setoran; jenis_sampah 1:1 stok_sampah
- users (petugas/admin) 1:N transaksi_setoran, penarikan (diproses_oleh), penjemputan, artikel_edukasi
- pengepul 1:N penjualan_pengepul
- users 1:N notifikasi

## 7. Teknologi dan Arsitektur

**Arsitektur:** *decoupled* (terpisah). Laravel sebagai **REST API (JSON)**, React sebagai **SPA** yang memanggil API.

| Lapisan | Teknologi | Pengerjaan |
|---|---|---|
| Backend | Laravel 11 (PHP 8.2+), REST API | Developer backend |
| Database | MySQL 8 (utf8mb4) | Developer backend |
| Auth | Laravel Sanctum (personal access token, Bearer) | Backend + Frontend |
| Frontend | React (Vite), React Router | Tim frontend |
| State/data fetching | TanStack Query (React Query) + Axios, atau setara | Tim frontend |
| UI | Bebas (mis. Tailwind / MUI / Bootstrap), wajib ikut panduan bagian 8 | Tim frontend |

Pendukung:
- **Peta:** Leaflet + OpenStreetMap (`react-leaflet` di frontend). Tanpa API key.
- **QR Code:** backend membuat QR (mis. `simplesoftwareio/simple-qrcode`) dan menyediakan endpoint gambar/SVG; frontend memindai QR dengan `html5-qrcode`.
- **Ekspor laporan:** dibuat di backend (Laravel Excel `maatwebsite/excel`, DomPDF) dan diunduh lewat endpoint `GET /reports/.../export`.
- **Grafik:** Chart.js (`react-chartjs-2`) atau Recharts di frontend; backend hanya menyediakan data agregat JSON.
- **Notifikasi:** Fase 1-2 cukup notifikasi di dalam sistem (tabel `notifikasi`). Integrasi WhatsApp/SMS dibuat sebagai service class terpisah (interface) agar penyedia mudah diganti. Jangan hardcode penyedia tertentu.
- **Dokumentasi API:** sediakan spesifikasi **OpenAPI/Swagger** (mis. `dedoc/scramble` atau `l5-swagger`) dan/atau koleksi **Postman**, supaya tim frontend dapat bekerja paralel. Sediakan juga **data dummy/seeder**.

### 7.1 Konvensi API (WAJIB, agar frontend dan backend sinkron)

- **Base URL:** `/api/v1`. Format JSON (`Accept: application/json`).
- **Auth:** `Authorization: Bearer <token>` dari Sanctum. Token dibuat saat login, dihapus saat logout.
- **CORS:** izinkan origin frontend (dev: `http://localhost:5173`) melalui `config/cors.php` dan `.env`.
- **Format respons sukses:**
```json
{ "success": true, "message": "Setoran berhasil disimpan", "data": { } }
```
- **Respons daftar (paginasi):**
```json
{ "success": true, "data": [ ], "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 } }
```
- **Respons error validasi (HTTP 422):**
```json
{ "success": false, "message": "Data tidak valid", "errors": { "jumlah": ["Jumlah melebihi saldo"] } }
```
- **Kode HTTP:** 200/201 sukses, 401 belum login, 403 tidak punya hak akses, 404 tidak ditemukan, 422 validasi, 500 error server. Pesan error dalam Bahasa Indonesia.
- **Penamaan:** field JSON memakai `snake_case` mengikuti nama kolom database.
- **Tipe data:** uang dikirim sebagai **angka** (rupiah, tanpa simbol; frontend yang memformat `Rp 25.000`). Berat dalam kg (angka desimal 2 digit). Tanggal format ISO 8601 (`2026-11-08T09:30:00+07:00`).
- **Filter dan pencarian:** query string, contoh `?search=budi&tanggal_dari=2026-11-01&tanggal_sampai=2026-11-30&page=2&per_page=15`.
- **Otorisasi:** middleware role (`role:admin`, `role:petugas,admin`) dan *policy* kepemilikan data. Endpoint `/me/*` selalu memakai data milik user yang login, tidak menerima `nasabah_id` dari klien.
- **Gunakan** Laravel API Resource untuk membentuk output JSON, dan Form Request untuk validasi.

### 7.2 Daftar Endpoint (kontrak awal)

**Auth**
| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/auth/login` | Body: `identifier` (no HP / no rekening), `password`. Return token + data user (termasuk `role`). |
| POST | `/auth/logout` | Hapus token aktif. |
| GET | `/auth/me` | Data user login. |
| POST | `/auth/register` | Registrasi mandiri nasabah (opsional). |
| PUT | `/auth/password` | Ganti password. |

**Publik (tanpa login)**
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/public/harga-sampah` | Daftar harga per kategori (aktif). |
| GET | `/public/artikel` , `/public/artikel/{id}` | Artikel edukasi dan pengumuman. |

**Nasabah** (role `nasabah`, hanya data sendiri)
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/me/dashboard` | Saldo, total berat, 5 setoran terakhir. |
| GET | `/me/setoran` , `/me/setoran/{id}` | Riwayat setoran + detail. |
| GET | `/me/mutasi` | Riwayat mutasi saldo. |
| GET | `/me/penarikan` | Riwayat penarikan. |
| POST | `/me/penarikan` | Ajukan penarikan tunai. Body: `jumlah`. Validasi tidak melebihi saldo. |
| GET | `/me/penjemputan` | Riwayat penjemputan. |
| POST | `/me/penjemputan` | Body: `alamat`, `latitude?`, `longitude?`, `jadwal`. |
| PATCH | `/me/penjemputan/{id}/batal` | Batalkan bila masih `menunggu`. |
| GET | `/me/notifikasi` ; PATCH `/me/notifikasi/{id}/baca` | Notifikasi. |
| GET / PUT | `/me/profil` | Lihat dan ubah profil. |
| GET | `/me/kartu` | Data kartu + gambar QR (Fase 3). |

**Petugas** (role `petugas`, `admin`)
| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/nasabah?search=` | Cari nasabah (nama / no rekening / no HP). |
| GET | `/nasabah/by-qr/{kode}` | Cari nasabah dari hasil scan QR (Fase 3). |
| GET | `/jenis-sampah` | Daftar jenis sampah aktif + harga (untuk form setoran). |
| POST | `/setoran` | Body: `nasabah_id`, `catatan?`, `items: [{ jenis_sampah_id, berat }]`. Server menghitung harga dan total. |
| GET | `/setoran` , `/setoran/{id}` | Daftar dan detail setoran. |
| GET | `/setoran/{id}/bukti` | Bukti setoran (JSON atau PDF). |
| GET | `/penjemputan` | Daftar penjemputan (filter status). |
| PATCH | `/penjemputan/{id}` | Ubah status / jadwal / tugaskan petugas. |
| POST | `/penarikan/langsung` | Catat penarikan atas nama nasabah (langsung `disetujui`). |
| GET | `/rekap/harian?tanggal=` | Rekap setoran harian. |

**Admin** (role `admin`)
| Method | Endpoint | Keterangan |
|---|---|---|
| CRUD | `/admin/users` | Pengguna dan role (nonaktifkan, bukan hapus). |
| CRUD | `/admin/nasabah` | Buat nasabah (otomatis membuat user + no rekening). |
| CRUD | `/admin/kategori-sampah` , `/admin/jenis-sampah` | Ubah harga otomatis menambah `riwayat_harga`. |
| GET | `/admin/jenis-sampah/{id}/riwayat-harga` | Riwayat harga. |
| GET | `/admin/penarikan?status=` | Daftar penarikan. |
| PATCH | `/admin/penarikan/{id}/setujui` , `/tolak` | Setujui (kurangi saldo + mutasi, atomik) atau tolak (`catatan`). |
| GET | `/admin/stok` | Stok per jenis sampah. |
| CRUD | `/admin/pengepul` | Data pengepul. |
| POST / GET | `/admin/penjualan` | Catat dan lihat penjualan ke pengepul (kurangi stok). |
| GET | `/admin/laporan/setoran` , `/penarikan` , `/saldo-beredar` , `/per-jenis` | Data laporan + filter periode. |
| GET | `/admin/laporan/{jenis}/export?format=xlsx (atau pdf)` | Unduh laporan. |
| CRUD | `/admin/artikel` | Artikel dan pengumuman. |
| GET | `/admin/dashboard` | Ringkasan: total nasabah, setoran bulan ini, saldo beredar, stok. |
| GET | `/admin/dampak-lingkungan` | Data agregat untuk grafik (Fase 3). |

### 7.3 Struktur Folder yang Disarankan

```
bank-sampah/
├── backend/    # Laravel (API)
└── frontend/   # React (Vite)
```
(Boleh repository terpisah. Yang penting kontrak API konsisten.)

## 8. Panduan UI/UX (Ramah Lintas Usia) - untuk Tim Frontend React

- Ukuran huruf dasar minimal 16-18px, tombol besar (tinggi minimal 48px), kontras warna tinggi.
- Menu sedikit dan jelas, ikon disertai teks. Maksimal 5-6 menu utama per role.
- Bahasa sehari-hari, hindari istilah teknis. Contoh: "Saldo Anda", "Setor Sampah", "Tarik Uang", "Riwayat".
- Format rupiah jelas: `Rp 25.000`. Format tanggal Indonesia: `8 November 2026`.
- Desain responsif, mobile-first (mayoritas akses lewat HP).
- Pesan error ramah dan jelas, konfirmasi sebelum aksi penting (penarikan, hapus).
- Form input setoran harus cepat dipakai: kolom pencarian nasabah, tombol "Tambah Baris", total terlihat langsung.
- Halaman publik statis "Panduan Memilah Sampah" dan "Daftar Harga" yang enak dicetak sebagai poster.
- Frontend memformat uang (`Rp 25.000`) dan tanggal; API hanya mengirim angka dan ISO 8601.
- Redirect berdasarkan `role` setelah login. Tampilkan pesan error dari field `errors` pada API di bawah kolom form terkait.
- Tangani kondisi koneksi lambat/terputus: indikator loading dan pesan ulang coba yang jelas.

## 9. Batasan: Yang TIDAK Boleh Dibuat

Fitur berikut sengaja dikeluarkan. Jangan dibuat, tidak perlu ada tabel atau kolomnya:

- Sistem poin, level, leaderboard, dan penukaran reward (tidak ada tabel `reward`/`penukaran_poin`, kolom `total_poin`, `poin_per_kg`).
- Chatbot atau panduan klasifikasi sampah interaktif (edukasi dilakukan lewat sosialisasi langsung).
- Payment gateway, e-wallet, transfer bank, atau pembayaran digital apa pun. Penarikan hanya tunai.
- Multi-desa/multi-tenant.
- Fitur yang menambah kerumitan bagi pengguna lansia tanpa kebutuhan yang jelas.

## 10. Keamanan dan Kualitas

- Hash password (bcrypt/argon2). Jangan simpan password polos.
- Validasi input di server, gunakan prepared statement/ORM (cegah SQL injection), proteksi CSRF, escape output (cegah XSS).
- Otorisasi per role dan per kepemilikan data (nasabah tidak boleh melihat data nasabah lain).
- Rate limiting pada login.
- Catat siapa (user id) yang melakukan setoran, persetujuan, dan perubahan harga.
- Seed data awal: 1 akun admin, 1 petugas, beberapa nasabah demo, kategori dan jenis sampah contoh (plastik, kertas/kardus, logam, kaca) beserta harga contoh.
- Tulis test untuk logika kritis: perhitungan setoran, pembaruan saldo + mutasi, penolakan penarikan melebihi saldo, konsistensi saat transaksi gagal.
- Sediakan `README.md` berisi instalasi, konfigurasi `.env`, migrasi, seeding, dan akun demo.

## 11. Fase Pengembangan

### Fase 1: MVP (kerjakan dulu)
**Backend (Laravel API):** migrasi + seeder, Sanctum auth, endpoint Auth, `/me/*` (dashboard, setoran, mutasi, penarikan, profil), `/nasabah`, `/jenis-sampah`, `/setoran`, `/admin/nasabah`, `/admin/kategori-sampah`, `/admin/jenis-sampah`, `/admin/penarikan`, `/penarikan/langsung`, dokumentasi OpenAPI/Postman.
**Frontend (React):** halaman login, dashboard nasabah, riwayat, ajukan penarikan, form input setoran petugas, bukti setoran cetak, halaman admin untuk nasabah/harga/penarikan.

- Auth (login via no HP / no rekening), manajemen role.
- CRUD nasabah (otomatis membuat user + nomor rekening).
- CRUD kategori dan jenis sampah + harga + riwayat harga.
- Input setoran (dengan aturan bisnis bagian 4), bukti setoran cetak.
- Dashboard nasabah: saldo dan riwayat.
- Pengajuan dan persetujuan penarikan tunai.
- Buku besar `mutasi_saldo`.

**Kriteria selesai Fase 1:** dokumentasi API tersedia dan dapat dipakai tim frontend; petugas dapat menyetor sampah atas nama nasabah, saldo bertambah benar dan tercatat di mutasi; nasabah dapat melihat saldo dan riwayat; nasabah mengajukan penarikan, admin menyetujui, saldo berkurang dan tercatat; saldo tidak pernah negatif; seluruh test logika kritis lulus.

### Fase 2
- Laporan + ekspor Excel/PDF, rekap harian petugas.
- Penjemputan sampah.
- Notifikasi (di dalam sistem; antarmuka WhatsApp/SMS siap dipasang).
- Stok sampah, pengepul, penjualan ke pengepul (+ `detail_penjualan` bila diperlukan).
- Artikel edukasi dan pengumuman.

### Fase 3 (Inovasi)
- QR Code nasabah (kartu cetak) dan scan QR di form setoran.
- Grafik dampak lingkungan.
- Peta titik penjemputan.

## 12. Struktur Halaman / Route React (Ringkas)

**Publik:** `/` beranda, `/harga` daftar harga, `/panduan` panduan memilah sampah, `/login`, `/daftar`.

**Nasabah:** `/nasabah/dashboard`, `/nasabah/setoran`, `/nasabah/penarikan` (+ form ajukan), `/nasabah/penjemputan`, `/nasabah/notifikasi`, `/nasabah/profil`, `/nasabah/kartu`.

**Petugas:** `/petugas/setoran/baru`, `/petugas/setoran`, `/petugas/penjemputan`, `/petugas/rekap`.

**Admin:** `/admin/dashboard`, `/admin/pengguna`, `/admin/nasabah`, `/admin/sampah` (kategori dan jenis), `/admin/penarikan`, `/admin/stok`, `/admin/pengepul`, `/admin/penjualan`, `/admin/laporan`, `/admin/artikel`.

## 13. Asumsi (boleh diubah AI bila ada alasan kuat, catat perubahannya)

- Satu bank sampah untuk satu desa.
- Satuan utama sampah adalah kg.
- Backend dikerjakan satu developer Laravel; frontend dikerjakan tim React secara paralel berdasarkan kontrak API bagian 7.
- Zona waktu Asia/Jakarta (WIB), bahasa Indonesia, mata uang Rupiah.
- Nasabah dapat didaftarkan oleh petugas/admin (tanpa harus mendaftar sendiri), dengan password awal yang bisa diganti.

## 14. Format Jawaban yang Diharapkan dari AI

Untuk setiap fase, keluarkan (sesuaikan dengan bagian yang kamu kerjakan: backend atau frontend):
1. Daftar file yang dibuat/diubah beserta isinya.
2. Perintah instalasi dan menjalankan aplikasi.
3. Akun demo dan data seed.
4. Daftar asumsi yang diambil dan hal yang belum dikerjakan.
5. Cara menjalankan test.
6. Untuk backend: perubahan pada kontrak API (jika ada) beserta contoh request/response, agar tim frontend dapat menyesuaikan.
7. Untuk frontend: daftar endpoint yang dipakai tiap halaman dan data dummy (mock) bila backend belum siap.
