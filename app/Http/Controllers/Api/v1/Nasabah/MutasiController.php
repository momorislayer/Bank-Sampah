<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\MutasiSaldo;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutasiController extends Controller
{
    /**
     * Riwayat buku besar mutasi saldo milik nasabah login.
     */
    public function index(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $perPage = (int) $request->query('per_page', 15);

        $mutasi = MutasiSaldo::where('nasabah_id', $nasabah->id)
            ->when($request->query('tipe'), function ($q, $tipe) {
                $q->where('tipe', $tipe);
            })
            ->when($request->query('tanggal_dari'), function ($q, $dari) {
                $q->whereDate('tanggal', '>=', $dari);
            })
            ->when($request->query('tanggal_sampai'), function ($q, $sampai) {
                $q->whereDate('tanggal', '<=', $sampai);
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $transformed = $mutasi->through(function ($item) {
            return [
                'id' => $item->id,
                'tipe' => $item->tipe,
                'jumlah' => (float) $item->jumlah,
                'saldo_akhir' => (float) $item->saldo_akhir,
                'referensi' => $item->referensi,
                'tanggal' => $item->tanggal?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($transformed, 'Riwayat mutasi saldo');
    }
}

