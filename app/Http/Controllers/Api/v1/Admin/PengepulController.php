<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengepul;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengepulController extends Controller
{
    /**
     * Daftar seluruh data master pengepul.
     */
    public function index(): JsonResponse
    {
        $pengepul = Pengepul::withCount('penjualan')
            ->orderBy('nama')
            ->get();

        $data = $pengepul->map(function ($p) {
            return [
                'id' => $p->id,
                'nama' => $p->nama,
                'kontak' => $p->kontak,
                'alamat' => $p->alamat,
                'total_transaksi_penjualan' => $p->penjualan_count,
            ];
        });

        return ApiResponse::success($data, 'Daftar data pengepul');
    }

    /**
     * Tambah data pengepul baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kontak' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
        ]);

        $pengepul = Pengepul::create($validated);

        return ApiResponse::success([
            'id' => $pengepul->id,
            'nama' => $pengepul->nama,
            'kontak' => $pengepul->kontak,
            'alamat' => $pengepul->alamat,
        ], 'Data pengepul berhasil ditambahkan', 201);
    }

    /**
     * Detail satu pengepul beserta riwayat penjualannya.
     */
    public function show(int $id): JsonResponse
    {
        $pengepul = Pengepul::with(['penjualan' => function ($q) {
            $q->orderByDesc('tanggal')->limit(10);
        }])->find($id);

        if (! $pengepul) {
            return ApiResponse::error('Pengepul tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $pengepul->id,
            'nama' => $pengepul->nama,
            'kontak' => $pengepul->kontak,
            'alamat' => $pengepul->alamat,
            'riwayat_penjualan' => $pengepul->penjualan->map(function ($pj) {
                return [
                    'id' => $pj->id,
                    'tanggal' => $pj->tanggal?->toIso8601String(),
                    'total_berat' => (float) $pj->total_berat,
                    'total_nilai' => (float) $pj->total_nilai,
                ];
            }),
        ], 'Detail data pengepul');
    }

    /**
     * Update data pengepul.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pengepul = Pengepul::find($id);

        if (! $pengepul) {
            return ApiResponse::error('Pengepul tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kontak' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
        ]);

        $pengepul->update($validated);

        return ApiResponse::success([
            'id' => $pengepul->id,
            'nama' => $pengepul->nama,
            'kontak' => $pengepul->kontak,
            'alamat' => $pengepul->alamat,
        ], 'Data pengepul berhasil diperbarui');
    }

    /**
     * Hapus data pengepul (hanya bila belum memiliki riwayat penjualan).
     */
    public function destroy(int $id): JsonResponse
    {
        $pengepul = Pengepul::withCount('penjualan')->find($id);

        if (! $pengepul) {
            return ApiResponse::error('Pengepul tidak ditemukan', 404);
        }

        if ($pengepul->penjualan_count > 0) {
            return ApiResponse::error('Pengepul tidak dapat dihapus karena sudah memiliki riwayat transaksi penjualan', 422);
        }

        $pengepul->delete();

        return ApiResponse::success(null, 'Data pengepul berhasil dihapus');
    }
}

