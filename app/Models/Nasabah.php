<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nasabah extends Model
{
    use HasFactory;

    protected $table = 'nasabah';

    protected $fillable = [
        'user_id',
        'no_rekening',
        'qr_code',
        'saldo',
    ];

    protected function casts(): array
    {
        return [
            'saldo' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaksiSetoran(): HasMany
    {
        return $this->hasMany(TransaksiSetoran::class, 'nasabah_id');
    }

    public function penarikan(): HasMany
    {
        return $this->hasMany(Penarikan::class, 'nasabah_id');
    }

    public function penjemputan(): HasMany
    {
        return $this->hasMany(Penjemputan::class, 'nasabah_id');
    }

    public function mutasiSaldo(): HasMany
    {
        return $this->hasMany(MutasiSaldo::class, 'nasabah_id');
    }

    /**
     * Generate format nomor rekening berikutnya: BS-0001, BS-0002, dst.
     */
    public static function generateNoRekening(): string
    {
        $last = static::orderByDesc('id')->first();
        $nextNumber = $last ? ($last->id + 1) : 1;
        return sprintf('BS-%04d', $nextNumber);
    }
}

