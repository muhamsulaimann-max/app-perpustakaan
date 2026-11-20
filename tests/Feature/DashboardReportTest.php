<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_dashboard_displays_stats_from_internal_api(): void
    {
        Http::fake([
            '*/api/stats' => Http::response([
                'total_buku' => 20,
                'total_anggota' => 15,
                'peminjaman_aktif' => 4,
            ]),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Total Buku')
            ->assertSee('20')
            ->assertSee('Total Anggota')
            ->assertSee('15')
            ->assertSee('Peminjaman Aktif')
            ->assertSee('4')
            ->assertDontSee('API internal tidak tersedia');

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'http://127.0.0.1:8011/api/stats');
    }

    public function test_report_displays_api_data_and_pagination_links(): void
    {
        Http::fake([
            '*/api/loans*' => Http::response([
                'data' => [[
                    'id' => 1,
                    'member' => ['nama' => 'Anggota Uji'],
                    'petugas' => ['name' => 'Siti Rahmawati'],
                    'books' => [['judul' => 'Laskar Pelangi']],
                    'tanggal_pinjam' => '2026-11-12',
                    'tanggal_kembali' => '2026-11-19',
                    'status' => 'dipinjam',
                ]],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 2,
                ],
            ]),
        ]);

        $this->get(route('loans.report', ['page' => 1]))
            ->assertOk()
            ->assertSee('Laporan Peminjaman')
            ->assertSee('Anggota Uji')
            ->assertSee('Siti Rahmawati')
            ->assertSee('Laskar Pelangi')
            ->assertSee('Halaman 1 dari 2')
            ->assertSee(route('loans.report', ['page' => 2]), false);

        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/api/loans?page=1'));
    }

    public function test_dashboard_and_report_stay_available_when_internal_api_cannot_connect(): void
    {
        Http::fake(fn () => throw new ConnectionException('Internal API is offline.'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('API internal tidak tersedia')
            ->assertSee('Peminjaman Aktif')
            ->assertSee('>0</div>', false);

        $this->get(route('loans.report'))
            ->assertOk()
            ->assertSee('API internal tidak tersedia')
            ->assertSee('Belum ada data peminjaman.');
    }

    public function test_database_seeder_creates_demo_data_in_dependency_order(): void
    {
        config(['app.faker_locale' => 'id_ID']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('categories', 5);
        $this->assertDatabaseCount('books', 20);
        $this->assertDatabaseCount('members', 15);
        $this->assertDatabaseCount('loans', 10);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@pens.ac.id',
            'name' => 'Bambang Sutrisno',
        ]);
        $this->assertSame(15, DB::table('members')->where('email', 'like', '%@pens.ac.id')->count());
    }
}
