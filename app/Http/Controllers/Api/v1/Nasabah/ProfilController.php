<?php

namespace App\Http\Controllers\Api\v1\Nasabah;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    /**
     * Tampilkan profil nasabah login.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $nasabah = $user->nasabah;

        return ApiResponse::success([
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'alamat' => $user->alamat,
            'role' => $user->role,
            'nasabah' => $nasabah ? [
                'id' => $nasabah->id,
                'no_rekening' => $nasabah->no_rekening,
                'saldo' => (float) $nasabah->saldo,
                'qr_code' => $nasabah->qr_code,
            ] : null,
        ], 'Profil nasabah');
    }

    /**
     * Update profil nasabah.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['required', 'string', 'max:20', Rule::unique('users', 'no_hp')->ignore($user->id)],
            'alamat' => ['nullable', 'string'],
        ]);

        $user->update($validated);

        return ApiResponse::success([
            'id' => $user->id,
            'nama' => $user->nama,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            'alamat' => $user->alamat,
        ], 'Profil berhasil diperbarui');
    }
}

