<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\DetailSetoran;
use App\Models\TransaksiSetoran;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RekapController extends Controller
{
    /**
     * Rekapitulasi setoran harian untuk petugas.
     */
    public function harian(Request $request): JsonResponse
    {
        $tanggalInput = $request->query('tanggal', now()->toDateString());
        $tanggal = Carbon::parse($tanggalInput)->toDateString();

        $setoranQuery = TransaksiSetoran::with([
            'nasabah.user:id,nama,no_hp',
            'petugas:id,nama',
        ])->whereDate('tanggal', $tanggal);

        $totalTransaksi = (clone $setoranQuery)->count();
        $totalBerat = (float) (clone $setoranQuery)->sum('total_berat');
        $totalNilai = (float) (clone $setoranQuery)->sum('total_nilai');

        // Rincian per jenis sampah
        $rincianPerJenis = DetailSetoran::with('jenisSampah')
            ->whereHas('setoran', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->selectRaw('jenis_sampah_id, SUM(berat) as total_berat, SUM(subtotal) as total_nilai')
            ->groupBy('jenis_sampah_id')
            ->get()
            ->map(function ($row) {
                return [
                    'jenis_sampah_id' => $row->jenis_sampah_id,
                    'nama_jenis' => $row->jenisSampah ? $row->jenisSampah->nama_jenis : '-',
                    'satuan' => $row->jenisSampah ? $row->jenisSampah->satuan : 'kg',
                    'total_berat' => (float) $row->total_berat,
                    'total_nilai' => (float) $row->total_nilai,
                ];
            });

        $daftarTransaksi = $setoranQuery->orderByDesc('tanggal')->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'waktu' => $s->tanggal?->format('H:i'),
                'nasabah' => $s->nasabah && $s->nasabah->user ? $s->nasabah->user->nama : '-',
                'no_rekening' => $s->nasabah ? $s->nasabah->no_rekening : '-',
                'petugas' => $s->petugas ? $s->petugas->nama : '-',
                'total_berat' => (float) $s->total_berat,
                'total_nilai' => (float) $s->total_nilai,
            ];
        });

        return ApiResponse::success([
            'tanggal' => $tanggal,
            'ringkasan' => [
                'total_transaksi' => $totalTransaksi,
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
            ],
            'rincian_per_jenis' => $rincianPerJenis,
            'daftar_transaksi' => $daftarTransaksi,
        ], "Rekap setoran tanggal {$tanggal}");
    }
}

