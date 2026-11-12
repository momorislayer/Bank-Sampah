<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\JenisSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class JenisSampahController extends Controller
{
    /**
     * Daftar jenis sampah aktif beserta kategori dan harga untuk form setoran.
     */
    public function index(): JsonResponse
    {
        $items = JenisSampah::with('kategori:id,nama_kategori')
            ->where('status', 'aktif')
            ->orderBy('nama_jenis')
            ->get();

        $data = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'kategori_id' => $item->kategori_id,
                'nama_kategori' => $item->kategori ? $item->kategori->nama_kategori : '-',
                'nama_jenis' => $item->nama_jenis,
                'satuan' => $item->satuan,
                'harga_per_kg' => (float) $item->harga_per_kg,
            ];
        });

        return ApiResponse::success($data, 'Daftar jenis sampah aktif');
    }
}

