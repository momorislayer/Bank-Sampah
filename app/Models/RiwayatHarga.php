<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatHarga extends Model
{
    use HasFactory;

    protected $table = 'riwayat_harga';

    protected $fillable = [
        'jenis_sampah_id',
        'harga',
        'berlaku_mulai',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'berlaku_mulai' => 'datetime',
        ];
    }

    public function jenisSampah(): BelongsTo
    {
        return $this->belongsTo(JenisSampah::class, 'jenis_sampah_id');
    }
}

