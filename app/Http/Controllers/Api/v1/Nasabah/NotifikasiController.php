<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * Daftar notifikasi milik pengguna login.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = (int) $request->query('per_page', 15);

        $notifikasi = Notifikasi::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $transformed = $notifikasi->through(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'pesan' => $item->pesan,
                'dibaca' => (bool) $item->dibaca,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar notifikasi');
    }

    /**
     * Tandai satu notifikasi sudah dibaca.
     */
    public function baca(Request $request, int $id): JsonResponse
    {
        $notifikasi = Notifikasi::where('user_id', $request->user()->id)->find($id);

        if (! $notifikasi) {
            return ApiResponse::error('Notifikasi tidak ditemukan', 404);
        }

        $notifikasi->update(['dibaca' => true]);

        return ApiResponse::success([
            'id' => $notifikasi->id,
            'dibaca' => true,
        ], 'Notifikasi ditandai telah dibaca');
    }
}

