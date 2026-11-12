<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailPenjualan;
use App\Models\JenisSampah;
use App\Models\PenjualanPengepul;
use App\Models\StokSampah;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenjualanController extends Controller
{
    /**
     * Daftar seluruh transaksi penjualan sampah ke pengepul.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = PenjualanPengepul::with('pengepul:id,nama,kontak');

        if ($request->query('pengepul_id')) {
            $query->where('pengepul_id', $request->query('pengepul_id'));
        }

        if ($request->query('tanggal_dari')) {
            $query->whereDate('tanggal', '>=', $request->query('tanggal_dari'));
        }

        if ($request->query('tanggal_sampai')) {
            $query->whereDate('tanggal', '<=', $request->query('tanggal_sampai'));
        }

        $penjualan = $query->orderByDesc('tanggal')->paginate($perPage);

        $transformed = $penjualan->through(function ($item) {
            return [
                'id' => $item->id,
                'tanggal' => $item->tanggal?->toIso8601String(),
                'pengepul' => $item->pengepul ? [
                    'id' => $item->pengepul->id,
                    'nama' => $item->pengepul->nama,
                    'kontak' => $item->pengepul->kontak,
                ] : null,
                'total_berat' => (float) $item->total_berat,
                'total_nilai' => (float) $item->total_nilai,
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar transaksi penjualan ke pengepul');
    }

    /**
     * Catat penjualan sampah ke pengepul (Transaksi Atomik & Pengurangan Stok).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pengepul_id' => ['required', 'exists:pengepul,id'],
            'tanggal' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.jenis_sampah_id' => ['required', 'exists:jenis_sampah,id'],
            'items.*.berat' => ['required', 'numeric', 'gt:0'],
            'items.*.harga' => ['required', 'numeric', 'min:0'],
        ]);

        $penjualan = DB::transaction(function () use ($validated) {
            $totalBerat = 0.0;
            $totalNilai = 0.0;
            $itemsProcessed = [];

            foreach ($validated['items'] as $item) {
                $jenisId = $item['jenis_sampah_id'];
                $berat = round((float) $item['berat'], 2);
                $harga = (float) $item['harga'];
                $subtotal = round($berat * $harga, 2);

                // Aturan Bisnis 9: Stok berkurang saat dijual. Tolak penjualan melebihi stok.
                /** @var StokSampah $stok */
                $stok = StokSampah::where('jenis_sampah_id', $jenisId)
                    ->lockForUpdate()
                    ->first();

                $beratTersedia = $stok ? (float) $stok->berat_tersedia : 0.0;
                $jenis = JenisSampah::find($jenisId);

                if ($berat > $beratTersedia) {
                    $namaJenis = $jenis ? $jenis->nama_jenis : "ID: {$jenisId}";
                    throw ValidationException::withMessages([
                        'items' => ["Penjualan melebihi stok tersedia untuk '{$namaJenis}'. Stok tersedia: {$beratTersedia} kg, diminta: {$berat} kg."],
                    ]);
                }

                // Kurangi stok
                $stok->decrement('berat_tersedia', $berat);

                $totalBerat += $berat;
                $totalNilai += $subtotal;

                $itemsProcessed[] = [
                    'jenis_sampah_id' => $jenisId,
                    'berat' => $berat,
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ];
            }

            // Simpan penjualan induk
            $penjualanInduk = PenjualanPengepul::create([
                'pengepul_id' => $validated['pengepul_id'],
                'tanggal' => $validated['tanggal'] ?? now(),
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
            ]);

            // Simpan detail per jenis sampah
            foreach ($itemsProcessed as $detail) {
                DetailPenjualan::create([
                    'penjualan_id' => $penjualanInduk->id,
                    'jenis_sampah_id' => $detail['jenis_sampah_id'],
                    'berat' => $detail['berat'],
                    'harga' => $detail['harga'],
                    'subtotal' => $detail['subtotal'],
                ]);
            }

            return $penjualanInduk;
        });

        $penjualan->load(['pengepul', 'details.jenisSampah']);

        return ApiResponse::success([
            'id' => $penjualan->id,
            'tanggal' => $penjualan->tanggal?->toIso8601String(),
            'pengepul' => $penjualan->pengepul ? [
                'id' => $penjualan->pengepul->id,
                'nama' => $penjualan->pengepul->nama,
            ] : null,
            'total_berat' => (float) $penjualan->total_berat,
            'total_nilai' => (float) $penjualan->total_nilai,
            'items' => $penjualan->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'jenis_sampah_id' => $d->jenis_sampah_id,
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'berat' => (float) $d->berat,
                    'harga' => (float) $d->harga,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
        ], 'Penjualan ke pengepul berhasil dicatat dan stok telah diperbarui', 201);
    }

    /**
     * Detail satu transaksi penjualan ke pengepul.
     */
    public function show(int $id): JsonResponse
    {
        $penjualan = PenjualanPengepul::with(['pengepul', 'details.jenisSampah'])->find($id);

        if (! $penjualan) {
            return ApiResponse::error('Data penjualan tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $penjualan->id,
            'tanggal' => $penjualan->tanggal?->toIso8601String(),
            'pengepul' => $penjualan->pengepul ? [
                'id' => $penjualan->pengepul->id,
                'nama' => $penjualan->pengepul->nama,
                'kontak' => $penjualan->pengepul->kontak,
                'alamat' => $penjualan->pengepul->alamat,
            ] : null,
            'total_berat' => (float) $penjualan->total_berat,
            'total_nilai' => (float) $penjualan->total_nilai,
            'items' => $penjualan->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'jenis_sampah_id' => $d->jenis_sampah_id,
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'berat' => (float) $d->berat,
                    'harga' => (float) $d->harga,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
        ], 'Detail transaksi penjualan');
    }
}

