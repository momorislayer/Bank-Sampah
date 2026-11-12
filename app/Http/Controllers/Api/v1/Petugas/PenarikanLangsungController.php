<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\MutasiSaldo;
use App\Models\Nasabah;
use App\Models\Notifikasi;
use App\Models\Penarikan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenarikanLangsungController extends Controller
{
    /**
     * Catat penarikan langsung tunai atas nama nasabah oleh petugas/admin (langsung disetujui).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nasabah_id' => ['required', 'exists:nasabah,id'],
            'jumlah' => ['required', 'numeric', 'min:1000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $petugasId = $request->user()->id;
        $jumlah = (float) $validated['jumlah'];

        $penarikan = DB::transaction(function () use ($validated, $petugasId, $jumlah) {
            // Aturan Bisnis 4: Row lock pada nasabah
            /** @var Nasabah $nasabah */
            $nasabah = Nasabah::where('id', $validated['nasabah_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Aturan Bisnis 5: Saldo tidak boleh negatif
            if ($jumlah > (float) $nasabah->saldo) {
                throw ValidationException::withMessages([
                    'jumlah' => ['Jumlah penarikan melebihi saldo nasabah saat ini (Rp ' . number_format($nasabah->saldo, 0, ',', '.') . ').'],
                ]);
            }

            // 1. Buat record penarikan langsung disetujui
            $record = Penarikan::create([
                'nasabah_id' => $nasabah->id,
                'jumlah' => $jumlah,
                'status' => 'disetujui',
                'tanggal_ajuan' => now(),
                'tanggal_proses' => now(),
                'diproses_oleh' => $petugasId,
                'catatan' => $validated['catatan'] ?? 'Penarikan tunai langsung di tempat',
            ]);

            // 2. Kurangi saldo nasabah
            $nasabah->saldo = round((float) $nasabah->saldo - $jumlah, 2);
            $nasabah->save();

            // 3. Catat mutasi saldo (tipe debit)
            MutasiSaldo::create([
                'nasabah_id' => $nasabah->id,
                'tipe' => 'debit',
                'jumlah' => $jumlah,
                'saldo_akhir' => $nasabah->saldo,
                'referensi' => "penarikan:{$record->id}",
                'tanggal' => now(),
            ]);

            // 4. Kirim notifikasi sistem ke nasabah
            if ($nasabah->user_id) {
                Notifikasi::create([
                    'user_id' => $nasabah->user_id,
                    'judul' => 'Penarikan Tunai Berhasil',
                    'pesan' => "Penarikan tunai sebesar Rp " . number_format($jumlah, 0, ',', '.') . " telah diserahkan. Sisa saldo Anda: Rp " . number_format($nasabah->saldo, 0, ',', '.') . ".",
                    'dibaca' => false,
                ]);
            }

            return $record;
        });

        $penarikan->load(['nasabah.user:id,nama,no_hp', 'diprosesOleh:id,nama']);

        return ApiResponse::success([
            'id' => $penarikan->id,
            'nasabah' => [
                'id' => $penarikan->nasabah_id,
                'no_rekening' => $penarikan->nasabah->no_rekening,
                'nama' => $penarikan->nasabah->user ? $penarikan->nasabah->user->nama : '-',
                'sisa_saldo' => (float) $penarikan->nasabah->saldo,
            ],
            'jumlah' => (float) $penarikan->jumlah,
            'status' => $penarikan->status,
            'tanggal_proses' => $penarikan->tanggal_proses?->toIso8601String(),
            'diproses_oleh' => $penarikan->diprosesOleh ? $penarikan->diprosesOleh->nama : '-',
            'catatan' => $penarikan->catatan,
        ], 'Penarikan tunai langsung berhasil dicatat', 201);
    }
}

