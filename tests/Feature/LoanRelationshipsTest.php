<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationships_and_eager_loaded_category_views_work(): void
    {
        $category = Category::create(['nama_kategori' => 'Novel']);
        $book = $this->createBook($category, 'Bumi');
        $member = $this->createMember();
        $user = User::factory()->create(['name' => 'Petugas']);
        $loan = $this->createLoan($member, $user, [$book]);

        $this->assertTrue($book->category->is($category));
        $this->assertTrue($category->books->contains($book));
        $this->assertTrue($member->loans->contains($loan));
        $this->assertTrue($user->loans->contains($loan));
        $this->assertTrue($loan->member->is($member));
        $this->assertTrue($loan->user->is($user));
        $this->assertTrue($loan->loanItems->first()->book->is($book));
        $this->assertTrue($loan->loanItems->first()->loan->is($loan));

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Novel')
            ->assertDontSee('ID Kategori');

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Novel')
            ->assertDontSee('ID Kategori');

        $this->get(route('members.show', $member))
            ->assertOk()
            ->assertSee('Bumi')
            ->assertSee('Petugas')
            ->assertSee('Riwayat Peminjaman');
    }

    public function test_loans_can_be_created_viewed_updated_and_deleted_with_items(): void
    {
        $category = Category::create(['nama_kategori' => 'Sains']);
        $firstBook = $this->createBook($category, 'Fisika Dasar');
        $secondBook = $this->createBook($category, 'Kimia Dasar');
        $member = $this->createMember();
        $user = User::factory()->create(['name' => 'Petugas Uji']);

        $this->get(route('loans.create'))
            ->assertOk()
            ->assertSee('Anggota Uji')
            ->assertSee('Petugas Uji')
            ->assertSee('Fisika Dasar')
            ->assertSee('Kimia Dasar');

        $this->post(route('loans.store'), [
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-12',
            'tanggal_kembali' => '2026-11-19',
            'book_ids' => [$firstBook->id, $secondBook->id],
        ])->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');

        $loan = Loan::with('loanItems')->firstOrFail();
        $this->assertDatabaseCount('loan_items', 2);

        $this->get(route('loans.index'))
            ->assertOk()
            ->assertSee('Anggota Uji')
            ->assertSee('Petugas Uji')
            ->assertSee('Fisika Dasar')
            ->assertSee('Kimia Dasar');

        $this->get(route('loans.show', $loan))
            ->assertOk()
            ->assertSee('Anggota Uji')
            ->assertSee('Fisika Dasar')
            ->assertSee('Kimia Dasar');

        $this->get(route('loans.edit', $loan))
            ->assertOk()
            ->assertSee('Anggota Uji')
            ->assertSee('Fisika Dasar')
            ->assertSee('tanggal_kembali');

        $this->put(route('loans.update', $loan), [
            'tanggal_kembali' => '2026-11-20',
            'status' => 'dikembalikan',
        ])->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'tanggal_kembali' => '2026-11-20',
            'status' => 'dikembalikan',
        ]);

        $this->delete(route('loans.destroy', $loan))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
        $this->assertDatabaseCount('loan_items', 0);
    }

    public function test_loan_validation_requires_existing_people_and_books_and_valid_dates(): void
    {
        $member = $this->createMember();
        $user = User::factory()->create();
        $category = Category::create(['nama_kategori' => 'Sejarah']);
        $book = $this->createBook($category, 'Sejarah Indonesia');

        $this->from(route('loans.create'))
            ->post(route('loans.store'), [
                'member_id' => $member->id,
                'user_id' => $user->id,
                'tanggal_pinjam' => '2026-11-12',
                'tanggal_kembali' => '2026-11-11',
                'book_ids' => [],
            ])
            ->assertRedirect(route('loans.create'))
            ->assertSessionHasErrors(['tanggal_kembali', 'book_ids']);

        $this->from(route('loans.create'))
            ->post(route('loans.store'), [
                'member_id' => 999,
                'user_id' => 999,
                'tanggal_pinjam' => '2026-11-12',
                'tanggal_kembali' => '2026-11-19',
                'book_ids' => [$book->id, 999],
            ])
            ->assertRedirect(route('loans.create'))
            ->assertSessionHasErrors(['member_id', 'user_id', 'book_ids.1']);

        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('loan_items', 0);
    }

    private function createBook(Category $category, string $title): Book
    {
        return Book::create([
            'judul' => $title,
            'penulis' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji',
            'tahun_terbit' => 2020,
            'stok' => 5,
            'category_id' => $category->id,
        ]);
    }

    private function createMember(): Member
    {
        return Member::create([
            'nama' => 'Anggota Uji',
            'nim' => 'NIM-UJI',
            'email' => 'anggota@example.test',
            'nomor_telepon' => '08123456789',
            'alamat' => 'Alamat Uji',
            'status' => 'aktif',
        ]);
    }

    /**
     * @param  array<int, Book>  $books
     */
    private function createLoan(Member $member, User $user, array $books): Loan
    {
        $loan = Loan::create([
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-12',
            'tanggal_kembali' => '2026-11-19',
        ]);

        foreach ($books as $book) {
            $loan->loanItems()->create(['book_id' => $book->id]);
        }

        return $loan;
    }
}
