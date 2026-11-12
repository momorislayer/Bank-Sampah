<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_sampah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_sampah_id')->unique()->constrained('jenis_sampah')->cascadeOnDelete();
            $table->decimal('berat_tersedia', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pengepul', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('kontak', 50)->nullable();
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        Schema::create('penjualan_pengepul', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengepul_id')->constrained('pengepul')->cascadeOnDelete();
            $table->dateTime('tanggal')->useCurrent();
            $table->decimal('total_berat', 12, 2)->default(0);
            $table->decimal('total_nilai', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_pengepul');
        Schema::dropIfExists('pengepul');
        Schema::dropIfExists('stok_sampah');
    }
};

