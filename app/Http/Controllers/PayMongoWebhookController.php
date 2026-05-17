<?php

namespace App\Http\Controllers;

use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    protected PayMongoService $paymongo;

    public function __construct(PayMongoService $paymongo)
    {
        $this->paymongo = $paymongo;
    }

    public function handleWebhook(Request $request)
    {
        // ── Verify signature ────────────────────────────────────────────────────
        $signature = $request->header('Paymongo-Signature');

        if (!$signature || !$this->paymongo->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('PayMongo Webhook: Invalid or missing signature.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── Extract payload ─────────────────────────────────────────────────────
        $payload    = $request->all();
        $attributes = $payload['data']['attributes'] ?? [];
        $type       = $attributes['type'] ?? null;
        $innerData  = $attributes['data'] ?? [];

        Log::info("PayMongo Webhook received", [
            'type'      => $type,
            'innerData' => $innerData,
        ]);

        // ── Guard: empty innerData ───────────────────────────────────────────────
        if (empty($innerData)) {
            Log::error("PayMongo Webhook: innerData is empty!", ['payload' => $payload]);
            return response()->json(['status' => 'ok']);
        }

        // ── Route events ────────────────────────────────────────────────────────
        match ($type) {
            'source.chargeable' => $this->paymongo->handleSourceChargeable($innerData),

            'payment.paid' => (function () use ($innerData) {
                $result = $this->paymongo->handlePaymentPaid($innerData);
                if (!$result) {
                    Log::warning('PayMongo payment.paid: Order not found.');
                }
            })(),

            'payment.failed' => $this->paymongo->handlePaymentFailed($innerData),

            default => Log::info("PayMongo: Unhandled event type [{$type}]"),
        };

        // Always return 200 — PayMongo retries on non-2xx
        return response()->json(['status' => 'ok']);
    }
}