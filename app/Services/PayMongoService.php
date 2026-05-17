<?php

namespace App\Services;

use App\Models\OrdersModel;
use App\Models\OrdersPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    protected string $secretKey;
    protected ?string $webhookSecret = null;
    protected string $baseUrl = 'https://api.paymongo.com/v1';

    public function __construct()
    {
        $this->secretKey     = config('services.paymongo.secret_key');
        $this->webhookSecret = config('services.paymongo.webhook_secret');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    protected function headers(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
            'Content-Type'  => 'application/json',
        ];
    }

    protected function toCents(float $amount): int
    {
        return intval(round($amount * 100));
    }

    // ─── Source ──────────────────────────────────────────────────────────────────

    public function createGcashSource(float $amount, array $metadata = []): array
    {
        $payload = [
            'data' => [
                'attributes' => [
                    'amount'   => $this->toCents($amount),
                    'currency' => 'PHP',
                    'type'     => 'gcash',
                    'redirect' => [
                        'success' => config('payment.success_url', 'http://localhost:5173/payment-success'),
                        'failed'  => config('payment.failed_url',  'http://localhost:5173/payment-failed'),
                    ],
                ],
            ],
        ];

        if (!empty($metadata)) {
            // Flatten metadata to prevent nesting issues with PayMongo API
            $flatMetadata = [];
            foreach ($metadata as $key => $value) {
                $flatMetadata[$key] = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
            }
            $payload['data']['attributes']['metadata'] = (object) $flatMetadata;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/sources", $payload);

        if (!$response->successful()) {
            Log::error('PayMongo createGcashSource failed', ['body' => $response->body()]);
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    // ─── Payment ─────────────────────────────────────────────────────────────────

    public function createPayment(float $amount, string $sourceId): array
    {
        $payload = [
            'data' => [
                'attributes' => [
                    'amount'   => $this->toCents($amount),
                    'currency' => 'PHP',
                    'source'   => [
                        'id'   => $sourceId,
                        'type' => 'source',
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payments", $payload);

        if (!$response->successful()) {
            Log::error('PayMongo createPayment failed', ['body' => $response->body()]);
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    // ─── Payment Intent ───────────────────────────────────────────────────────────

    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/payment_intents/{$paymentIntentId}/cancel");

        if (!$response->successful()) {
            Log::error('PayMongo cancelPaymentIntent failed', ['body' => $response->body()]);
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    // ─── Webhook Signature ────────────────────────────────────────────────────────

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('PayMongo: Webhook secret not set — skipping verification.');
            return true;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $parts[trim($pair[0])] = trim($pair[1]);
            }
        }

        if (empty($parts['t']) || empty($parts['te'])) {
            Log::warning('PayMongo: Missing t or te in signature header.', ['header' => $signatureHeader]);
            return false;
        }

        $expected = hash_hmac('sha256', $parts['t'] . '.' . $rawBody, $this->webhookSecret);

        return hash_equals($expected, $parts['te']);
    }

    // ─── Webhook Event Handlers ───────────────────────────────────────────────────

    public function handleSourceChargeable(array $data): void
    {
        Log::info("handleSourceChargeable called", ['data' => $data]);

        if (empty($data['id'])) {
            Log::error("handleSourceChargeable: No source ID in data", ['data' => $data]);
            return;
        }

        $sourceId = $data['id'];
        $metadata = $data['attributes']['metadata'] ?? [];
        $type = $metadata['type'] ?? 'order';

        Log::info("Source is chargeable", ['source_id' => $sourceId, 'type' => $type]);

        $amount = 0;
        if ($type === 'inquiry') {
            $inquiry = \App\Models\Inquiry::find($metadata['inquiry_id'] ?? null);
            if ($inquiry) {
                $paymentType = $metadata['payment_type'] ?? 'full';
                if ($paymentType === 'downpayment') {
                    $amount = $inquiry->downpayment_amount;
                } else {
                    $amount = $inquiry->quotation_amount - $inquiry->amount_paid;
                }
            }
        } else {
            $order = OrdersModel::where('paymongo_source_id', $sourceId)->first();
            if ($order) {
                $amount = $order->total_price;
            }
        }

        if ($amount <= 0) {
            Log::warning("Amount is 0 or record not found for Source ID: {$sourceId}");
            return;
        }

        try {
            $paymentResponse = $this->createPayment($amount, $sourceId);
            Log::info("Payment created successfully", ['response' => $paymentResponse]);
        } catch (\Exception $e) {
            Log::error("Failed to create payment", ['error' => $e->getMessage()]);
        }
    }

    public function handlePaymentPaid(array $data): ?array
    {
        Log::info("handlePaymentPaid called", ['data' => $data]);

        $paymentId  = $data['id'] ?? null;
        $attributes = $data['attributes'] ?? [];
        $sourceId   = $attributes['source']['id'] ?? null;
        $metadata   = $attributes['metadata'] ?? [];
        $type       = $metadata['type'] ?? 'order';

        Log::info("handlePaymentPaid extracted", [
            'payment_id' => $paymentId,
            'source_id'  => $sourceId,
            'type'       => $type
        ]);

        if ($type === 'inquiry') {
            $inquiryId = $metadata['inquiry_id'] ?? null;
            $paymentType = $metadata['payment_type'] ?? 'full';
            $inquiry = \App\Models\Inquiry::find($inquiryId);
            if ($inquiry) {
                $paidAmount = ($attributes['amount'] ?? 0) / 100;
                $newAmountPaid = $inquiry->amount_paid + $paidAmount;
                
                $updateData = [
                    'amount_paid' => $newAmountPaid,
                    'payment_reference' => $paymentId,
                    'paid_at' => now(),
                    'payment_method' => 'gcash'
                ];

                if ($newAmountPaid >= $inquiry->quotation_amount) {
                    $updateData['payment_status'] = 'paid';
                    $updateData['status'] = 'scheduled'; // Or completed? Usually scheduled if they pay full early
                } else {
                    $updateData['payment_status'] = 'partial';
                    $updateData['status'] = 'scheduled';
                }

                $inquiry->update($updateData);
                Log::info("Inquiry #{$inquiryId} updated after payment. Paid: {$paidAmount}, Total Paid: {$newAmountPaid}");
                return ['inquiry_id' => $inquiryId];
            }
        } else {
            $order = OrdersModel::where('paymongo_source_id', $sourceId)->first();
            if ($order) {
                Log::info("PayMongo Webhook: Marking Order #{$order->order_id} as PAID.");
                
                OrdersPayment::create([
                    'order_id'         => $order->order_id,
                    'payment_amount'   => $order->total_price,
                    'amount_paid'      => ($attributes['amount'] ?? 0) / 100,
                    'payment_date'     => now(),
                    'reference_number' => $paymentId,
                ]);

                // Update order status - Removed 'payment_status' as it doesn't exist in orders_table
                $order->update([
                    'status' => 'Pending',
                ]);

                Log::info("PayMongo Webhook: Order #{$order->order_id} status updated to 'Pending'.");
                return ['order_id' => $order->order_id];
            }
        }

        Log::error("handlePaymentPaid: Record not found", ['source_id' => $sourceId, 'type' => $type]);
        return null;
    }

    public function handlePaymentFailed(array $data): void
    {
        Log::info("handlePaymentFailed called", ['data' => $data]);

        $attributes = $data['attributes'] ?? [];
        $sourceId   = $attributes['source']['id'] ?? null;
        $metadata   = $attributes['metadata'] ?? [];
        $type       = $metadata['type'] ?? 'order';

        Log::info("Payment failed", ['source_id' => $sourceId, 'type' => $type]);

        if ($type === 'inquiry') {
            $inquiryId = $metadata['inquiry_id'] ?? null;
            $inquiry = \App\Models\Inquiry::find($inquiryId);
            if ($inquiry) {
                $inquiry->update(['payment_status' => 'failed']);
                Log::info("Inquiry #{$inquiryId} payment failed");
            }
        } else {
            $order = OrdersModel::where('paymongo_source_id', $sourceId)->first();
            if ($order) {
                $order->update([
                    'status'         => 'Payment Failed',
                    'payment_status' => 'failed',
                ]);
                Log::info("Order #{$order->order_id} marked as Payment Failed");
            }
        }
    }

    // ─── Refund ──────────────────────────────────────────────────────────────────

    public function refundPayment(string $paymentId, float $amount): array
    {
        $payload = [
            'data' => [
                'attributes' => [
                    'amount'     => intval($amount * 100),
                    'reason'     => 'requested_by_customer',
                    'payment_id' => $paymentId,
                ],
            ],
        ];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/refunds", $payload);

        if (!$response->successful()) {
            Log::error('PayMongo refund failed', ['body' => $response->body()]);
            throw new \Exception($response->body());
        }

        return $response->json();
    }
}