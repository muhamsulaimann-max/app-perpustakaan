<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_categories_can_be_created_updated_and_deleted(): void
    {
        $this->post(route('categories.store'), [
            'nama_kategori' => 'Fiksi',
            'deskripsi' => 'Buku cerita.',
        ])->assertRedirect(route('categories.index'))
            ->assertSessionHas('success');

        $category = Category::where('nama_kategori', 'Fiksi')->firstOrFail();
        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Fiksi');

        $this->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('Fiksi');

        $this->put(route('categories.update', $category), [
            'nama_kategori' => 'Novel',
            'deskripsi' => 'Cerita rekaan.',
        ])->assertRedirect(route('categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'nama_kategori' => 'Novel',
        ]);

        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_books_use_database_categories_and_support_crud(): void
    {
        $category = Category::create(['nama_kategori' => 'Teknologi']);

        $this->get(route('books.create'))
            ->assertOk()
            ->assertSee('Teknologi');

        $bookData = [
            'judul' => 'Clean Code',
            'penulis' => 'Robert C. Martin',
            'penerbit' => 'Prentice Hall',
            'tahun_terbit' => 2008,
            'isbn' => '9780132350884',
            'stok' => 7,
            'category_id' => $category->id,
        ];

        $this->post(route('books.store'), $bookData)
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success');

        $book = Book::where('isbn', $bookData['isbn'])->firstOrFail();

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Clean Code')
            ->assertSee('Teknologi')
            ->assertDontSee('ID Kategori');

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Teknologi')
            ->assertDontSee('ID Kategori');

        $this->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('Clean Code')
            ->assertSee('value="'.$category->id.'" selected', false);

        $bookData['judul'] = 'Clean Architecture';
        $this->put(route('books.update', $book), $bookData)
            ->assertRedirect(route('books.index'));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'judul' => 'Clean Architecture',
        ]);

        $this->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('Clean Architecture');
    }

    public function test_empty_book_form_shows_validation_errors(): void
    {
        $this->from(route('books.create'))
            ->post(route('books.store'), [])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors([
                'judul',
                'penulis',
                'penerbit',
                'tahun_terbit',
                'stok',
                'category_id',
            ]);

        $this->get(route('books.create'))
            ->assertOk()
            ->assertSee('Judul buku wajib diisi.')
            ->assertSee('Nama penulis wajib diisi.')
            ->assertSee('Nama penerbit wajib diisi.')
            ->assertSee('Tahun terbit wajib diisi.')
            ->assertSee('Stok wajib diisi.')
            ->assertSee('Kategori wajib dipilih.');
    }

    public function test_book_category_must_exist(): void
    {
        $this->from(route('books.create'))
            ->post(route('books.store'), [
                'judul' => 'Buku',
                'penulis' => 'Penulis',
                'penerbit' => 'Penerbit',
                'tahun_terbit' => 2020,
                'stok' => 1,
                'category_id' => 999,
            ])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_members_can_be_managed_and_searched_with_pagination(): void
    {
        $memberData = [
            'nama' => 'Siti Aminah',
            'nim' => '2310501001',
            'email' => 'siti@example.test',
            'nomor_telepon' => '081234567890',
            'alamat' => 'Surabaya',
            'status' => 'aktif',
        ];

        $this->get(route('members.create'))->assertOk();
        $this->post(route('members.store'), $memberData)
            ->assertRedirect(route('members.index'))
            ->assertSessionHas('success');

        $member = Member::where('nim', $memberData['nim'])->firstOrFail();
        $this->get(route('members.show', $member))->assertOk()->assertSee('Siti Aminah');
        $this->get(route('members.edit', $member))->assertOk()->assertSee('Siti Aminah');

        $memberData['nama'] = 'Siti A.';
        $this->put(route('members.update', $member), $memberData)
            ->assertRedirect(route('members.index'));
        $this->assertDatabaseHas('members', ['id' => $member->id, 'nama' => 'Siti A.']);

        for ($index = 1; $index <= 11; $index++) {
            Member::create([
                'nama' => "Match Member {$index}",
                'nim' => "MATCH{$index}",
                'email' => "match{$index}@example.test",
                'nomor_telepon' => '081234567890',
                'alamat' => 'Surabaya',
                'status' => 'aktif',
            ]);
        }

        $this->withSession(['success' => null])
            ->get(route('members.index', ['search' => 'Match']))
            ->assertOk()
            ->assertSee('Match Member 1')
            ->assertDontSee('Siti A.')
            ->assertSee('search=Match&amp;page=2', false);

        $this->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'));
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }

    public function test_member_validation_rejects_duplicate_identity_fields(): void
    {
        Member::create([
            'nama' => 'Anggota Lama',
            'nim' => 'NIM-1',
            'email' => 'lama@example.test',
            'nomor_telepon' => '081234567890',
            'alamat' => 'Surabaya',
            'status' => 'aktif',
        ]);

        $this->from(route('members.create'))
            ->post(route('members.store'), [
                'nama' => 'Anggota Baru',
                'nim' => 'NIM-1',
                'email' => 'lama@example.test',
                'nomor_telepon' => '081234567891',
                'alamat' => 'Surabaya',
                'status' => 'aktif',
            ])
            ->assertRedirect(route('members.create'))
            ->assertSessionHasErrors(['nim', 'email']);
    }
}
