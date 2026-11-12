<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtikelEdukasi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtikelController extends Controller
{
    /**
     * Daftar seluruh artikel edukasi & pengumuman untuk admin.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = trim((string) $request->query('search', ''));

        $query = ArtikelEdukasi::with('penulis:id,nama');

        if ($search !== '') {
            $query->where('judul', 'like', "%{$search}%");
        }

        $artikel = $query->orderByDesc('created_at')->paginate($perPage);

        $transformed = $artikel->through(function ($item) {
            return [
                'id' => $item->id,
                'judul' => $item->judul,
                'isi' => $item->isi,
                'gambar' => $item->gambar,
                'penulis' => $item->penulis ? $item->penulis->nama : null,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        });

        return ApiResponse::paginated($transformed, 'Daftar artikel edukasi');
    }

    /**
     * Tambah artikel edukasi baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'isi' => ['required', 'string'],
            'gambar' => ['nullable', 'string', 'max:255'],
        ]);

        $artikel = ArtikelEdukasi::create([
            'penulis_id' => $request->user()->id,
            'judul' => $validated['judul'],
            'isi' => $validated['isi'],
            'gambar' => $validated['gambar'] ?? null,
        ]);

        return ApiResponse::success([
            'id' => $artikel->id,
            'judul' => $artikel->judul,
            'isi' => $artikel->isi,
            'gambar' => $artikel->gambar,
        ], 'Artikel edukasi berhasil ditambahkan', 201);
    }

    /**
     * Detail satu artikel edukasi.
     */
    public function show(int $id): JsonResponse
    {
        $artikel = ArtikelEdukasi::with('penulis:id,nama')->find($id);

        if (! $artikel) {
            return ApiResponse::error('Artikel edukasi tidak ditemukan', 404);
        }

        return ApiResponse::success([
            'id' => $artikel->id,
            'judul' => $artikel->judul,
            'isi' => $artikel->isi,
            'gambar' => $artikel->gambar,
            'penulis' => $artikel->penulis ? $artikel->penulis->nama : null,
            'created_at' => $artikel->created_at?->toIso8601String(),
        ], 'Detail artikel edukasi');
    }

    /**
     * Update artikel edukasi.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $artikel = ArtikelEdukasi::find($id);

        if (! $artikel) {
            return ApiResponse::error('Artikel edukasi tidak ditemukan', 404);
        }

        $validated = $request->validate([
            'judul' => ['sometimes', 'string', 'max:200'],
            'isi' => ['sometimes', 'string'],
            'gambar' => ['nullable', 'string', 'max:255'],
        ]);

        $artikel->update($validated);

        return ApiResponse::success([
            'id' => $artikel->id,
            'judul' => $artikel->judul,
            'isi' => $artikel->isi,
            'gambar' => $artikel->gambar,
        ], 'Artikel edukasi berhasil diperbarui');
    }

    /**
     * Hapus artikel edukasi.
     */
    public function destroy(int $id): JsonResponse
    {
        $artikel = ArtikelEdukasi::find($id);

        if (! $artikel) {
            return ApiResponse::error('Artikel edukasi tidak ditemukan', 404);
        }

        $artikel->delete();

        return ApiResponse::success(null, 'Artikel edukasi berhasil dihapus');
    }
}

