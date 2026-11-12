<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisSampah;
use App\Models\Nasabah;
use App\Models\Penarikan;
use App\Models\StokSampah;
use App\Models\TransaksiSetoran;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Ringkasan metrik dashboard admin.
     */
    public function index(): JsonResponse
    {
        $now = now();

        $totalNasabah = Nasabah::count();
        $totalPetugas = User::where('role', 'petugas')->where('status', 'aktif')->count();
        $saldoBeredar = (float) Nasabah::sum('saldo');

        $setoranBulanIni = (float) TransaksiSetoran::whereYear('tanggal', $now->year)
            ->whereMonth('tanggal', $now->month)
            ->sum('total_nilai');

        $beratBulanIni = (float) TransaksiSetoran::whereYear('tanggal', $now->year)
            ->whereMonth('tanggal', $now->month)
            ->sum('total_berat');

        $penarikanPendingCount = Penarikan::where('status', 'pending')->count();
        $penarikanPendingNominal = (float) Penarikan::where('status', 'pending')->sum('jumlah');

        $stokList = StokSampah::with('jenisSampah.kategori')
            ->get()
            ->map(function ($s) {
                return [
                    'jenis_sampah_id' => $s->jenis_sampah_id,
                    'nama_jenis' => $s->jenisSampah ? $s->jenisSampah->nama_jenis : '-',
                    'kategori' => $s->jenisSampah && $s->jenisSampah->kategori ? $s->jenisSampah->kategori->nama_kategori : '-',
                    'satuan' => $s->jenisSampah ? $s->jenisSampah->satuan : 'kg',
                    'berat_tersedia' => (float) $s->berat_tersedia,
                ];
            });

        return ApiResponse::success([
            'total_nasabah' => $totalNasabah,
            'total_petugas' => $totalPetugas,
            'saldo_beredar' => $saldoBeredar,
            'setoran_bulan_ini' => [
                'total_nilai' => $setoranBulanIni,
                'total_berat' => $beratBulanIni,
            ],
            'penarikan_pending' => [
                'total_pengajuan' => $penarikanPendingCount,
                'total_nominal' => $penarikanPendingNominal,
            ],
            'stok_sampah' => $stokList,
        ], 'Ringkasan dashboard admin');
    }
}

