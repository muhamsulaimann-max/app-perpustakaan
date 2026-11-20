<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_books_api_supports_filters_pagination_and_crud(): void
    {
        $category = Category::create(['nama_kategori' => 'Novel']);
        $book = $this->createBook($category, 'Laskar Pelangi', 'ISBN-1');
        $this->createBook($category, 'Bumi', 'ISBN-2');

        $this->getJson('/api/books?judul=laskar&category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('data.0.judul', 'Laskar Pelangi')
            ->assertJsonPath('data.0.category.nama_kategori', 'Novel')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('data.category.id', $category->id);

        $bookData = [
            'judul' => 'Buku Baru',
            'penulis' => 'Penulis',
            'penerbit' => 'Penerbit',
            'tahun_terbit' => 2020,
            'isbn' => 'ISBN-3',
            'stok' => 3,
            'category_id' => $category->id,
        ];

        $this->postJson('/api/books', $bookData)
            ->assertCreated()
            ->assertJsonPath('data.judul', 'Buku Baru')
            ->assertJsonPath('data.category.nama_kategori', 'Novel');

        $this->postJson('/api/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['judul', 'penulis', 'category_id']);

        $this->putJson('/api/books/'.$book->id, array_merge($bookData, [
            'judul' => 'Laskar Pelangi Revisi',
            'isbn' => 'ISBN-1',
        ]))
            ->assertOk()
            ->assertJsonPath('data.judul', 'Laskar Pelangi Revisi');

        $this->deleteJson('/api/books/'.$book->id)
            ->assertOk()
            ->assertJsonPath('message', 'Buku berhasil dihapus.');

        $this->getJson('/api/books/'.$book->id)->assertNotFound();
    }

    public function test_members_api_returns_paginated_list_and_member_detail(): void
    {
        $member = $this->createMember('Anggota Pertama', 'NIM-1');

        for ($index = 2; $index <= 11; $index++) {
            $this->createMember("Anggota {$index}", "NIM-{$index}");
        }

        $this->getJson('/api/members')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(10, 'data');

        $this->getJson('/api/members/'.$member->id)
            ->assertOk()
            ->assertJsonPath('data.nama', 'Anggota Pertama');
    }

    public function test_loans_api_creates_lists_and_marks_a_loan_as_returned(): void
    {
        $category = Category::create(['nama_kategori' => 'Sains']);
        $book = $this->createBook($category, 'Fisika Dasar', 'ISBN-FISIKA');
        $member = $this->createMember('Anggota Uji', 'NIM-UJI');
        $user = User::factory()->create(['name' => 'Petugas Uji']);

        $response = $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-12',
            'tanggal_kembali' => '2026-11-19',
            'book_ids' => [$book->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'dipinjam')
            ->assertJsonPath('data.member.nama', 'Anggota Uji')
            ->assertJsonPath('data.petugas.name', 'Petugas Uji')
            ->assertJsonPath('data.books.0.judul', 'Fisika Dasar');

        $loanId = $response->json('data.id');

        $this->getJson('/api/loans')
            ->assertOk()
            ->assertJsonPath('data.0.id', $loanId)
            ->assertJsonPath('data.0.books.0.id', $book->id);

        $this->travelTo(now()->setDate(2026, 11, 20));
        $this->putJson("/api/loans/{$loanId}/kembalikan")
            ->assertOk()
            ->assertJsonPath('data.status', 'dikembalikan')
            ->assertJsonPath('data.tanggal_dikembalikan', '2026-11-20');
        $this->travelBack();
    }

    public function test_loan_api_validation_and_stats_are_correct(): void
    {
        $category = Category::create(['nama_kategori' => 'Sejarah']);
        $book = $this->createBook($category, 'Sejarah Indonesia', 'ISBN-SEJARAH');
        $member = $this->createMember('Anggota Aktif', 'NIM-AKTIF');
        $user = User::factory()->create();

        $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-12',
            'tanggal_kembali' => '2026-11-11',
            'book_ids' => [$book->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tanggal_kembali']);

        $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-12',
            'tanggal_kembali' => '2026-11-19',
            'book_ids' => [$book->id],
        ])->assertCreated();

        Loan::create([
            'member_id' => $member->id,
            'user_id' => $user->id,
            'tanggal_pinjam' => '2026-11-01',
            'tanggal_kembali' => '2026-11-08',
            'status' => 'dikembalikan',
        ]);

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertExactJson([
                'total_buku' => 1,
                'total_anggota' => 1,
                'peminjaman_aktif' => 1,
            ]);
    }

    private function createBook(Category $category, string $title, string $isbn): Book
    {
        return Book::create([
            'judul' => $title,
            'penulis' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji',
            'tahun_terbit' => 2020,
            'isbn' => $isbn,
            'stok' => 5,
            'category_id' => $category->id,
        ]);
    }

    private function createMember(string $name, string $nim): Member
    {
        return Member::create([
            'nama' => $name,
            'nim' => $nim,
            'email' => strtolower(str_replace(' ', '.', $nim)).'@example.test',
            'nomor_telepon' => '08123456789',
            'alamat' => 'Alamat Uji',
            'status' => 'aktif',
        ]);
    }
}
