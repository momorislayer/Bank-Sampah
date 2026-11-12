<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokSampah extends Model
{
    use HasFactory;

    protected $table = 'stok_sampah';

    protected $fillable = [
        'jenis_sampah_id',
        'berat_tersedia',
    ];

    protected function casts(): array
    {
        return [
            'berat_tersedia' => 'decimal:2',
        ];
    }

    public function jenisSampah(): BelongsTo
    {
        return $this->belongsTo(JenisSampah::class, 'jenis_sampah_id');
    }
}

