<?php

use App\Http\Controllers\Api\v1\Admin as Admin;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\Nasabah as Nasabah;
use App\Http\Controllers\Api\v1\Petugas as Petugas;
use App\Http\Controllers\Api\v1\PublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Bank Sampah Desa v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------
    // 1. Auth Publik
    // -------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/password', [AuthController::class, 'changePassword']);
        });
    });

    // -------------------------------------------------------------
    // 2. Publik (Tanpa Autentikasi)
    // -------------------------------------------------------------
    Route::prefix('public')->group(function () {
        Route::get('/harga-sampah', [PublicController::class, 'hargaSampah']);
        Route::get('/artikel', [PublicController::class, 'artikel']);
        Route::get('/artikel/{id}', [PublicController::class, 'artikelDetail']);
    });

    // -------------------------------------------------------------
    // 3. Nasabah (Hanya role: nasabah, data milik sendiri)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'role:nasabah'])->prefix('me')->group(function () {
        Route::get('/dashboard', [Nasabah\DashboardController::class, 'index']);
        Route::get('/setoran', [Nasabah\SetoranController::class, 'index']);
        Route::get('/setoran/{id}', [Nasabah\SetoranController::class, 'show']);
        Route::get('/mutasi', [Nasabah\MutasiController::class, 'index']);
        Route::get('/penarikan', [Nasabah\PenarikanController::class, 'index']);
        Route::post('/penarikan', [Nasabah\PenarikanController::class, 'store']);
        Route::get('/profil', [Nasabah\ProfilController::class, 'show']);
        Route::put('/profil', [Nasabah\ProfilController::class, 'update']);

        // Penjemputan Sampah
        Route::get('/penjemputan', [Nasabah\PenjemputanController::class, 'index']);
        Route::post('/penjemputan', [Nasabah\PenjemputanController::class, 'store']);
        Route::patch('/penjemputan/{id}/batal', [Nasabah\PenjemputanController::class, 'batal']);

        // Notifikasi
        Route::get('/notifikasi', [Nasabah\NotifikasiController::class, 'index']);
        Route::patch('/notifikasi/{id}/baca', [Nasabah\NotifikasiController::class, 'baca']);
    });

    // -------------------------------------------------------------
    // 4. Petugas & Admin (role: petugas,admin)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'role:petugas,admin'])->group(function () {
        Route::get('/nasabah', [Petugas\NasabahController::class, 'index']);
        Route::get('/nasabah/{id}', [Petugas\NasabahController::class, 'show']);
        Route::get('/jenis-sampah', [Petugas\JenisSampahController::class, 'index']);

        Route::get('/setoran', [Petugas\SetoranController::class, 'index']);
        Route::post('/setoran', [Petugas\SetoranController::class, 'store']);
        Route::get('/setoran/{id}', [Petugas\SetoranController::class, 'show']);
        Route::get('/setoran/{id}/bukti', [Petugas\SetoranController::class, 'bukti']);

        Route::post('/penarikan/langsung', [Petugas\PenarikanLangsungController::class, 'store']);

        // Kelola Penjemputan
        Route::get('/penjemputan', [Petugas\PenjemputanController::class, 'index']);
        Route::patch('/penjemputan/{id}', [Petugas\PenjemputanController::class, 'update']);

        // Rekap Harian
        Route::get('/rekap/harian', [Petugas\RekapController::class, 'harian']);
    });

    // -------------------------------------------------------------
    // 5. Admin (role: admin)
    // -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [Admin\DashboardController::class, 'index']);

        // Kelola Pengguna
        Route::get('/users', [Admin\UserController::class, 'index']);
        Route::post('/users', [Admin\UserController::class, 'store']);
        Route::get('/users/{id}', [Admin\UserController::class, 'show']);
        Route::put('/users/{id}', [Admin\UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-status', [Admin\UserController::class, 'toggleStatus']);
        Route::patch('/users/{id}/reset-password', [Admin\UserController::class, 'resetPassword']);

        // Kelola Nasabah
        Route::get('/nasabah', [Admin\NasabahController::class, 'index']);
        Route::post('/nasabah', [Admin\NasabahController::class, 'store']);
        Route::get('/nasabah/{id}', [Admin\NasabahController::class, 'show']);
        Route::put('/nasabah/{id}', [Admin\NasabahController::class, 'update']);

        // Kelola Kategori Sampah
        Route::apiResource('kategori-sampah', Admin\KategoriSampahController::class);

        // Kelola Jenis Sampah & Harga
        Route::apiResource('jenis-sampah', Admin\JenisSampahController::class);
        Route::get('/jenis-sampah/{id}/riwayat-harga', [Admin\JenisSampahController::class, 'riwayatHarga']);

        // Kelola Penarikan
        Route::get('/penarikan', [Admin\PenarikanController::class, 'index']);
        Route::patch('/penarikan/{id}/setujui', [Admin\PenarikanController::class, 'setujui']);
        Route::patch('/penarikan/{id}/tolak', [Admin\PenarikanController::class, 'tolak']);

        // Stok Sampah
        Route::get('/stok', [Admin\StokController::class, 'index']);

        // Master Data Pengepul
        Route::apiResource('pengepul', Admin\PengepulController::class);

        // Penjualan ke Pengepul
        Route::get('/penjualan', [Admin\PenjualanController::class, 'index']);
        Route::post('/penjualan', [Admin\PenjualanController::class, 'store']);
        Route::get('/penjualan/{id}', [Admin\PenjualanController::class, 'show']);

        // Kelola Artikel Edukasi & Pengumuman
        Route::apiResource('artikel', Admin\ArtikelController::class);

        // Laporan & Ekspor
        Route::get('/laporan/setoran', [Admin\LaporanController::class, 'setoran']);
        Route::get('/laporan/penarikan', [Admin\LaporanController::class, 'penarikan']);
        Route::get('/laporan/saldo-beredar', [Admin\LaporanController::class, 'saldoBeredar']);
        Route::get('/laporan/per-jenis', [Admin\LaporanController::class, 'perJenis']);
        Route::get('/laporan/{jenis}/export', [Admin\LaporanController::class, 'export']);
    });
});
