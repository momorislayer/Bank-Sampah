<?php

namespace Tests\Feature;

use App\Models\JenisSampah;
use App\Models\KategoriSampah;
use App\Models\MutasiSaldo;
use App\Models\Nasabah;
use App\Models\Penarikan;
use App\Models\StokSampah;
use App\Models\TransaksiSetoran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetoranDanPenarikanTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;
    private User $admin;
    private User $warga;
    private Nasabah $nasabah;
    private JenisSampah $botolPlastik;
    private JenisSampah $kardus;

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
            'nama' => 'Petugas Satu',
            'no_hp' => '0822222222',
            'password' => Hash::make('password'),
            'role' => 'petugas',
            'status' => 'aktif',
        ]);

        $this->warga = User::create([
            'nama' => 'Warga Satu',
            'no_hp' => '0833333333',
            'password' => Hash::make('password'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        $this->nasabah = Nasabah::create([
            'user_id' => $this->warga->id,
            'no_rekening' => 'BS-0001',
            'saldo' => 10000.00,
        ]);

        $kategori = KategoriSampah::create([
            'nama_kategori' => 'Plastik & Kertas',
        ]);

        $this->botolPlastik = JenisSampah::create([
            'kategori_id' => $kategori->id,
            'nama_jenis' => 'Botol Plastik PET',
            'satuan' => 'kg',
            'harga_per_kg' => 4000.00,
            'status' => 'aktif',
        ]);

        $this->kardus = JenisSampah::create([
            'kategori_id' => $kategori->id,
            'nama_jenis' => 'Kardus Bekas',
            'satuan' => 'kg',
            'harga_per_kg' => 2000.00,
            'status' => 'aktif',
        ]);
    }

    public function test_petugas_can_store_setoran_and_balance_is_credited_with_ledger_mutasi(): void
    {
        // Setor: 2.5 kg botol @ 4000 = 10.000
        //        5.0 kg kardus @ 2000 = 10.000
        // Total nilai = 20.000. Saldo awal 10.000 -> Saldo akhir 30.000.
        $payload = [
            'nasabah_id' => $this->nasabah->id,
            'catatan' => 'Setoran minggu pertama',
            'items' => [
                [
                    'jenis_sampah_id' => $this->botolPlastik->id,
                    'berat' => 2.5,
                ],
                [
                    'jenis_sampah_id' => $this->kardus->id,
                    'berat' => 5.0,
                ],
            ],
        ];

        $response = $this->actingAs($this->petugas, 'sanctum')
            ->postJson('/api/v1/setoran', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_berat' => 7.5,
                    'total_nilai' => 20000.0,
                    'nasabah' => [
                        'saldo_baru' => 30000.0,
                    ],
                ],
            ]);

        // Cek update saldo nasabah di database
        $this->nasabah->refresh();
        $this->assertEquals(30000.00, (float) $this->nasabah->saldo);

        // Cek pencatatan mutasi_saldo (buku besar kredit)
        $this->assertDatabaseHas('mutasi_saldo', [
            'nasabah_id' => $this->nasabah->id,
            'tipe' => 'kredit',
            'jumlah' => 20000.00,
            'saldo_akhir' => 30000.00,
        ]);

        // Cek stok sampah bertambah
        $stokBotol = StokSampah::where('jenis_sampah_id', $this->botolPlastik->id)->first();
        $this->assertEquals(2.5, (float) $stokBotol->berat_tersedia);

        $stokKardus = StokSampah::where('jenis_sampah_id', $this->kardus->id)->first();
        $this->assertEquals(5.0, (float) $stokKardus->berat_tersedia);
    }

    public function test_harga_historis_remains_unchanged_when_price_changes_later(): void
    {
        // 1. Simpan setoran dengan harga 4000
        $this->actingAs($this->petugas, 'sanctum')
            ->postJson('/api/v1/setoran', [
                'nasabah_id' => $this->nasabah->id,
                'items' => [
                    ['jenis_sampah_id' => $this->botolPlastik->id, 'berat' => 3.0],
                ],
            ])->assertStatus(201);

        $setoran = TransaksiSetoran::first();
        $detail = $setoran->details->first();
        $this->assertEquals(4000.00, (float) $detail->harga_saat_itu);
        $this->assertEquals(12000.00, (float) $detail->subtotal);

        // 2. Admin menaikkan harga jenis sampah menjadi 6000
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/jenis-sampah/{$this->botolPlastik->id}", [
                'harga_per_kg' => 6000.00,
            ])->assertStatus(200);

        // 3. Pastikan detail setoran lama TETAP 4000 dan subtotal 12000
        $detail->refresh();
        $this->assertEquals(4000.00, (float) $detail->harga_saat_itu);
        $this->assertEquals(12000.00, (float) $detail->subtotal);
    }

    public function test_nasabah_cannot_request_withdrawal_exceeding_saldo(): void
    {
        // Saldo nasabah 10.000, ajukan 15.000 -> harus ditolak
        $response = $this->actingAs($this->warga, 'sanctum')
            ->postJson('/api/v1/me/penarikan', [
                'jumlah' => 15000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_nasabah_can_request_withdrawal_and_saldo_is_not_deducted_yet(): void
    {
        // Saldo nasabah 10.000, ajukan 5.000
        $response = $this->actingAs($this->warga, 'sanctum')
            ->postJson('/api/v1/me/penarikan', [
                'jumlah' => 5000,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'jumlah' => 5000.0,
                ],
            ]);

        // Saldo belum boleh berkurang saat status pending
        $this->nasabah->refresh();
        $this->assertEquals(10000.00, (float) $this->nasabah->saldo);
    }

    public function test_admin_can_approve_withdrawal_and_balance_is_debited_with_mutasi(): void
    {
        // Nasabah ajukan penarikan 6.000
        $penarikan = Penarikan::create([
            'nasabah_id' => $this->nasabah->id,
            'jumlah' => 6000.00,
            'status' => 'pending',
            'tanggal_ajuan' => now(),
        ]);

        // Admin menyetujui
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/penarikan/{$penarikan->id}/setujui");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'disetujui',
                    'sisa_saldo_nasabah' => 4000.0,
                ],
            ]);

        // Saldo berkurang menjadi 4.000
        $this->nasabah->refresh();
        $this->assertEquals(4000.00, (float) $this->nasabah->saldo);

        // Mutasi saldo debit tercatat
        $this->assertDatabaseHas('mutasi_saldo', [
            'nasabah_id' => $this->nasabah->id,
            'tipe' => 'debit',
            'jumlah' => 6000.00,
            'saldo_akhir' => 4000.00,
            'referensi' => "penarikan:{$penarikan->id}",
        ]);
    }

    public function test_admin_can_reject_withdrawal_and_balance_is_untouched(): void
    {
        $penarikan = Penarikan::create([
            'nasabah_id' => $this->nasabah->id,
            'jumlah' => 5000.00,
            'status' => 'pending',
            'tanggal_ajuan' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/penarikan/{$penarikan->id}/tolak", [
                'catatan' => 'Uang kas fisik sedang tidak mencukupi hari ini',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ditolak',
                ],
            ]);

        // Saldo nasabah tetap 10.000
        $this->nasabah->refresh();
        $this->assertEquals(10000.00, (float) $this->nasabah->saldo);

        // Tidak ada mutasi debit
        $this->assertDatabaseMissing('mutasi_saldo', [
            'referensi' => "penarikan:{$penarikan->id}",
        ]);
    }

    public function test_petugas_can_process_direct_withdrawal_cash(): void
    {
        // Petugas mencatat penarikan langsung 7.000
        $response = $this->actingAs($this->petugas, 'sanctum')
            ->postJson('/api/v1/penarikan/langsung', [
                'nasabah_id' => $this->nasabah->id,
                'jumlah' => 7000,
                'catatan' => 'Tarik tunai langsung di loket',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'disetujui',
                    'nasabah' => [
                        'sisa_saldo' => 3000.0,
                    ],
                ],
            ]);

        $this->nasabah->refresh();
        $this->assertEquals(3000.00, (float) $this->nasabah->saldo);

        $this->assertDatabaseHas('mutasi_saldo', [
            'nasabah_id' => $this->nasabah->id,
            'tipe' => 'debit',
            'jumlah' => 7000.00,
            'saldo_akhir' => 3000.00,
        ]);
    }
}

