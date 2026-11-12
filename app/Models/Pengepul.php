<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengepul extends Model
{
    use HasFactory;

    protected $table = 'pengepul';

    protected $fillable = [
        'nama',
        'kontak',
        'alamat',
    ];

    public function penjualan(): HasMany
    {
        return $this->hasMany(PenjualanPengepul::class, 'pengepul_id');
    }
}

