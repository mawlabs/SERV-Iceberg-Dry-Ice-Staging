<?php

namespace App\Services;

use App\Models\SupplyLocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Novex delivery quote, same request and 25% markup the public checkout uses
 * (SupplierController::getNovexQuote), so invoices match credit card orders.
 */
class NovexQuoteService
{
    private const MARKUP = 0.25;

    /**
     * @param array $delivery name, street, unit, city, province, postal_code, contact, phone, email
     * @return array ['amount' => float] on success, ['error' => string] on failure
     */
    public function quote(SupplyLocation $pickup, array $delivery, float $weight): array
    {
        $weight     = max(1, $weight);
        $dimensions = max(12, ceil($weight / 2));

        $orderDetails = [
            'callerName' => 'Tyler',
            'reference'  => '',
            'pickup'     => [
                'name'         => $pickup->name,
                'street'       => $pickup->address,
                'unit'         => $pickup->unit ?? '',
                'city'         => $pickup->city,
                'province'     => $pickup->province,
                'postalCode'   => $pickup->postal,
                'country'      => 'CAN',
                'instructions' => 'Call  604-255-6007 if given phone is not responding',
            ],
            'delivery'   => [
                'name'              => $delivery['name'],
                'street'            => $delivery['street'],
                'unit'              => $delivery['unit'] ?? '',
                'city'              => $delivery['city'],
                'province'          => $delivery['province'],
                'postalCode'        => $delivery['postal_code'],
                'country'           => 'CAN',
                'instructions'      => 'Call  604-255-6007 if given phone is not responding',
                'contact'           => $delivery['contact'],
                'phone'             => $delivery['phone'],
                'notificationEmail' => $delivery['email'],
            ],
            'serviceTypeId' => 4,
            'vehicleTypeId' => 1,
            'readyBy'       => '2050-10-10T15:00-07:00',
            'instructions'  => '',
            'packages'      => [
                [
                    'typeId' => 3,
                    'length' => $dimensions,
                    'width'  => 1,
                    'height' => 1,
                    'weight' => $weight,
                    'count'  => 1,
                ],
            ],
        ];

        try {
            $response = Http::withOptions([
                'verify' => config('services.http_verify'),
            ])->withHeaders([
                'Authorization' => 'Basic ' . config('services.novex.auth_key'),
                'Content-Type'  => 'application/json',
            ])->post(config('services.novex.api_url'), $orderDetails);

            if (!$response->successful()) {
                Log::error('Novex quote error', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);

                return ['error' => 'Novex returned status ' . $response->status()];
            }

            $total = (float) ($response->json('totalAmount') ?? 0);
            if ($total <= 0) {
                return ['error' => 'Novex returned no price'];
            }

            return ['amount' => round($total * (1 + self::MARKUP), 2)];
        } catch (\Throwable $e) {
            Log::error('Novex quote exception: ' . $e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }
}
