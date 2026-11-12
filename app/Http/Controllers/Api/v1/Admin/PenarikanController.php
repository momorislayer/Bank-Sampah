<?php

namespace App\Http\Controllers\Api\v1\Admin;

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

class PenarikanController extends Controller
{
    /**
     * Daftar seluruh pengajuan penarikan (filter status).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $query = Penarikan::with([
            'nasabah.user:id,nama,no_hp,alamat',
            'diprosesOleh:id,nama',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        $penarikan = $query->orderByDesc('tanggal_ajuan')->paginate($perPage);

        $transformed = $penarikan->through(function ($item) {
            return [
                'id' => $item->id,
                'nasabah' => [
                    'id' => $item->nasabah_id,
                    'no_rekening' => $item->nasabah ? $item->nasabah->no_rekening : '-',
                    'nama' => $item->nasabah && $item->nasabah->user ? $item->nasabah->user->nama : '-',
                    'no_hp' => $item->nasabah && $item->nasabah->user ? $item->nasabah->user->no_hp : '-',
                    'saldo_saat_ini' => (float) ($item->nasabah ? $item->nasabah->saldo : 0),
                ],
                'jumlah' => (float) $item->jumlah,
                'status' => $item->status,
                'tanggal_ajuan' => $item->tanggal_ajuan?->toIso8601String(),
                'tanggal_proses' => $item->tanggal_proses?->toIso8601String(),
                'diproses_oleh' => $item->diprosesOleh ? $item->diprosesOleh->nama : null,
                'catatan' => $item->catatan,
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar pengajuan penarikan');
    }

    /**
     * Setujui penarikan (Transaksi Atomik: kurangi saldo nasabah dan catat mutasi debit).
     */
    public function setujui(Request $request, int $id): JsonResponse
    {
        $penarikan = Penarikan::find($id);

        if (! $penarikan) {
            return ApiResponse::error('Pengajuan penarikan tidak ditemukan', 404);
        }

        if ($penarikan->status !== 'pending') {
            return ApiResponse::error("Pengajuan penarikan sudah berstatus '{$penarikan->status}' dan tidak dapat diproses lagi", 422);
        }

        $adminId = $request->user()->id;

        DB::transaction(function () use ($penarikan, $adminId) {
            // Aturan Bisnis 4: Row lock pada nasabah
            /** @var Nasabah $nasabah */
            $nasabah = Nasabah::where('id', $penarikan->nasabah_id)
                ->lockForUpdate()
                ->firstOrFail();

            $jumlah = (float) $penarikan->jumlah;

            // Aturan Bisnis 5: Saldo tidak boleh negatif
            if ($jumlah > (float) $nasabah->saldo) {
                throw ValidationException::withMessages([
                    'jumlah' => ['Saldo nasabah saat ini (Rp ' . number_format($nasabah->saldo, 0, ',', '.') . ') tidak mencukupi untuk penarikan ini.'],
                ]);
            }

            // 1. Kurangi saldo nasabah
            $nasabah->saldo = round((float) $nasabah->saldo - $jumlah, 2);
            $nasabah->save();

            // 2. Update status penarikan
            $penarikan->update([
                'status' => 'disetujui',
                'tanggal_proses' => now(),
                'diproses_oleh' => $adminId,
            ]);

            // 3. Catat mutasi saldo (tipe debit)
            MutasiSaldo::create([
                'nasabah_id' => $nasabah->id,
                'tipe' => 'debit',
                'jumlah' => $jumlah,
                'saldo_akhir' => $nasabah->saldo,
                'referensi' => "penarikan:{$penarikan->id}",
                'tanggal' => now(),
            ]);

            // 4. Kirim notifikasi sistem ke nasabah
            if ($nasabah->user_id) {
                Notifikasi::create([
                    'user_id' => $nasabah->user_id,
                    'judul' => 'Pengajuan Penarikan Disetujui',
                    'pesan' => "Penarikan tunai Anda sebesar Rp " . number_format($jumlah, 0, ',', '.') . " telah disetujui. Silakan mengambil uang tunai di kantor Bank Sampah Desa.",
                    'dibaca' => false,
                ]);
            }
        });

        $penarikan->refresh()->load(['nasabah.user:id,nama', 'diprosesOleh:id,nama']);

        return ApiResponse::success([
            'id' => $penarikan->id,
            'status' => $penarikan->status,
            'jumlah' => (float) $penarikan->jumlah,
            'tanggal_proses' => $penarikan->tanggal_proses?->toIso8601String(),
            'diproses_oleh' => $penarikan->diprosesOleh ? $penarikan->diprosesOleh->nama : null,
            'sisa_saldo_nasabah' => (float) $penarikan->nasabah->saldo,
        ], 'Penarikan berhasil disetujui dan saldo telah diperbarui');
    }

    /**
     * Tolak pengajuan penarikan (dengan catatan alasan penolakan).
     */
    public function tolak(Request $request, int $id): JsonResponse
    {
        $penarikan = Penarikan::with('nasabah')->find($id);

        if (! $penarikan) {
            return ApiResponse::error('Pengajuan penarikan tidak ditemukan', 404);
        }

        if ($penarikan->status !== 'pending') {
            return ApiResponse::error("Pengajuan penarikan sudah berstatus '{$penarikan->status}' dan tidak dapat ditolak lagi", 422);
        }

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'max:255'],
        ]);

        $adminId = $request->user()->id;

        $penarikan->update([
            'status' => 'ditolak',
            'tanggal_proses' => now(),
            'diproses_oleh' => $adminId,
            'catatan' => $validated['catatan'],
        ]);

        // Notifikasi ke nasabah
        if ($penarikan->nasabah && $penarikan->nasabah->user_id) {
            Notifikasi::create([
                'user_id' => $penarikan->nasabah->user_id,
                'judul' => 'Pengajuan Penarikan Ditolak',
                'pesan' => "Pengajuan penarikan sebesar Rp " . number_format($penarikan->jumlah, 0, ',', '.') . " ditolak dengan alasan: {$validated['catatan']}.",
                'dibaca' => false,
            ]);
        }

        $penarikan->load(['diprosesOleh:id,nama']);

        return ApiResponse::success([
            'id' => $penarikan->id,
            'status' => $penarikan->status,
            'catatan' => $penarikan->catatan,
            'tanggal_proses' => $penarikan->tanggal_proses?->toIso8601String(),
            'diproses_oleh' => $penarikan->diprosesOleh ? $penarikan->diprosesOleh->nama : null,
        ], 'Pengajuan penarikan berhasil ditolak');
    }
}

