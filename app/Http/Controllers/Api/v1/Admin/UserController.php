<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Daftar pengguna (filter role, status, dan pencarian).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role');
        $status = $request->query('status');

        $query = User::query();

        if ($role) {
            $query->where('role', $role);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('id')->paginate($perPage);

        $transformed = $users->through(function ($user) {
            return [
                'id' => $user->id,
                'nama' => $user->nama,
                'email' => $user->email,
                'no_hp' => $user->no_hp,
                'role' => $user->role,
                'alamat' => $user->alamat,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar pengguna');
    }

    /**
     * Tambah user baru (admin atau petugas).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,petugas,nasabah'],
            'alamat' => ['nullable', 'string'],
        ]);

        $user = User::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'] ?? null,
            'no_hp' => $validated['no_hp'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'alamat' => $validated['alamat'] ?? null,
            'status' => 'aktif',
        ]);

        return ApiResponse::success([
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'role' => $user->role,
            'alamat' => $user->alamat,
            'status' => $user->status,
        ], 'Pengguna berhasil ditambahkan', 201);
    }

    /**
     * Detail pengguna.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with('nasabah')->find($id);

        if (! $user) {
            return ApiResponse::error('Pengguna tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'role' => $user->role,
            'alamat' => $user->alamat,
            'status' => $user->status,
            'nasabah' => $user->nasabah ? [
                'id' => $user->nasabah->id,
                'no_rekening' => $user->nasabah->no_rekening,
                'saldo' => (float) $user->nasabah->saldo,
            ] : null,
            'created_at' => $user->created_at?->toIso8601String(),
        ], 'Detail pengguna');
    }

    /**
     * Update data pengguna.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::error('Pengguna tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['required', 'string', 'max:20', Rule::unique('users', 'no_hp')->ignore($user->id)],
            'role' => ['sometimes', 'in:admin,petugas,nasabah'],
            'alamat' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
        ]);

        $user->update($validated);

        return ApiResponse::success([
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'role' => $user->role,
            'alamat' => $user->alamat,
            'status' => $user->status,
        ], 'Data pengguna berhasil diperbarui');
    }

    /**
     * Nonaktifkan / aktifkan kembali pengguna (Soft status).
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::error('Pengguna tidak ditemukan', 404);
        }

        // Cegah admin menonaktifkan dirinya sendiri
        if ($request->user()->id === $user->id) {
            return ApiResponse::error('Anda tidak dapat menonaktifkan akun Anda sendiri', 422);
        }

        $newStatus = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->update(['status' => $newStatus]);

        return ApiResponse::success([
            'id' => $user->id,
            'status' => $user->status,
        ], "Pengguna berhasil diubah menjadi {$newStatus}");
    }

    /**
     * Reset password pengguna oleh admin.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::error('Pengguna tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return ApiResponse::success(null, 'Password pengguna berhasil direset');
    }
}

