<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_saldo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nasabah_id')->constrained('nasabah')->cascadeOnDelete();
            $table->enum('tipe', ['debit', 'kredit']);
            $table->decimal('jumlah', 15, 2);
            $table->decimal('saldo_akhir', 15, 2);
            $table->string('referensi', 50)->nullable();
            $table->dateTime('tanggal')->useCurrent();
            $table->timestamps();

            $table->index(['nasabah_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_saldo');
    }
};

