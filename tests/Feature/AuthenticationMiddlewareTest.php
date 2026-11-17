<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_protected_routes(): void
    {
        $this->get(route('books.index'))
            ->assertRedirect(route('login'));

        $this->get(route('categories.index'))
            ->assertRedirect(route('login'));

        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'petugas@example.test',
            'password' => 'password',
            'role' => 'petugas',
        ]);

        $this->get(route('login'))->assertOk();

        $this->post(route('login'), [
            'email' => 'petugas@example.test',
            'password' => 'password',
            'remember' => 'on',
        ])->assertRedirect(route('books.index'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Logout berhasil.');

        $this->assertGuest();
    }

    public function test_invalid_credentials_show_a_generic_error(): void
    {
        User::factory()->create([
            'email' => 'petugas@example.test',
            'password' => 'correct-password',
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'petugas@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Email atau password salah.',
            ]);

        $this->assertGuest();
    }

    public function test_user_seeder_creates_idempotent_admin_and_petugas_accounts(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@pens.ac.id',
            'name' => 'Admin Perpustakaan',
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'petugas1@pens.ac.id',
            'name' => 'Petugas Satu',
            'role' => 'petugas',
        ]);

        $admin = User::where('email', 'admin@pens.ac.id')->firstOrFail();
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_only_admins_can_access_categories(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Uji',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Kategori');

        $petugas = User::factory()->create([
            'name' => 'Petugas Uji',
            'role' => 'petugas',
        ]);

        $this->actingAs($petugas)
            ->get(route('categories.index'))
            ->assertForbidden()
            ->assertSee('Halaman ini hanya bisa diakses oleh admin.');

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Petugas Uji (Petugas)')
            ->assertDontSee(route('categories.index'));
    }
}
