<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Nasabah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NasabahController extends Controller
{
    /**
     * Cari nasabah (berdasarkan nama, no rekening, atau no hp).
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 15);

        $query = Nasabah::with('user:id,nama,email,no_hp,alamat,status')
            ->whereHas('user', function ($q) {
                $q->where('status', 'aktif');
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('no_rekening', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $nasabah = $query->orderBy('no_rekening')->paginate($perPage);

        $transformed = $nasabah->through(function ($item) {
            return [
                'id' => $item->id,
                'no_rekening' => $item->no_rekening,
                'nama' => $item->user ? $item->user->nama : '-',
                'no_hp' => $item->user ? $item->user->no_hp : '-',
                'email' => $item->user ? $item->user->email : null,
                'alamat' => $item->user ? $item->user->alamat : null,
                'saldo' => (float) $item->saldo,
                'qr_code' => $item->qr_code,
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar nasabah');
    }

    /**
     * Detail satu nasabah.
     */
    public function show(int $id): JsonResponse
    {
        $nasabah = Nasabah::with('user:id,nama,email,no_hp,alamat,status')->find($id);

        if (! $nasabah) {
            return ApiResponse::error('Nasabah tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $nasabah->id,
            'no_rekening' => $nasabah->no_rekening,
            'nama' => $nasabah->user ? $nasabah->user->nama : '-',
            'no_hp' => $nasabah->user ? $nasabah->user->no_hp : '-',
            'email' => $nasabah->user ? $nasabah->user->email : null,
            'alamat' => $nasabah->user ? $nasabah->user->alamat : null,
            'status' => $nasabah->user ? $nasabah->user->status : 'nonaktif',
            'saldo' => (float) $nasabah->saldo,
            'qr_code' => $nasabah->qr_code,
            'total_berat_disetor' => (float) $nasabah->transaksiSetoran()->sum('total_berat'),
        ], 'Detail nasabah');
    }
}

