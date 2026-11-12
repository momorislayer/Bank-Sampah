<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JenisSampah extends Model
{
    use HasFactory;

    protected $table = 'jenis_sampah';

    protected $fillable = [
        'kategori_id',
        'nama_jenis',
        'satuan',
        'harga_per_kg',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga_per_kg' => 'decimal:2',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriSampah::class, 'kategori_id');
    }

    public function riwayatHarga(): HasMany
    {
        return $this->hasMany(RiwayatHarga::class, 'jenis_sampah_id');
    }

    public function stok(): HasOne
    {
        return $this->hasOne(StokSampah::class, 'jenis_sampah_id');
    }

    public function detailSetoran(): HasMany
    {
        return $this->hasMany(DetailSetoran::class, 'jenis_sampah_id');
    }
}

