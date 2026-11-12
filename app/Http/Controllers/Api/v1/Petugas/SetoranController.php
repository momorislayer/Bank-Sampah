<?php

namespace App\Http\Controllers\Api\v1\Petugas;

use App\Http\Controllers\Controller;
use App\Models\DetailSetoran;
use App\Models\JenisSampah;
use App\Models\MutasiSaldo;
use App\Models\Nasabah;
use App\Models\Notifikasi;
use App\Models\StokSampah;
use App\Models\TransaksiSetoran;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetoranController extends Controller
{
    /**
     * Daftar seluruh transaksi setoran (filter pencarian & tanggal).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = trim((string) $request->query('search', ''));

        $query = TransaksiSetoran::with([
            'nasabah.user:id,nama,no_hp',
            'petugas:id,nama',
        ]);

        if ($request->query('nasabah_id')) {
            $query->where('nasabah_id', $request->query('nasabah_id'));
        }

        if ($request->query('tanggal_dari')) {
            $query->whereDate('tanggal', '>=', $request->query('tanggal_dari'));
        }

        if ($request->query('tanggal_sampai')) {
            $query->whereDate('tanggal', '<=', $request->query('tanggal_sampai'));
        }

        if ($search !== '') {
            $query->whereHas('nasabah', function ($q) use ($search) {
                $q->where('no_rekening', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('nama', 'like', "%{$search}%")
                            ->orWhere('no_hp', 'like', "%{$search}%");
                    });
            });
        }

        $setoran = $query->orderByDesc('tanggal')->paginate($perPage);

        $transformed = $setoran->through(function ($item) {
            return [
                'id' => $item->id,
                'tanggal' => $item->tanggal?->toIso8601String(),
                'nasabah' => [
                    'id' => $item->nasabah_id,
                    'no_rekening' => $item->nasabah ? $item->nasabah->no_rekening : '-',
                    'nama' => $item->nasabah && $item->nasabah->user ? $item->nasabah->user->nama : '-',
                ],
                'petugas' => $item->petugas ? $item->petugas->nama : '-',
                'total_berat' => (float) $item->total_berat,
                'total_nilai' => (float) $item->total_nilai,
                'catatan' => $item->catatan,
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar transaksi setoran');
    }

    /**
     * Simpan setoran baru (Transaksi Atomik dengan Row Lock).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nasabah_id' => ['required', 'exists:nasabah,id'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.jenis_sampah_id' => ['required', 'exists:jenis_sampah,id'],
            'items.*.berat' => ['required', 'numeric', 'gt:0'],
        ]);

        $petugasId = $request->user()->id;

        $setoran = DB::transaction(function () use ($validated, $petugasId) {
            // Aturan Bisnis 4: Row lock pada baris nasabah
            /** @var Nasabah $nasabah */
            $nasabah = Nasabah::where('id', $validated['nasabah_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $totalBerat = 0.0;
            $totalNilai = 0.0;
            $itemsProcessed = [];

            // Kumpulkan ID jenis sampah
            $jenisIds = collect($validated['items'])->pluck('jenis_sampah_id')->unique()->all();
            $jenisList = JenisSampah::whereIn('id', $jenisIds)->get()->keyBy('id');

            foreach ($validated['items'] as $item) {
                $jenis = $jenisList->get($item['jenis_sampah_id']);

                if (! $jenis || $jenis->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'items' => ["Jenis sampah '{$jenis?->nama_jenis}' tidak aktif atau tidak ditemukan."],
                    ]);
                }

                $berat = round((float) $item['berat'], 2);
                $hargaSaatItu = (float) $jenis->harga_per_kg;
                $subtotal = round($berat * $hargaSaatItu, 2);

                $totalBerat += $berat;
                $totalNilai += $subtotal;

                $itemsProcessed[] = [
                    'jenis_sampah_id' => $jenis->id,
                    'berat' => $berat,
                    'harga_saat_itu' => $hargaSaatItu,
                    'subtotal' => $subtotal,
                ];

                // Aturan Bisnis 9: Tambah stok sampah
                $stok = StokSampah::firstOrCreate(
                    ['jenis_sampah_id' => $jenis->id],
                    ['berat_tersedia' => 0.00]
                );
                $stok->increment('berat_tersedia', $berat);
            }

            // 1. Simpan transaksi setoran
            $transaksi = TransaksiSetoran::create([
                'nasabah_id' => $nasabah->id,
                'petugas_id' => $petugasId,
                'tanggal' => now(),
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
                'catatan' => $validated['catatan'] ?? null,
            ]);

            // 2. Simpan detail transaksi
            foreach ($itemsProcessed as $detail) {
                DetailSetoran::create([
                    'setoran_id' => $transaksi->id,
                    'jenis_sampah_id' => $detail['jenis_sampah_id'],
                    'berat' => $detail['berat'],
                    'harga_saat_itu' => $detail['harga_saat_itu'],
                    'subtotal' => $detail['subtotal'],
                ]);
            }

            // 3. Tambah saldo nasabah
            $nasabah->saldo = round((float) $nasabah->saldo + $totalNilai, 2);
            $nasabah->save();

            // 4. Catat mutasi saldo (tipe kredit)
            MutasiSaldo::create([
                'nasabah_id' => $nasabah->id,
                'tipe' => 'kredit',
                'jumlah' => $totalNilai,
                'saldo_akhir' => $nasabah->saldo,
                'referensi' => "setoran:{$transaksi->id}",
                'tanggal' => now(),
            ]);

            // 5. Kirim notifikasi sistem ke nasabah
            if ($nasabah->user_id) {
                Notifikasi::create([
                    'user_id' => $nasabah->user_id,
                    'judul' => 'Setoran Sampah Berhasil',
                    'pesan' => "Setoran sampah sebesar {$totalBerat} kg telah tercatat. Saldo Anda bertambah Rp " . number_format($totalNilai, 0, ',', '.') . ".",
                    'dibaca' => false,
                ]);
            }

            return $transaksi;
        });

        // Load relasi lengkap untuk response
        $setoran->load(['nasabah.user:id,nama,no_hp', 'petugas:id,nama', 'details.jenisSampah']);

        return ApiResponse::success([
            'id' => $setoran->id,
            'tanggal' => $setoran->tanggal?->toIso8601String(),
            'nasabah' => [
                'id' => $setoran->nasabah_id,
                'no_rekening' => $setoran->nasabah->no_rekening,
                'nama' => $setoran->nasabah->user ? $setoran->nasabah->user->nama : '-',
                'saldo_baru' => (float) $setoran->nasabah->saldo,
            ],
            'petugas' => $setoran->petugas ? $setoran->petugas->nama : '-',
            'total_berat' => (float) $setoran->total_berat,
            'total_nilai' => (float) $setoran->total_nilai,
            'catatan' => $setoran->catatan,
            'items' => $setoran->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'jenis_sampah_id' => $d->jenis_sampah_id,
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'berat' => (float) $d->berat,
                    'harga_saat_itu' => (float) $d->harga_saat_itu,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
        ], 'Setoran berhasil disimpan', 201);
    }

    /**
     * Detail satu transaksi setoran.
     */
    public function show(int $id): JsonResponse
    {
        $setoran = TransaksiSetoran::with([
            'nasabah.user:id,nama,no_hp,alamat',
            'petugas:id,nama',
            'details.jenisSampah.kategori',
        ])->find($id);

        if (! $setoran) {
            return ApiResponse::error('Data setoran tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $setoran->id,
            'tanggal' => $setoran->tanggal?->toIso8601String(),
            'nasabah' => [
                'id' => $setoran->nasabah_id,
                'no_rekening' => $setoran->nasabah ? $setoran->nasabah->no_rekening : '-',
                'nama' => $setoran->nasabah && $setoran->nasabah->user ? $setoran->nasabah->user->nama : '-',
                'no_hp' => $setoran->nasabah && $setoran->nasabah->user ? $setoran->nasabah->user->no_hp : '-',
                'alamat' => $setoran->nasabah && $setoran->nasabah->user ? $setoran->nasabah->user->alamat : '-',
            ],
            'petugas' => $setoran->petugas ? $setoran->petugas->nama : '-',
            'total_berat' => (float) $setoran->total_berat,
            'total_nilai' => (float) $setoran->total_nilai,
            'catatan' => $setoran->catatan,
            'items' => $setoran->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'jenis_sampah_id' => $d->jenis_sampah_id,
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'kategori' => $d->jenisSampah && $d->jenisSampah->kategori ? $d->jenisSampah->kategori->nama_kategori : '-',
                    'satuan' => $d->jenisSampah ? $d->jenisSampah->satuan : 'kg',
                    'berat' => (float) $d->berat,
                    'harga_saat_itu' => (float) $d->harga_saat_itu,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
        ], 'Detail setoran');
    }

    /**
     * Data bukti setoran print-friendly (format JSON terstruktur).
     */
    public function bukti(int $id): JsonResponse
    {
        $setoran = TransaksiSetoran::with([
            'nasabah.user:id,nama,no_hp,alamat',
            'petugas:id,nama',
            'details.jenisSampah',
        ])->find($id);

        if (! $setoran) {
            return ApiResponse::error('Data setoran tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'judul' => 'BUKTI SETORAN SAMPAH',
            'nomor_transaksi' => sprintf('TRX-%06d', $setoran->id),
            'tanggal' => $setoran->tanggal?->format('d/m/Y H:i'),
            'petugas' => $setoran->petugas ? $setoran->petugas->nama : '-',
            'nasabah' => [
                'no_rekening' => $setoran->nasabah ? $setoran->nasabah->no_rekening : '-',
                'nama' => $setoran->nasabah && $setoran->nasabah->user ? $setoran->nasabah->user->nama : '-',
                'alamat' => $setoran->nasabah && $setoran->nasabah->user ? $setoran->nasabah->user->alamat : '-',
            ],
            'items' => $setoran->details->map(function ($d) {
                return [
                    'nama_jenis' => $d->jenisSampah ? $d->jenisSampah->nama_jenis : '-',
                    'berat' => (float) $d->berat,
                    'satuan' => $d->jenisSampah ? $d->jenisSampah->satuan : 'kg',
                    'harga_per_kg' => (float) $d->harga_saat_itu,
                    'subtotal' => (float) $d->subtotal,
                ];
            }),
            'total_berat' => (float) $setoran->total_berat,
            'total_nilai' => (float) $setoran->total_nilai,
            'saldo_terkini' => (float) ($setoran->nasabah ? $setoran->nasabah->saldo : 0),
            'catatan' => $setoran->catatan,
        ], 'Data bukti setoran cetak');
    }
}

