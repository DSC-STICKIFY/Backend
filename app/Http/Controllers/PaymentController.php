<?php

namespace App\Http\Controllers;

use App\Models\OrdersModel;
use App\Services\PayMongoService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PayMongoService $paymongo;
    public function __construct(PayMongoService $paymongo)
    {
        $this->paymongo = $paymongo;
    }

            public function payViaGcash(Request $request)
        {
            $orderId = $request->input('order_id');

            $order = is_numeric($orderId)
                ? OrdersModel::findOrFail($orderId)
                : OrdersModel::where('order_number', $orderId)->firstOrFail();

            $amount = floatval($order->total_price);

            if (!$amount) {
                return response()->json(['message' => 'Order total is missing.'], 400);
            }

            $response = $this->paymongo->createGcashSource($amount, [
                'order_id' => (string) $order->order_id,
                'type'     => 'order'
            ]);

            $order->update([
                'paymongo_source_id' => $response['data']['id'],
                'status'             => 'Pending Payment',
            ]);

            return response()->json([
                'checkout_url' => $response['data']['attributes']['redirect']['checkout_url'],
            ]);
        }
}
