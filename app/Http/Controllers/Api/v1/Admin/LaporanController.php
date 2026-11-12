<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DetailSetoran;
use App\Models\JenisSampah;
use App\Models\Nasabah;
use App\Models\Penarikan;
use App\Models\TransaksiSetoran;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    /**
     * Laporan transaksi setoran per periode.
     */
    public function setoran(Request $request): JsonResponse
    {
        $query = TransaksiSetoran::with([
            'nasabah.user:id,nama,no_hp',
            'petugas:id,nama',
            'details.jenisSampah',
        ]);

        if ($request->query('tanggal_dari')) {
            $query->whereDate('tanggal', '>=', $request->query('tanggal_dari'));
        }

        if ($request->query('tanggal_sampai')) {
            $query->whereDate('tanggal', '<=', $request->query('tanggal_sampai'));
        }

        if ($request->query('nasabah_id')) {
            $query->where('nasabah_id', $request->query('nasabah_id'));
        }

        $items = $query->orderByDesc('tanggal')->get();

        $totalBerat = (float) $items->sum('total_berat');
        $totalNilai = (float) $items->sum('total_nilai');

        $data = $items->map(function ($s) {
            return [
                'id' => $s->id,
                'tanggal' => $s->tanggal?->toIso8601String(),
                'no_rekening' => $s->nasabah ? $s->nasabah->no_rekening : '-',
                'nasabah' => $s->nasabah && $s->nasabah->user ? $s->nasabah->user->nama : '-',
                'petugas' => $s->petugas ? $s->petugas->nama : '-',
                'total_berat' => (float) $s->total_berat,
                'total_nilai' => (float) $s->total_nilai,
                'catatan' => $s->catatan,
            ];
        });

        return ApiResponse::success([
            'ringkasan' => [
                'total_transaksi' => $items->count(),
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
            ],
            'data' => $data,
        ], 'Laporan transaksi setoran');
    }

    /**
     * Laporan penarikan tunai per periode.
     */
    public function penarikan(Request $request): JsonResponse
    {
        $query = Penarikan::with([
            'nasabah.user:id,nama,no_hp',
            'diprosesOleh:id,nama',
        ]);

        if ($request->query('tanggal_dari')) {
            $query->whereDate('tanggal_ajuan', '>=', $request->query('tanggal_dari'));
        }

        if ($request->query('tanggal_sampai')) {
            $query->whereDate('tanggal_ajuan', '<=', $request->query('tanggal_sampai'));
        }

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $items = $query->orderByDesc('tanggal_ajuan')->get();

        $totalDisetujui = (float) $items->where('status', 'disetujui')->sum('jumlah');
        $totalPending = (float) $items->where('status', 'pending')->sum('jumlah');

        $data = $items->map(function ($p) {
            return [
                'id' => $p->id,
                'tanggal_ajuan' => $p->tanggal_ajuan?->toIso8601String(),
                'tanggal_proses' => $p->tanggal_proses?->toIso8601String(),
                'no_rekening' => $p->nasabah ? $p->nasabah->no_rekening : '-',
                'nasabah' => $p->nasabah && $p->nasabah->user ? $p->nasabah->user->nama : '-',
                'jumlah' => (float) $p->jumlah,
                'status' => $p->status,
                'diproses_oleh' => $p->diprosesOleh ? $p->diprosesOleh->nama : null,
                'catatan' => $p->catatan,
            ];
        });

        return ApiResponse::success([
            'ringkasan' => [
                'total_pengajuan' => $items->count(),
                'total_disetujui' => $totalDisetujui,
                'total_pending' => $totalPending,
            ],
            'data' => $data,
        ], 'Laporan penarikan');
    }

    /**
     * Laporan saldo beredar nasabah.
     */
    public function saldoBeredar(Request $request): JsonResponse
    {
        $items = Nasabah::with('user:id,nama,no_hp,alamat,status')
            ->orderByDesc('saldo')
            ->get();

        $totalSaldo = (float) $items->sum('saldo');

        $data = $items->map(function ($n) {
            return [
                'id' => $n->id,
                'no_rekening' => $n->no_rekening,
                'nama' => $n->user ? $n->user->nama : '-',
                'no_hp' => $n->user ? $n->user->no_hp : '-',
                'alamat' => $n->user ? $n->user->alamat : null,
                'status' => $n->user ? $n->user->status : 'nonaktif',
                'saldo' => (float) $n->saldo,
            ];
        });

        return ApiResponse::success([
            'ringkasan' => [
                'total_nasabah' => $items->count(),
                'total_saldo_beredar' => $totalSaldo,
            ],
            'data' => $data,
        ], 'Laporan saldo beredar');
    }

    /**
     * Laporan volume per jenis sampah.
     */
    public function perJenis(Request $request): JsonResponse
    {
        $query = DetailSetoran::with('jenisSampah.kategori');

        if ($request->query('tanggal_dari')) {
            $query->whereHas('setoran', function ($q) use ($request) {
                $q->whereDate('tanggal', '>=', $request->query('tanggal_dari'));
            });
        }

        if ($request->query('tanggal_sampai')) {
            $query->whereHas('setoran', function ($q) use ($request) {
                $q->whereDate('tanggal', '<=', $request->query('tanggal_sampai'));
            });
        }

        $items = $query->selectRaw('jenis_sampah_id, SUM(berat) as total_berat, SUM(subtotal) as total_nilai')
            ->groupBy('jenis_sampah_id')
            ->get();

        $totalBerat = (float) $items->sum('total_berat');
        $totalNilai = (float) $items->sum('total_nilai');

        $data = $items->map(function ($row) {
            return [
                'jenis_sampah_id' => $row->jenis_sampah_id,
                'nama_jenis' => $row->jenisSampah ? $row->jenisSampah->nama_jenis : '-',
                'kategori' => $row->jenisSampah && $row->jenisSampah->kategori ? $row->jenisSampah->kategori->nama_kategori : '-',
                'satuan' => $row->jenisSampah ? $row->jenisSampah->satuan : 'kg',
                'total_berat' => (float) $row->total_berat,
                'total_nilai' => (float) $row->total_nilai,
            ];
        });

        return ApiResponse::success([
            'ringkasan' => [
                'total_berat' => $totalBerat,
                'total_nilai' => $totalNilai,
            ],
            'data' => $data,
        ], 'Laporan sampah per jenis');
    }

    /**
     * Ekspor laporan ke format CSV / File Download.
     */
    public function export(Request $request, string $jenis): StreamedResponse|JsonResponse
    {
        $filename = "laporan_{$jenis}_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($jenis, $request) {
            $handle = fopen('php://output', 'w');
            // Menambahkan UTF-8 BOM untuk kompatibilitas Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            switch ($jenis) {
                case 'setoran':
                    fputcsv($handle, ['ID', 'Tanggal', 'No Rekening', 'Nama Nasabah', 'Petugas', 'Total Berat (kg)', 'Total Nilai (Rp)', 'Catatan']);
                    $query = TransaksiSetoran::with(['nasabah.user', 'petugas']);
                    if ($request->query('tanggal_dari')) $query->whereDate('tanggal', '>=', $request->query('tanggal_dari'));
                    if ($request->query('tanggal_sampai')) $query->whereDate('tanggal', '<=', $request->query('tanggal_sampai'));
                    $query->chunk(200, function ($rows) use ($handle) {
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->tanggal?->format('Y-m-d H:i:s'),
                                $r->nasabah?->no_rekening,
                                $r->nasabah?->user?->nama,
                                $r->petugas?->nama,
                                $r->total_berat,
                                $r->total_nilai,
                                $r->catatan,
                            ]);
                        }
                    });
                    break;

                case 'penarikan':
                    fputcsv($handle, ['ID', 'Tanggal Ajuan', 'Tanggal Proses', 'No Rekening', 'Nama Nasabah', 'Jumlah (Rp)', 'Status', 'Diproses Oleh', 'Catatan']);
                    $query = Penarikan::with(['nasabah.user', 'diprosesOleh']);
                    if ($request->query('tanggal_dari')) $query->whereDate('tanggal_ajuan', '>=', $request->query('tanggal_dari'));
                    if ($request->query('tanggal_sampai')) $query->whereDate('tanggal_ajuan', '<=', $request->query('tanggal_sampai'));
                    $query->chunk(200, function ($rows) use ($handle) {
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->tanggal_ajuan?->format('Y-m-d H:i:s'),
                                $r->tanggal_proses?->format('Y-m-d H:i:s'),
                                $r->nasabah?->no_rekening,
                                $r->nasabah?->user?->nama,
                                $r->jumlah,
                                $r->status,
                                $r->diprosesOleh?->nama,
                                $r->catatan,
                            ]);
                        }
                    });
                    break;

                case 'saldo-beredar':
                    fputcsv($handle, ['ID', 'No Rekening', 'Nama Nasabah', 'No HP', 'Alamat', 'Status', 'Saldo (Rp)']);
                    $query = Nasabah::with('user');
                    $query->chunk(200, function ($rows) use ($handle) {
                        foreach ($rows as $r) {
                            fputcsv($handle, [
                                $r->id,
                                $r->no_rekening,
                                $r->user?->nama,
                                $r->user?->no_hp,
                                $r->user?->alamat,
                                $r->user?->status,
                                $r->saldo,
                            ]);
                        }
                    });
                    break;

                case 'per-jenis':
                    fputcsv($handle, ['ID Jenis', 'Nama Jenis Sampah', 'Kategori', 'Satuan', 'Total Berat (kg)', 'Total Nilai (Rp)']);
                    $rows = DetailSetoran::with('jenisSampah.kategori')
                        ->selectRaw('jenis_sampah_id, SUM(berat) as total_berat, SUM(subtotal) as total_nilai')
                        ->groupBy('jenis_sampah_id')
                        ->get();
                    foreach ($rows as $r) {
                        fputcsv($handle, [
                            $r->jenis_sampah_id,
                            $r->jenisSampah?->nama_jenis,
                            $r->jenisSampah?->kategori?->nama_kategori,
                            $r->jenisSampah?->satuan ?? 'kg',
                            $r->total_berat,
                            $r->total_nilai,
                        ]);
                    }
                    break;
            }

            fclose($handle);
        }, 200, $headers);
    }
}

