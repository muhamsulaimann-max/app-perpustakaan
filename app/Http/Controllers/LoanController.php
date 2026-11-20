<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::with(['member', 'user', 'loanItems.book'])->paginate(10);

        return view('loans.index', compact('loans'));
    }

    public function report(Request $request)
    {
        $loans = [];
        $meta = null;
        $apiAvailable = false;

        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->get(config('services.internal_api.base_url').'/api/loans', [
                    'page' => $request->query('page', 1),
                ]);

            $body = $response->json();
            if ($response->successful() && is_array($body) && isset($body['data']) && is_array($body['data'])) {
                $loans = $body['data'];
                $meta = isset($body['meta']) && is_array($body['meta']) ? $body['meta'] : null;
                $apiAvailable = true;
            } else {
                Log::warning('Internal loans API returned an unsuccessful or invalid response.', [
                    'status' => $response->status(),
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Internal loans API could not be reached.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return view('loans.report', compact('loans', 'meta', 'apiAvailable'));
    }

    public function create()
    {
        $members = Member::all();
        $books = Book::all();
        return view('loans.create', compact('members', 'books'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|integer|exists:members,id',
            'tanggal_pinjam' => 'required|date',
            'tanggal_kembali' => 'required|date|after_or_equal:tanggal_pinjam',
            'book_ids' => 'required|array|min:1',
            'book_ids.*' => 'required|integer|exists:books,id',
        ]);

        DB::transaction(function () use ($validated) {
            $loan = Loan::create([
                'member_id' => $validated['member_id'],
                'user_id' => auth()->id(),
                'tanggal_pinjam' => $validated['tanggal_pinjam'],
                'tanggal_kembali' => $validated['tanggal_kembali'],
            ]);

            foreach ($validated['book_ids'] as $bookId) {
                $loan->loanItems()->create(['book_id' => $bookId]);
            }
        });

        return redirect()->route('loans.index')
            ->with('success', 'Transaksi peminjaman berhasil dibuat.');
    }

    public function show(string $id)
    {
        $loan = Loan::with(['member', 'user', 'loanItems.book'])->findOrFail($id);

        return view('loans.show', compact('loan'));
    }

    public function edit(string $id)
    {
        $loan = Loan::with(['member', 'user', 'loanItems.book'])->findOrFail($id);

        return view('loans.edit', compact('loan'));
    }

    public function update(Request $request, string $id)
    {
        $loan = Loan::findOrFail($id);

        $validated = $request->validate([
            'tanggal_kembali' => ['required', 'date', 'after_or_equal:'.$loan->tanggal_pinjam],
            'status' => 'required|in:dipinjam,dikembalikan,terlambat',
        ], [
            'tanggal_kembali.required' => 'Tanggal kembali wajib diisi.',
            'tanggal_kembali.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
        ]);

        $loan->update($validated);

        return redirect()->route('loans.index')
            ->with('success', 'Transaksi peminjaman berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        DB::transaction(function () use ($id) {
            $loan = Loan::findOrFail($id);
            $loan->loanItems()->delete();
            $loan->delete();
        });

        return redirect()->route('loans.index')
            ->with('success', 'Transaksi peminjaman berhasil dihapus.');
    }
}
