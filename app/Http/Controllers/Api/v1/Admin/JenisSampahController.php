<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisSampah;
use App\Models\RiwayatHarga;
use App\Models\StokSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JenisSampahController extends Controller
{
    /**
     * Daftar seluruh jenis sampah.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $kategoriId = $request->query('kategori_id');

        $query = JenisSampah::with('kategori:id,nama_kategori');

        if ($status) {
            $query->where('status', $status);
        }

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        $items = $query->orderBy('nama_jenis')->get();

        $data = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'kategori_id' => $item->kategori_id,
                'nama_kategori' => $item->kategori ? $item->kategori->nama_kategori : '-',
                'nama_jenis' => $item->nama_jenis,
                'satuan' => $item->satuan,
                'harga_per_kg' => (float) $item->harga_per_kg,
                'status' => $item->status,
            ];
        });

        return ApiResponse::success($data, 'Daftar jenis sampah');
    }

    /**
     * Tambah jenis sampah baru (otomatis inisialisasi riwayat_harga dan stok_sampah).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kategori_id' => ['required', 'exists:kategori_sampah,id'],
            'nama_jenis' => ['required', 'string', 'max:100'],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_per_kg' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
        ]);

        $jenis = DB::transaction(function () use ($validated) {
            $record = JenisSampah::create([
                'kategori_id' => $validated['kategori_id'],
                'nama_jenis' => $validated['nama_jenis'],
                'satuan' => $validated['satuan'] ?? 'kg',
                'harga_per_kg' => $validated['harga_per_kg'],
                'status' => $validated['status'] ?? 'aktif',
            ]);

            // Tambah baris riwayat harga awal
            RiwayatHarga::create([
                'jenis_sampah_id' => $record->id,
                'harga' => $validated['harga_per_kg'],
                'berlaku_mulai' => now(),
            ]);

            // Inisialisasi stok
            StokSampah::create([
                'jenis_sampah_id' => $record->id,
                'berat_tersedia' => 0.00,
            ]);

            return $record;
        });

        $jenis->load('kategori');

        return ApiResponse::success([
            'id' => $jenis->id,
            'kategori_id' => $jenis->kategori_id,
            'nama_kategori' => $jenis->kategori ? $jenis->kategori->nama_kategori : '-',
            'nama_jenis' => $jenis->nama_jenis,
            'satuan' => $jenis->satuan,
            'harga_per_kg' => (float) $jenis->harga_per_kg,
            'status' => $jenis->status,
        ], 'Jenis sampah berhasil ditambahkan', 201);
    }

    /**
     * Detail jenis sampah.
     */
    public function show(int $id): JsonResponse
    {
        $jenis = JenisSampah::with(['kategori', 'stok'])->find($id);

        if (! $jenis) {
            return ApiResponse::error('Jenis sampah tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $jenis->id,
            'kategori_id' => $jenis->kategori_id,
            'nama_kategori' => $jenis->kategori ? $jenis->kategori->nama_kategori : '-',
            'nama_jenis' => $jenis->nama_jenis,
            'satuan' => $jenis->satuan,
            'harga_per_kg' => (float) $jenis->harga_per_kg,
            'status' => $jenis->status,
            'stok_tersedia' => (float) ($jenis->stok ? $jenis->stok->berat_tersedia : 0),
        ], 'Detail jenis sampah');
    }

    /**
     * Update jenis sampah. Jika harga berubah, otomatis menambah baris di riwayat_harga.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jenis = JenisSampah::find($id);

        if (! $jenis) {
            return ApiResponse::error('Jenis sampah tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'kategori_id' => ['sometimes', 'exists:kategori_sampah,id'],
            'nama_jenis' => ['sometimes', 'string', 'max:100'],
            'satuan' => ['sometimes', 'string', 'max:20'],
            'harga_per_kg' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
        ]);

        DB::transaction(function () use ($jenis, $validated) {
            $hargaLama = (float) $jenis->harga_per_kg;
            $jenis->update($validated);

            // Cek jika harga berubah
            if (isset($validated['harga_per_kg'])) {
                $hargaBaru = (float) $validated['harga_per_kg'];
                if ($hargaBaru !== $hargaLama) {
                    RiwayatHarga::create([
                        'jenis_sampah_id' => $jenis->id,
                        'harga' => $hargaBaru,
                        'berlaku_mulai' => now(),
                    ]);
                }
            }
        });

        $jenis->load('kategori');

        return ApiResponse::success([
            'id' => $jenis->id,
            'kategori_id' => $jenis->kategori_id,
            'nama_kategori' => $jenis->kategori ? $jenis->kategori->nama_kategori : '-',
            'nama_jenis' => $jenis->nama_jenis,
            'satuan' => $jenis->satuan,
            'harga_per_kg' => (float) $jenis->harga_per_kg,
            'status' => $jenis->status,
        ], 'Data jenis sampah berhasil diperbarui');
    }

    /**
     * Riwayat perubahan harga untuk jenis sampah ini.
     */
    public function riwayatHarga(int $id): JsonResponse
    {
        $jenis = JenisSampah::find($id);

        if (! $jenis) {
            return ApiResponse::error('Jenis sampah tidak ditemukan', 404);
        }

        $riwayat = RiwayatHarga::where('jenis_sampah_id', $id)
            ->orderByDesc('berlaku_mulai')
            ->get();

        $data = $riwayat->map(function ($r) {
            return [
                'id' => $r->id,
                'harga' => (float) $r->harga,
                'berlaku_mulai' => $r->berlaku_mulai?->toIso8601String(),
            ];
        });

        return ApiResponse::success($data, "Riwayat harga untuk '{$jenis->nama_jenis}'");
    }
}

