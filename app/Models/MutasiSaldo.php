<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiSaldo extends Model
{
    use HasFactory;

    protected $table = 'mutasi_saldo';

    protected $fillable = [
        'nasabah_id',
        'tipe',
        'jumlah',
        'saldo_akhir',
        'referensi',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'saldo_akhir' => 'decimal:2',
            'tanggal' => 'datetime',
        ];
    }

    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(Nasabah::class, 'nasabah_id');
    }
}

