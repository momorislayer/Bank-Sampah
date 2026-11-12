<?php

namespace Database\Seeders;

use App\Models\ArtikelEdukasi;
use App\Models\JenisSampah;
use App\Models\KategoriSampah;
use App\Models\MutasiSaldo;
use App\Models\Nasabah;
use App\Models\Notifikasi;
use App\Models\Pengepul;
use App\Models\RiwayatHarga;
use App\Models\StokSampah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Admin
        $admin = User::create([
            'nama' => 'Admin Bank Sampah',
            'email' => 'admin@banksampah.desa',
            'no_hp' => '081234567890',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'alamat' => 'Kantor Balai Desa, RT 01/RW 01',
            'status' => 'aktif',
        ]);

        // 2. Petugas
        $petugas = User::create([
            'nama' => 'Petugas Lapangan',
            'email' => 'petugas@banksampah.desa',
            'no_hp' => '081234567891',
            'password' => Hash::make('password'),
            'role' => 'petugas',
            'alamat' => 'Dusun Krajan, RT 02/RW 01',
            'status' => 'aktif',
        ]);

        // 3. Nasabah Demo
        $warga1 = User::create([
            'nama' => 'Pak Budi Santoso',
            'email' => 'budi@warga.desa',
            'no_hp' => '081234567892',
            'password' => Hash::make('password'),
            'role' => 'nasabah',
            'alamat' => 'RT 03 / RW 01 Desa Sukamaju',
            'status' => 'aktif',
        ]);

        $nasabah1 = Nasabah::create([
            'user_id' => $warga1->id,
            'no_rekening' => 'BS-0001',
            'saldo' => 50000.00,
        ]);

        MutasiSaldo::create([
            'nasabah_id' => $nasabah1->id,
            'tipe' => 'kredit',
            'jumlah' => 50000.00,
            'saldo_akhir' => 50000.00,
            'referensi' => 'saldo_awal',
            'tanggal' => now(),
        ]);

        $warga2 = User::create([
            'nama' => 'Ibu Siti Aminah',
            'email' => 'siti@warga.desa',
            'no_hp' => '081234567893',
            'password' => Hash::make('password'),
            'role' => 'nasabah',
            'alamat' => 'RT 01 / RW 02 Desa Sukamaju',
            'status' => 'aktif',
        ]);

        $nasabah2 = Nasabah::create([
            'user_id' => $warga2->id,
            'no_rekening' => 'BS-0002',
            'saldo' => 25000.00,
        ]);

        MutasiSaldo::create([
            'nasabah_id' => $nasabah2->id,
            'tipe' => 'kredit',
            'jumlah' => 25000.00,
            'saldo_akhir' => 25000.00,
            'referensi' => 'saldo_awal',
            'tanggal' => now(),
        ]);

        $warga3 = User::create([
            'nama' => 'Pak Joko Widodo',
            'email' => 'joko@warga.desa',
            'no_hp' => '081234567894',
            'password' => Hash::make('password'),
            'role' => 'nasabah',
            'alamat' => 'RT 04 / RW 02 Desa Sukamaju',
            'status' => 'aktif',
        ]);

        Nasabah::create([
            'user_id' => $warga3->id,
            'no_rekening' => 'BS-0003',
            'saldo' => 0.00,
        ]);

        // 4. Kategori dan Jenis Sampah
        $kategoriList = [
            [
                'nama_kategori' => 'Plastik',
                'deskripsi' => 'Botol, wadah, dan berbagai jenis sampah plastik daur ulang',
                'jenis' => [
                    ['nama_jenis' => 'Botol Plastik PET Bersih', 'satuan' => 'kg', 'harga' => 4000],
                    ['nama_jenis' => 'Plastik Campur / Kresek', 'satuan' => 'kg', 'harga' => 1500],
                    ['nama_jenis' => 'Gelas Mineral Plastik', 'satuan' => 'kg', 'harga' => 3500],
                ],
            ],
            [
                'nama_kategori' => 'Kertas & Kardus',
                'deskripsi' => 'Kardus, kertas arsip/buku bekas, dan koran',
                'jenis' => [
                    ['nama_jenis' => 'Kardus Bekas Cokelat', 'satuan' => 'kg', 'harga' => 2200],
                    ['nama_jenis' => 'Kertas HVS / Buku Bekas', 'satuan' => 'kg', 'harga' => 1800],
                    ['nama_jenis' => 'Koran Bekas', 'satuan' => 'kg', 'harga' => 1500],
                ],
            ],
            [
                'nama_kategori' => 'Logam',
                'deskripsi' => 'Besi rongsok, seng, kaleng, dan aluminium',
                'jenis' => [
                    ['nama_jenis' => 'Besi Rongsok Tebal', 'satuan' => 'kg', 'harga' => 4500],
                    ['nama_jenis' => 'Kaleng Aluminium Minuman', 'satuan' => 'kg', 'harga' => 12000],
                    ['nama_jenis' => 'Seng / Besi Tipis', 'satuan' => 'kg', 'harga' => 2500],
                ],
            ],
            [
                'nama_kategori' => 'Kaca',
                'deskripsi' => 'Botol kaca utuh sirup, kecap, atau beling kaca',
                'jenis' => [
                    ['nama_jenis' => 'Botol Kaca Utuh', 'satuan' => 'kg', 'harga' => 1000],
                    ['nama_jenis' => 'Pecahan / Beling Kaca', 'satuan' => 'kg', 'harga' => 400],
                ],
            ],
        ];

        foreach ($kategoriList as $katData) {
            $kategori = KategoriSampah::create([
                'nama_kategori' => $katData['nama_kategori'],
                'deskripsi' => $katData['deskripsi'],
            ]);

            foreach ($katData['jenis'] as $item) {
                $jenis = JenisSampah::create([
                    'kategori_id' => $kategori->id,
                    'nama_jenis' => $item['nama_jenis'],
                    'satuan' => $item['satuan'],
                    'harga_per_kg' => $item['harga'],
                    'status' => 'aktif',
                ]);

                RiwayatHarga::create([
                    'jenis_sampah_id' => $jenis->id,
                    'harga' => $item['harga'],
                    'berlaku_mulai' => now(),
                ]);

                StokSampah::create([
                    'jenis_sampah_id' => $jenis->id,
                    'berat_tersedia' => 0.00,
                ]);
            }
        }

        // 5. Pengepul Master Data
        Pengepul::create([
            'nama' => 'UD Berkah Daur Ulang',
            'kontak' => '085712345678',
            'alamat' => 'Jl. Raya Desa No. 45, Kecamatan Makmur',
        ]);

        // 6. Artikel Edukasi
        ArtikelEdukasi::create([
            'penulis_id' => $admin->id,
            'judul' => 'Panduan Mudah Memilah Sampah dari Rumah',
            'isi' => "Memilah sampah dari rumah tidaklah sulit. Pisahkan sampah organik (sisa makanan) dan sampah anorganik (botol plastik, kardus, kaleng). Pastikan botol plastik dalam keadaan kering dan dipipihkan agar menghemat ruang penyimpanan sebelum dibawa ke Bank Sampah Desa.",
        ]);

        ArtikelEdukasi::create([
            'penulis_id' => $admin->id,
            'judul' => 'Jadwal Operasional dan Penimbangan Bank Sampah',
            'isi' => "Bank Sampah Desa melayani penyetoran setiap hari Sabtu dan Minggu pukul 08:00 - 12:00 WIB di Balai Desa. Untuk lansia dan warga yang memiliki sampah dalam jumlah besar, layanan penjemputan sampah dapat dijadwalkan melalui aplikasi atau lapor ke ketua RT.",
        ]);

        // 7. Notifikasi Demo
        Notifikasi::create([
            'user_id' => $warga1->id,
            'judul' => 'Selamat Datang di Bank Sampah Desa',
            'pesan' => 'Akun tabungan sampah Anda dengan no rekening BS-0001 telah aktif. Mulai pilah sampah dan kumpulkan pundi rupiah Anda!',
            'dibaca' => false,
        ]);
    }
}
