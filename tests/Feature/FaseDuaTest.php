<?php

namespace Tests\Feature;

use App\Models\JenisSampah;
use App\Models\KategoriSampah;
use App\Models\Nasabah;
use App\Models\Notifikasi;
use App\Models\Pengepul;
use App\Models\Penjemputan;
use App\Models\StokSampah;
use App\Models\TransaksiSetoran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaseDuaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $petugas;
    private User $warga;
    private Nasabah $nasabah;
    private JenisSampah $jenisPlastik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama' => 'Admin Utama',
            'no_hp' => '0811111111',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->petugas = User::create([
            'nama' => 'Petugas Lapangan',
            'no_hp' => '0822222222',
            'password' => Hash::make('password'),
            'role' => 'petugas',
            'status' => 'aktif',
        ]);

        $this->warga = User::create([
            'nama' => 'Budi Warga',
            'no_hp' => '0833333333',
            'password' => Hash::make('password'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        $this->nasabah = Nasabah::create([
            'user_id' => $this->warga->id,
            'no_rekening' => 'BS-0001',
            'saldo' => 50000.00,
        ]);

        $kategori = KategoriSampah::create(['nama_kategori' => 'Plastik']);
        $this->jenisPlastik = JenisSampah::create([
            'kategori_id' => $kategori->id,
            'nama_jenis' => 'Botol Plastik PET',
            'satuan' => 'kg',
            'harga_per_kg' => 4000.00,
            'status' => 'aktif',
        ]);

        StokSampah::create([
            'jenis_sampah_id' => $this->jenisPlastik->id,
            'berat_tersedia' => 50.00,
        ]);
    }

    public function test_nasabah_can_request_penjemputan_and_cancel_when_menunggu(): void
    {
        // 1. Nasabah ajukan penjemputan
        $response = $this->actingAs($this->warga, 'sanctum')
            ->postJson('/api/v1/me/penjemputan', [
                'alamat' => 'RT 02 RW 01 Dusun Krajan',
                'jadwal' => '2026-11-15 09:00:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'menunggu',
                ],
            ]);

        $penjemputanId = $response->json('data.id');

        // 2. Nasabah batalkan penjemputan saat status masih menunggu
        $batalResponse = $this->actingAs($this->warga, 'sanctum')
            ->patchJson("/api/v1/me/penjemputan/{$penjemputanId}/batal");

        $batalResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'dibatalkan',
                ],
            ]);
    }

    public function test_petugas_can_view_and_update_penjemputan_status(): void
    {
        $penjemputan = Penjemputan::create([
            'nasabah_id' => $this->nasabah->id,
            'alamat' => 'Rumah Pak Budi',
            'jadwal' => now()->addDay(),
            'status' => 'menunggu',
        ]);

        // Petugas mengubah status menjadi dijadwalkan
        $response = $this->actingAs($this->petugas, 'sanctum')
            ->patchJson("/api/v1/penjemputan/{$penjemputan->id}", [
                'status' => 'dijadwalkan',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'dijadwalkan',
                ],
            ]);

        // Notifikasi dibuat untuk nasabah
        $this->assertDatabaseHas('notifikasi', [
            'user_id' => $this->warga->id,
            'judul' => 'Update Status Penjemputan',
        ]);
    }

    public function test_nasabah_can_view_and_read_notifications(): void
    {
        $notif = Notifikasi::create([
            'user_id' => $this->warga->id,
            'judul' => 'Info Baru',
            'pesan' => 'Pesan pemberitahuan',
            'dibaca' => false,
        ]);

        // List notifikasi
        $this->actingAs($this->warga, 'sanctum')
            ->getJson('/api/v1/me/notifikasi')
            ->assertStatus(200)
            ->assertJsonFragment(['judul' => 'Info Baru']);

        // Baca notifikasi
        $this->actingAs($this->warga, 'sanctum')
            ->patchJson("/api/v1/me/notifikasi/{$notif->id}/baca")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['dibaca' => true],
            ]);

        $notif->refresh();
        $this->assertTrue((bool) $notif->dibaca);
    }

    public function test_petugas_can_get_rekap_harian(): void
    {
        $response = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/v1/rekap/harian?tanggal=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tanggal',
                    'ringkasan' => ['total_transaksi', 'total_berat', 'total_nilai'],
                    'rincian_per_jenis',
                    'daftar_transaksi',
                ],
            ]);
    }

    public function test_admin_can_record_penjualan_and_stock_is_deducted(): void
    {
        $pengepul = Pengepul::create([
            'nama' => 'CV Logam Abadi',
            'kontak' => '081999888777',
        ]);

        // Stok awal 50 kg. Jual 20 kg @ 4500
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/penjualan', [
                'pengepul_id' => $pengepul->id,
                'items' => [
                    [
                        'jenis_sampah_id' => $this->jenisPlastik->id,
                        'berat' => 20.0,
                        'harga' => 4500.0,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_berat' => 20.0,
                    'total_nilai' => 90000.0,
                ],
            ]);

        // Sisa stok harus berkurang menjadi 30 kg
        $stok = StokSampah::where('jenis_sampah_id', $this->jenisPlastik->id)->first();
        $this->assertEquals(30.00, (float) $stok->berat_tersedia);
    }

    public function test_admin_cannot_sell_more_than_available_stock(): void
    {
        $pengepul = Pengepul::create([
            'nama' => 'Pengepul Percobaan',
        ]);

        // Stok hanya 50 kg, coba jual 100 kg -> harus ditolak
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/penjualan', [
                'pengepul_id' => $pengepul->id,
                'items' => [
                    [
                        'jenis_sampah_id' => $this->jenisPlastik->id,
                        'berat' => 100.0,
                        'harga' => 4500.0,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_admin_can_access_laporan_and_export_csv(): void
    {
        // 1. Laporan setoran
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/laporan/setoran')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['ringkasan', 'data']]);

        // 2. Laporan saldo beredar
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/laporan/saldo-beredar')
            ->assertStatus(200);

        // 3. Export CSV
        $exportResponse = $this->actingAs($this->admin, 'sanctum')
            ->get('/api/v1/admin/laporan/saldo-beredar/export');

        $exportResponse->assertStatus(200);
        $this->assertTrue(str_contains($exportResponse->headers->get('content-type'), 'text/csv'));
    }
}

