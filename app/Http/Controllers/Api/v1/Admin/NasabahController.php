<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Nasabah;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class NasabahController extends Controller
{
    /**
     * Daftar seluruh nasabah.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = trim((string) $request->query('search', ''));

        $query = Nasabah::with('user:id,nama,email,no_hp,alamat,status');

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

        $nasabah = $query->orderByDesc('id')->paginate($perPage);

        $transformed = $nasabah->through(function ($item) {
            return [
                'id' => $item->id,
                'no_rekening' => $item->no_rekening,
                'nama' => $item->user ? $item->user->nama : '-',
                'email' => $item->user ? $item->user->email : null,
                'no_hp' => $item->user ? $item->user->no_hp : '-',
                'alamat' => $item->user ? $item->user->alamat : null,
                'status' => $item->user ? $item->user->status : 'nonaktif',
                'saldo' => (float) $item->saldo,
                'qr_code' => $item->qr_code,
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar nasabah');
    }

    /**
     * Daftarkan nasabah baru oleh admin (membuat user + nomor rekening otomatis).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6'],
            'alamat' => ['nullable', 'string'],
        ]);

        $defaultPassword = $validated['password'] ?? 'password123';

        $nasabah = DB::transaction(function () use ($validated, $defaultPassword) {
            $user = User::create([
                'nama' => $validated['nama'],
                'no_hp' => $validated['no_hp'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($defaultPassword),
                'role' => 'nasabah',
                'alamat' => $validated['alamat'] ?? null,
                'status' => 'aktif',
            ]);

            return Nasabah::create([
                'user_id' => $user->id,
                'no_rekening' => Nasabah::generateNoRekening(),
                'saldo' => 0.00,
            ]);
        });

        $nasabah->load('user');

        return ApiResponse::success([
            'id' => $nasabah->id,
            'no_rekening' => $nasabah->no_rekening,
            'nama' => $nasabah->user->nama,
            'email' => $nasabah->user->email,
            'no_hp' => $nasabah->user->no_hp,
            'alamat' => $nasabah->user->alamat,
            'saldo' => (float) $nasabah->saldo,
        ], 'Nasabah berhasil didaftarkan', 201);
    }

    /**
     * Detail satu nasabah lengkap dengan riwayat mutasi dan ringkasan.
     */
    public function show(int $id): JsonResponse
    {
        $nasabah = Nasabah::with(['user'])->find($id);

        if (! $nasabah) {
            return ApiResponse::error('Nasabah tidak ditemukan', 404);
        }

        $totalBerat = (float) $nasabah->transaksiSetoran()->sum('total_berat');
        $totalSetoran = $nasabah->transaksiSetoran()->count();

        $mutasiTerakhir = $nasabah->mutasiSaldo()
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'tipe' => $m->tipe,
                    'jumlah' => (float) $m->jumlah,
                    'saldo_akhir' => (float) $m->saldo_akhir,
                    'referensi' => $m->referensi,
                    'tanggal' => $m->tanggal?->toIso8601String(),
                ];
            });

        return ApiResponse::success([
            'id' => $nasabah->id,
            'no_rekening' => $nasabah->no_rekening,
            'nama' => $nasabah->user ? $nasabah->user->nama : '-',
            'email' => $nasabah->user ? $nasabah->user->email : null,
            'no_hp' => $nasabah->user ? $nasabah->user->no_hp : '-',
            'alamat' => $nasabah->user ? $nasabah->user->alamat : null,
            'status' => $nasabah->user ? $nasabah->user->status : 'nonaktif',
            'saldo' => (float) $nasabah->saldo,
            'total_berat_disetor' => $totalBerat,
            'total_transaksi_setoran' => $totalSetoran,
            'mutasi_terakhir' => $mutasiTerakhir,
        ], 'Detail nasabah');
    }

    /**
     * Update profil nasabah.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $nasabah = Nasabah::with('user')->find($id);

        if (! $nasabah || ! $nasabah->user) {
            return ApiResponse::error('Nasabah tidak ditemukan', 404);
        }

        $user = $nasabah->user;

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['required', 'string', 'max:20', Rule::unique('users', 'no_hp')->ignore($user->id)],
            'alamat' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
        ]);

        $user->update($validated);

        return ApiResponse::success([
            'id' => $nasabah->id,
            'no_rekening' => $nasabah->no_rekening,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'alamat' => $user->alamat,
            'status' => $user->status,
            'saldo' => (float) $nasabah->saldo,
        ], 'Data nasabah berhasil diperbarui');
    }
}

