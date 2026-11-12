<?php

namespace Tests\Feature;

use App\Models\Nasabah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_no_hp(): void
    {
        $user = User::create([
            'nama' => 'Budi Santoso',
            'no_hp' => '081234567890',
            'email' => 'budi@test.com',
            'password' => Hash::make('password123'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        $nasabah = Nasabah::create([
            'user_id' => $user->id,
            'no_rekening' => 'BS-0001',
            'saldo' => 10000,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '081234567890',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'nama', 'no_hp', 'role', 'nasabah' => ['no_rekening', 'saldo']],
                ],
            ]);
    }

    public function test_user_can_login_with_no_rekening(): void
    {
        $user = User::create([
            'nama' => 'Siti Aminah',
            'no_hp' => '081234567891',
            'password' => Hash::make('password123'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        Nasabah::create([
            'user_id' => $user->id,
            'no_rekening' => 'BS-0002',
            'saldo' => 5000,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'BS-0002',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'nama' => 'Siti Aminah',
                        'nasabah' => [
                            'no_rekening' => 'BS-0002',
                        ],
                    ],
                ],
            ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::create([
            'nama' => 'Test',
            'no_hp' => '081234567892',
            'password' => Hash::make('password123'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '081234567892',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_nasabah_can_register_mandiri(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'Warga Baru',
            'no_hp' => '089988776655',
            'email' => 'warga@desa.id',
            'password' => 'password123',
            'alamat' => 'RT 01 RW 02',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registrasi berhasil',
            ]);

        $this->assertDatabaseHas('users', [
            'no_hp' => '089988776655',
            'role' => 'nasabah',
        ]);

        $this->assertDatabaseHas('nasabah', [
            'no_rekening' => 'BS-0001',
            'saldo' => 0.00,
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'nama' => 'User Logout',
            'no_hp' => '08111222333',
            'password' => Hash::make('secret'),
            'role' => 'nasabah',
            'status' => 'aktif',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout berhasil',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

