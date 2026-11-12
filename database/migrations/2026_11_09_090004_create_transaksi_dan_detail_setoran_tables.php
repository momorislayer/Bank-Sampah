<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_setoran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nasabah_id')->constrained('nasabah')->cascadeOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('tanggal')->useCurrent();
            $table->decimal('total_berat', 10, 2)->default(0);
            $table->decimal('total_nilai', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['nasabah_id', 'tanggal']);
        });

        Schema::create('detail_setoran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setoran_id')->constrained('transaksi_setoran')->cascadeOnDelete();
            $table->foreignId('jenis_sampah_id')->constrained('jenis_sampah')->cascadeOnDelete();
            $table->decimal('berat', 10, 2);
            $table->decimal('harga_saat_itu', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_setoran');
        Schema::dropIfExists('transaksi_setoran');
    }
};

