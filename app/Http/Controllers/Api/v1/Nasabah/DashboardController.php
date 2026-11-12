<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard nasabah: saldo, total berat yang disetor, dan 5 setoran terakhir.
     */
    public function index(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;

        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan untuk akun ini', 404);
        }

        $totalBerat = (float) $nasabah->transaksiSetoran()->sum('total_berat');
        $totalSetoran = $nasabah->transaksiSetoran()->count();

        $setoranTerakhir = $nasabah->transaksiSetoran()
            ->with(['details.jenisSampah'])
            ->orderByDesc('tanggal')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tanggal' => $item->tanggal?->toIso8601String(),
                    'total_berat' => (float) $item->total_berat,
                    'total_nilai' => (float) $item->total_nilai,
                    'catatan' => $item->catatan,
                    'items_count' => $item->details->count(),
                ];
            });

        return ApiResponse::success([
            'no_rekening' => $nasabah->no_rekening,
            'saldo' => (float) $nasabah->saldo,
            'total_berat_disetor' => $totalBerat,
            'total_transaksi_setoran' => $totalSetoran,
            'setoran_terakhir' => $setoranTerakhir,
        ], 'Ringkasan dashboard nasabah');
    }
}

