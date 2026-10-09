<?php

namespace App\Services;

use App\Exceptions\SupplierLookupException;
use App\Models\SupplyLocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClosestSupplierService
{
    protected $googleApiKey;

    public function __construct()
    {
        // Load from config (which uses env internally)
        $this->googleApiKey = config('services.google.api_key');
    }


    public function findClosest($street, $city, $province, $useGoogle = true)
    {
        $destination = $this->formatAddress($street, $city, $province);

        $suppliers = SupplyLocation::where('province', $province)
            ->where('active', 1)
            ->get();

        if ($suppliers->isEmpty()) return null;

        if (!$useGoogle) {
            $suppliers[0]->distance = 12345;
            return $suppliers[0];
        }

        $origins = $suppliers->map(function ($supplier) {
            return $this->formatAddress($supplier->address, $supplier->city, $supplier->province);
        })->implode('|');

        // A failed lookup must not look like "no supplier covers this address":
        // it throws, so the caller can tell the customer to try again.
        try {
            $response = Http::withOptions([
                'verify' => config('services.http_verify'),
            ])->timeout(10)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'key' => $this->googleApiKey,
                'units' => 'imperial',
                'origins' => $origins,
                'destinations' => $destination,
            ]);
        } catch (\Throwable $e) {
            Log::error('Distance Matrix request failed: ' . $e->getMessage());
            throw new SupplierLookupException('Distance lookup is unavailable: ' . $e->getMessage(), 0, $e);
        }

        $data = $response->json();

        // status is OK even when an address can't be found (that shows per element below);
        // anything else (REQUEST_DENIED, OVER_QUERY_LIMIT, ...) means the lookup itself failed.
        if (!$response->successful() || !is_array($data) || ($data['status'] ?? 'OK') !== 'OK' || !isset($data['rows'])) {
            Log::error('Distance Matrix lookup failed', [
                'http_status' => $response->status(),
                'status' => $data['status'] ?? null,
                'error_message' => $data['error_message'] ?? null,
            ]);
            throw new SupplierLookupException('Distance lookup failed: ' . ($data['status'] ?? 'HTTP ' . $response->status()));
        }

        $shortest = PHP_INT_MAX;
        $closest = null;

        foreach ($data['rows'] as $index => $row) {
            $distance = $row['elements'][0]['distance']['value'] ?? null;

            if ($distance !== null && $distance < $shortest) {
                $shortest = $distance;
                $closest = $suppliers[$index];
                $closest->distance = $distance;
            }
        }

        return $closest;
    }

    protected function formatAddress($street, $city, $province)
    {
        return urlencode(trim("{$street}, {$city}, {$province}"));
    }

}
