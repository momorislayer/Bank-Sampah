<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\Penjemputan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenjemputanController extends Controller
{
    /**
     * Daftar seluruh penjemputan sampah untuk petugas & admin (filter status).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $status = $request->query('status');

        $query = Penjemputan::with([
            'nasabah.user:id,nama,no_hp,alamat',
            'petugas:id,nama,no_hp',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        $penjemputan = $query->orderByDesc('jadwal')->paginate($perPage);

        $transformed = $penjemputan->through(function ($item) {
            return [
                'id' => $item->id,
                'nasabah' => [
                    'id' => $item->nasabah_id,
                    'no_rekening' => $item->nasabah ? $item->nasabah->no_rekening : '-',
                    'nama' => $item->nasabah && $item->nasabah->user ? $item->nasabah->user->nama : '-',
                    'no_hp' => $item->nasabah && $item->nasabah->user ? $item->nasabah->user->no_hp : '-',
                ],
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
     * Ubah status penjemputan, jadwalkan, atau tugaskan diri sendiri/petugas lain.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $penjemputan = Penjemputan::with('nasabah.user')->find($id);

        if (! $penjemputan) {
            return ApiResponse::error('Data penjemputan tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'in:menunggu,dijadwalkan,selesai,dibatalkan'],
            'petugas_id' => ['nullable', 'exists:users,id'],
            'jadwal' => ['sometimes', 'date'],
        ]);

        // Default jika petugas ingin menugaskan dirinya sendiri
        if (! isset($validated['petugas_id']) && ! $penjemputan->petugas_id) {
            $validated['petugas_id'] = $request->user()->id;
        }

        $penjemputan->update($validated);

        // Buat notifikasi sistem untuk nasabah bila status berubah
        if (isset($validated['status']) && $penjemputan->nasabah && $penjemputan->nasabah->user_id) {
            $statusLabels = [
                'dijadwalkan' => 'telah dijadwalkan',
                'selesai' => 'telah selesai dilaksanakan',
                'dibatalkan' => 'dibatalkan',
                'menunggu' => 'kembali dalam antrean menunggu',
            ];

            $label = $statusLabels[$validated['status']] ?? $validated['status'];

            Notifikasi::create([
                'user_id' => $penjemputan->nasabah->user_id,
                'judul' => 'Update Status Penjemputan',
                'pesan' => "Penjemputan sampah Anda {$label}.",
                'dibaca' => false,
            ]);
        }

        $penjemputan->load('petugas:id,nama,no_hp');

        return ApiResponse::success([
            'id' => $penjemputan->id,
            'status' => $penjemputan->status,
            'jadwal' => $penjemputan->jadwal?->toIso8601String(),
            'petugas' => $penjemputan->petugas ? [
                'id' => $penjemputan->petugas->id,
                'nama' => $penjemputan->petugas->nama,
            ] : null,
        ], 'Status penjemputan berhasil diperbarui');
    }
}

