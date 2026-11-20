<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_buku' => 0,
            'total_anggota' => 0,
            'peminjaman_aktif' => 0,
        ];
        $apiAvailable = false;

        try {
            $response = Http::acceptJson()
                ->timeout(3)
                ->get(config('services.internal_api.base_url').'/api/stats');
            $payload = $response->json();

            if (
                $response->successful()
                && is_array($payload)
                && collect(array_keys($stats))->every(fn ($key) => isset($payload[$key]) && is_numeric($payload[$key]))
            ) {
                $stats = array_map('intval', array_intersect_key($payload, $stats));
                $apiAvailable = true;
            } else {
                Log::warning('Internal stats API returned an unsuccessful or invalid response.', [
                    'status' => $response->status(),
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Internal stats API could not be reached.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return view('dashboard', compact('stats', 'apiAvailable'));
    }
}
