<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Nasabah;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login menggunakan No HP atau No Rekening Nasabah (atau Email).
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($validated['identifier']);
        $password = $validated['password'];

        // Cari berdasarkan No HP atau Email
        $user = User::where('no_hp', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        // Jika tidak ditemukan, cari berdasarkan No Rekening Nasabah
        if (! $user) {
            $nasabah = Nasabah::where('no_rekening', $identifier)->first();
            if ($nasabah) {
                $user = $nasabah->user;
            }
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Nomor HP / Nomor Rekening atau password salah.'],
            ]);
        }

        if ($user->status === 'nonaktif') {
            return ApiResponse::error('Akun Anda dinonaktifkan. Silakan hubungi admin.', 403);
        }

        // Generate Sanctum Token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Load nasabah relasi jika role nasabah
        if ($user->isNasabah()) {
            $user->load('nasabah');
        }

        return ApiResponse::success([
            'token' => $token,
            'user' => [
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
                    'qr_code' => $user->nasabah->qr_code,
                ] : null,
            ],
        ], 'Login berhasil');
    }

    /**
     * Logout dan hapus token aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, 'Logout berhasil');
    }

    /**
     * Data user yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isNasabah()) {
            $user->load('nasabah');
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
                'qr_code' => $user->nasabah->qr_code,
            ] : null,
        ], 'Data profil pengguna');
    }

    /**
     * Registrasi mandiri nasabah baru.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'alamat' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $user = User::create([
                'nama' => $validated['nama'],
                'no_hp' => $validated['no_hp'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'nasabah',
                'alamat' => $validated['alamat'] ?? null,
                'status' => 'aktif',
            ]);

            $nasabah = Nasabah::create([
                'user_id' => $user->id,
                'no_rekening' => Nasabah::generateNoRekening(),
                'saldo' => 0.00,
            ]);

            return ['user' => $user, 'nasabah' => $nasabah];
        });

        $user = $result['user'];
        $nasabah = $result['nasabah'];
        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'email' => $user->email,
                'no_hp' => $user->no_hp,
                'role' => $user->role,
                'alamat' => $user->alamat,
                'nasabah' => [
                    'id' => $nasabah->id,
                    'no_rekening' => $nasabah->no_rekening,
                    'saldo' => (float) $nasabah->saldo,
                ],
            ],
        ], 'Registrasi berhasil', 201);
    }

    /**
     * Ubah password user aktif.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak cocok.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return ApiResponse::success(null, 'Password berhasil diperbarui');
    }
}

