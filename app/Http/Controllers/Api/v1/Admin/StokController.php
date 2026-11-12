<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\StokSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StokController extends Controller
{
    /**
     * Stok terkini per jenis sampah.
     */
    public function index(): JsonResponse
    {
        $stok = StokSampah::with('jenisSampah.kategori')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'jenis_sampah_id' => $s->jenis_sampah_id,
                    'nama_jenis' => $s->jenisSampah ? $s->jenisSampah->nama_jenis : '-',
                    'kategori' => $s->jenisSampah && $s->jenisSampah->kategori ? $s->jenisSampah->kategori->nama_kategori : '-',
                    'satuan' => $s->jenisSampah ? $s->jenisSampah->satuan : 'kg',
                    'berat_tersedia' => (float) $s->berat_tersedia,
                    'harga_beli_saat_ini' => (float) ($s->jenisSampah ? $s->jenisSampah->harga_per_kg : 0),
                    'updated_at' => $s->updated_at?->toIso8601String(),
                ];
            });

        return ApiResponse::success($stok, 'Data stok sampah terkini');
    }
}

