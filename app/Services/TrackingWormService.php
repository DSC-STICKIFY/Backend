<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TrackingWormService
{
    public function track(string $trackingNumber): array
    {
        // ─── LOCAL TESTING — mock data, no real API call ──────────
        if (app()->environment('local')) {
            return $this->mockTrackingData($trackingNumber);
        }

        // ─── PRODUCTION — real TrackingWorm API ───────────────────
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.trackingworm.api_key'),
            'Content-Type' => 'application/json',
        ])->get(config('services.trackingworm.base_url') . '/track', [
                    'tracking_number' => $trackingNumber,
                    'courier_code' => 'jnt-ph',
                ]);

        return $response->successful()
            ? $response->json()
            : ['error' => 'Unable to fetch tracking info.'];
    }

    private function mockTrackingData(string $trackingNumber): array
    {
        return [
            'data' => [
                'tracking_number' => $trackingNumber,
                'status' => 'out_for_delivery',
                // Change status to test different scenarios:
                // 'in_transit'       → Track button visible, no Order Received
                // 'out_for_delivery' → Track button visible, no Order Received  
                // 'delivered'        → Order Received button appears ✅
                'events' => [
                    [
                        'status' => 'out_for_delivery',
                        'message' => 'Package is out for delivery',
                        'datetime' => now()->format('Y-m-d H:i:s'),
                        'location' => 'Davao City Hub',
                    ],
                    [
                        'status' => 'in_transit',
                        'message' => 'Package arrived at Davao City Hub',
                        'datetime' => now()->subHours(3)->format('Y-m-d H:i:s'),
                        'location' => 'Davao City Hub',
                    ],
                    [
                        'status' => 'in_transit',
                        'message' => 'Package in transit from Cebu Hub',
                        'datetime' => now()->subHours(8)->format('Y-m-d H:i:s'),
                        'location' => 'Cebu Hub',
                    ],
                    [
                        'status' => 'picked_up',
                        'message' => 'Package picked up by J&T Express',
                        'datetime' => now()->subDay()->format('Y-m-d H:i:s'),
                        'location' => 'Seller Location',
                    ],
                ],
            ],
        ];
    }
}