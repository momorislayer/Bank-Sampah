<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtikelEdukasi extends Model
{
    use HasFactory;

    protected $table = 'artikel_edukasi';

    protected $fillable = [
        'penulis_id',
        'judul',
        'isi',
        'gambar',
    ];

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }
}

