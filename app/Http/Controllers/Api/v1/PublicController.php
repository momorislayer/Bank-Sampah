<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ArtikelEdukasi;
use App\Models\KategoriSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    /**
     * Daftar harga sampah per kategori yang berstatus aktif.
     */
    public function hargaSampah(): JsonResponse
    {
        $kategori = KategoriSampah::with(['jenisSampah' => function ($query) {
            $query->where('status', 'aktif')->orderBy('nama_jenis');
        }])->get();

        $data = $kategori->map(function ($kat) {
            return [
                'id' => $kat->id,
                'nama_kategori' => $kat->nama_kategori,
                'deskripsi' => $kat->deskripsi,
                'jenis_sampah' => $kat->jenisSampah->map(function ($jenis) {
                    return [
                        'id' => $jenis->id,
                        'nama_jenis' => $jenis->nama_jenis,
                        'satuan' => $jenis->satuan,
                        'harga_per_kg' => (float) $jenis->harga_per_kg,
                    ];
                }),
            ];
        });

        return ApiResponse::success($data, 'Daftar harga sampah saat ini');
    }

    /**
     * Daftar artikel edukasi dan pengumuman.
     */
    public function artikel(): JsonResponse
    {
        $artikel = ArtikelEdukasi::with('penulis:id,nama')
            ->orderByDesc('created_at')
            ->get();

        $data = $artikel->map(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'isi' => $item->isi,
                'gambar' => $item->gambar,
                'penulis' => $item->penulis ? $item->penulis->nama : null,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success($data, 'Daftar artikel edukasi');
    }

    /**
     * Detail satu artikel edukasi.
     */
    public function artikelDetail(int $id): JsonResponse
    {
        $item = ArtikelEdukasi::with('penulis:id,nama')->find($id);

        if (! $item) {
            return ApiResponse::error('Artikel tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $item->id,
            'judul' => $item->judul,
            'isi' => $item->isi,
            'gambar' => $item->gambar,
            'penulis' => $item->penulis ? $item->penulis->nama : null,
            'created_at' => $item->created_at?->toIso8601String(),
        ], 'Detail artikel');
    }
}

