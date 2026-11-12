<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Penjemputan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenjemputanController extends Controller
{
    /**
     * Riwayat pengajuan penjemputan sampah milik nasabah login.
     */
    public function index(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $perPage = (int) $request->query('per_page', 15);
        $status = $request->query('status');

        $query = Penjemputan::with('petugas:id,nama,no_hp')
            ->where('nasabah_id', $nasabah->id);

        if ($status) {
            $query->where('status', $status);
        }

        $penjemputan = $query->orderByDesc('jadwal')->paginate($perPage);

        $transformed = $penjemputan->through(function ($item) {
            return [
                'id' => $item->id,
                'alamat' => $item->alamat,
                'latitude' => $item->latitude !== null ? (float) $item->latitude : null,
                'longitude' => $item->longitude !== null ? (float) $item->longitude : null,
                'jadwal' => $item->jadwal?->toIso8601String(),
                'status' => $item->status,
                'petugas' => $item->petugas ? [
                    'id' => $item->petugas->id,
                    'nama' => $item->petugas->nama,
                    'no_hp' => $item->petugas->no_hp,
                ] : null,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar penjemputan sampah');
    }

    /**
     * Ajukan permintaan penjemputan sampah.
     */
    public function store(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'alamat' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'jadwal' => ['required', 'date'],
        ]);

        $penjemputan = Penjemputan::create([
            'nasabah_id' => $nasabah->id,
            'alamat' => $validated['alamat'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'jadwal' => $validated['jadwal'],
            'status' => 'menunggu',
        ]);

        return ApiResponse::success([
            'id' => $penjemputan->id,
            'alamat' => $penjemputan->alamat,
            'latitude' => $penjemputan->latitude !== null ? (float) $penjemputan->latitude : null,
            'longitude' => $penjemputan->longitude !== null ? (float) $penjemputan->longitude : null,
            'jadwal' => $penjemputan->jadwal?->toIso8601String(),
            'status' => $penjemputan->status,
        ], 'Permintaan penjemputan berhasil diajukan', 201);
    }

    /**
     * Batalkan penjemputan bila status masih 'menunggu'.
     */
    public function batal(Request $request, int $id): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $penjemputan = Penjemputan::where('nasabah_id', $nasabah->id)->find($id);

        if (! $penjemputan) {
            return ApiResponse::error('Data penjemputan tidak ditemukan', 404);
        }

        if ($penjemputan->status !== 'menunggu') {
            return ApiResponse::error("Penjemputan tidak dapat dibatalkan karena sudah berstatus '{$penjemputan->status}'", 422);
        }

        $penjemputan->update(['status' => 'dibatalkan']);

        return ApiResponse::success([
            'id' => $penjemputan->id,
            'status' => $penjemputan->status,
        ], 'Permintaan penjemputan berhasil dibatalkan');
    }
}

