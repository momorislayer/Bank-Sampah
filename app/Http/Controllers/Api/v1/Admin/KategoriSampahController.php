<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KategoriSampahController extends Controller
{
    /**
     * Daftar seluruh kategori sampah beserta jumlah jenis sampahnya.
     */
    public function index(): JsonResponse
    {
        $kategori = KategoriSampah::withCount('jenisSampah')->get();

        $data = $kategori->map(function ($k) {
            return [
                'id' => $k->id,
                'nama_kategori' => $k->nama_kategori,
                'deskripsi' => $k->deskripsi,
                'total_jenis_sampah' => $k->jenis_sampah_count,
            ];
        });

        return ApiResponse::success($data, 'Daftar kategori sampah');
    }

    /**
     * Tambah kategori sampah baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $kategori = KategoriSampah::create($validated);

        return ApiResponse::success([
            'id' => $kategori->id,
            'nama_kategori' => $kategori->nama_kategori,
            'deskripsi' => $kategori->deskripsi,
        ], 'Kategori sampah berhasil ditambahkan', 201);
    }

    /**
     * Detail kategori sampah.
     */
    public function show(int $id): JsonResponse
    {
        $kategori = KategoriSampah::with('jenisSampah')->find($id);

        if (! $kategori) {
            return ApiResponse::error('Kategori sampah tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $kategori->id,
            'nama_kategori' => $kategori->nama_kategori,
            'deskripsi' => $kategori->deskripsi,
            'jenis_sampah' => $kategori->jenisSampah->map(function ($j) {
                return [
                    'id' => $j->id,
                    'nama_jenis' => $j->nama_jenis,
                    'satuan' => $j->satuan,
                    'harga_per_kg' => (float) $j->harga_per_kg,
                    'status' => $j->status,
                ];
            }),
        ], 'Detail kategori sampah');
    }

    /**
     * Update kategori sampah.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kategori = KategoriSampah::find($id);

        if (! $kategori) {
            return ApiResponse::error('Kategori sampah tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $kategori->update($validated);

        return ApiResponse::success([
            'id' => $kategori->id,
            'nama_kategori' => $kategori->nama_kategori,
            'deskripsi' => $kategori->deskripsi,
        ], 'Kategori sampah berhasil diperbarui');
    }

    /**
     * Hapus kategori sampah bila tidak memiliki jenis sampah.
     */
    public function destroy(int $id): JsonResponse
    {
        $kategori = KategoriSampah::withCount('jenisSampah')->find($id);

        if (! $kategori) {
            return ApiResponse::error('Kategori sampah tidak ditemukan', 404);
        }

        if ($kategori->jenis_sampah_count > 0) {
            return ApiResponse::error('Kategori tidak dapat dihapus karena masih memiliki jenis sampah terkait', 422);
        }

        $kategori->delete();

        return ApiResponse::success(null, 'Kategori sampah berhasil dihapus');
    }
}

