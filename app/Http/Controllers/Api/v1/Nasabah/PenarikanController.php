<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Penarikan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PenarikanController extends Controller
{
    /**
     * Riwayat pengajuan penarikan milik nasabah login.
     */
    public function index(Request $request): JsonResponse
    {
        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $perPage = (int) $request->query('per_page', 15);

        $penarikan = Penarikan::with('diprosesOleh:id,nama')
            ->where('nasabah_id', $nasabah->id)
            ->when($request->query('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->orderByDesc('tanggal_ajuan')
            ->paginate($perPage);

        $transformed = $penarikan->through(function ($item) {
            return [
                'id' => $item->id,
                'jumlah' => (float) $item->jumlah,
                'status' => $item->status,
                'tanggal_ajuan' => $item->tanggal_ajuan?->toIso8601String(),
                'tanggal_proses' => $item->tanggal_proses?->toIso8601String(),
                'diproses_oleh' => $item->diprosesOleh ? $item->diprosesOleh->nama : null,
                'catatan' => $item->catatan,
            ];
        });

        return ApiResponse::paginated($transformed, 'Riwayat pengajuan penarikan');
    }

    /**
     * Pengajuan penarikan saldo tunai oleh nasabah.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:1000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $nasabah = $request->user()->nasabah;
        if (! $nasabah) {
            return ApiResponse::error('Data nasabah tidak ditemukan', 404);
        }

        $jumlah = (float) $validated['jumlah'];

        // Aturan Bisnis 5: Saldo tidak boleh negatif. Tolak jika jumlah > saldo.
        if ($jumlah > (float) $nasabah->saldo) {
            throw ValidationException::withMessages([
                'jumlah' => ['Jumlah penarikan melebihi saldo yang tersedia.'],
            ]);
        }

        // Cek total penarikan berstatus 'pending' agar tidak over-request
        $totalPending = (float) Penarikan::where('nasabah_id', $nasabah->id)
            ->where('status', 'pending')
            ->sum('jumlah');

        if (($totalPending + $jumlah) > (float) $nasabah->saldo) {
            throw ValidationException::withMessages([
                'jumlah' => ['Anda memiliki pengajuan penarikan yang masih menunggu persetujuan sehingga melebihi sisa saldo Anda.'],
            ]);
        }

        $penarikan = Penarikan::create([
            'nasabah_id' => $nasabah->id,
            'jumlah' => $jumlah,
            'status' => 'pending',
            'tanggal_ajuan' => now(),
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return ApiResponse::success([
            'id' => $penarikan->id,
            'jumlah' => (float) $penarikan->jumlah,
            'status' => $penarikan->status,
            'tanggal_ajuan' => $penarikan->tanggal_ajuan?->toIso8601String(),
            'catatan' => $penarikan->catatan,
        ], 'Pengajuan penarikan berhasil dikirim. Menunggu persetujuan admin.', 201);
    }
}

