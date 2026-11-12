<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\TransaksiSetoran;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetoranController extends Controller
{
    /**
     * Riwayat setoran milik nasabah login.
     */
    public function index(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $perPage = (int) $request->query('per_page', 15);

        $setoran = TransaksiSetoran::with(['petugas:id,nama'])
            ->where('nasabah_id', $nasabah->id)
            ->when($request->query('tanggal_dari'), function ($q, $dari) {
                $q->whereDate('tanggal', '>=', $dari);
            })
            ->when($request->query('tanggal_sampai'), function ($q, $sampai) {
                $q->whereDate('tanggal', '<=', $sampai);
            })
            ->orderByDesc('tanggal')
            ->paginate($perPage);

        $transformed = $setoran->through(function ($item) {
            return [
                'id' => $item->id,
                'tanggal' => $item->tanggal?->toIso8601String(),
                'petugas' => $item->petugas ? $item->petugas->nama : null,
                'total_berat' => (float) $item->total_berat,
                'total_nilai' => (float) $item->total_nilai,
                'catatan' => $item->catatan,
            ];
        });

        return ApiResponse::paginated($transformed, 'Riwayat setoran');
    }

    /**
     * Detail setoran beserta rincian sampah per baris.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $setoran = TransaksiSetoran::with([
            'petugas:id,nama',
            'details.jenisSampah.kategori',
        ])
            ->where('nasabah_id', $nasabah->id)
            ->find($id);

        if (! $setoran) {
            return ApiResponse::error('Data setoran tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $setoran->id,
            'tanggal' => $setoran->tanggal?->toIso8601String(),
            'petugas' => $setoran->petugas ? $setoran->petugas->nama : null,
            'total_berat' => (float) $setoran->total_berat,
            'total_nilai' => (float) $setoran->total_nilai,
            'catatan' => $setoran->catatan,
            'items' => $setoran->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'jenis_sampah_id' => $d->jenis_sampah_id,
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'kategori' => $d->jenisSampah && $d->jenisSampah->kategori ? $d->jenisSampah->kategori->nama_kategori : '-',
                    'satuan' => $d->jenisSampah ? $d->jenisSampah->satuan : 'kg',
                    'berat' => (float) $d->berat,
                    'harga_saat_itu' => (float) $d->harga_saat_itu,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
        ], 'Detail setoran');
    }
}

