<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenjualanPengepul extends Model
{
    use HasFactory;

    protected $table = 'penjualan_pengepul';

    protected $fillable = [
        'pengepul_id',
        'tanggal',
        'total_berat',
        'total_nilai',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'total_berat' => 'decimal:2',
            'total_nilai' => 'decimal:2',
        ];
    }

    public function pengepul(): BelongsTo
    {
        return $this->belongsTo(Pengepul::class, 'pengepul_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }
}
