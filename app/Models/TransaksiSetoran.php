<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransaksiSetoran extends Model
{
    use HasFactory;

    protected $table = 'transaksi_setoran';

    protected $fillable = [
        'nasabah_id',
        'petugas_id',
        'tanggal',
        'total_berat',
        'total_nilai',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'total_berat' => 'decimal:2',
            'total_nilai' => 'decimal:2',
        ];
    }

    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class, 'nasabah_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailSetoran::class, 'setoran_id');
    }
}

