<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InquiryPaymentController extends Controller
{
    protected PayMongoService $paymongo;

    public function __construct(PayMongoService $paymongo)
    {
        $this->paymongo = $paymongo;
    }

    /**
     * Create GCash payment source for inquiry
     */
    public function payViaGcash(Request $request, $id)
    {
        $userId = Auth::id();
        $inquiry = Inquiry::where('user_id', $userId)->findOrFail($id);

        $amountToPay = 0;
        $paymentType = 'full';

        if ($inquiry->status === 'approved' && $inquiry->downpayment_amount > 0 && $inquiry->amount_paid == 0) {
            $amountToPay = $inquiry->downpayment_amount;
            $paymentType = 'downpayment';
        } else {
            $amountToPay = $inquiry->quotation_amount - $inquiry->amount_paid;
            $paymentType = 'balance';
        }

        if ($amountToPay <= 0) {
            return response()->json(['message' => 'No balance to pay.'], 400);
        }

        $metadata = [
            'type' => 'inquiry',
            'inquiry_id' => (string) $inquiry->id,
            'customer_name' => (string) $inquiry->customer_name,
            'payment_type' => (string) $paymentType
        ];

        try {
            $response = $this->paymongo->createGcashSource($amountToPay, $metadata);

            $inquiry->update([
                'payment_method' => 'gcash',
                'payment_intent_id' => $response['data']['id'],
                'payment_status' => 'pending'
            ]);

            return response()->json([
                'checkout_url' => $response['data']['attributes']['redirect']['checkout_url']
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create payment session: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Choose onsite payment
     */
    public function payOnsite(Request $request, $id)
    {
        $userId = Auth::id();
        $inquiry = Inquiry::where('user_id', $userId)->findOrFail($id);

        $inquiry->update([
            'payment_method' => 'onsite',
            'payment_status' => 'pay_onsite'
        ]);

        return response()->json([
            'message' => 'Payment method set to Pay Onsite.',
            'data' => $inquiry
        ]);
    }

    /**
     * Admin: Manually mark as paid
     */
    public function markAsPaid(Request $request, $id)
    {
        $inquiry = Inquiry::findOrFail($id);

        $inquiry->update([
            'payment_status' => 'paid',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_reference' => $request->input('reference', 'MANUAL-' . strtoupper(uniqid()))
        ]);

        return response()->json([
            'message' => 'Inquiry marked as paid.',
            'data' => $inquiry
        ]);
    }
}
