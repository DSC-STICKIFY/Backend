<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServicePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Services\ServicePaymentService; // ✅ added missing import

class ServicePaymentController extends Controller
{
    protected $service;

    public function __construct(ServicePaymentService $service)
    {
        $this->service = $service;
    }

    // Get all payments
    public function index()
    {
        return response()->json($this->service->getAll());
    }

    // Get payment by ID
    public function show($id)
    {
        $payment = $this->service->getById($id);

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    // Create new payment
    public function store(ServicePaymentRequest $request)
    {
        $payment = $this->service->create($request->validated());

        return response()->json($payment, 201);
    }

    // Update payment
    public function update(UpdatePaymentRequest $request, $id)
    {
        $payment = $this->service->update($id, $request->validated());

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json($payment);
    }

    // Delete payment
    public function destroy($id)
    {
        $deleted = $this->service->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    // Get payments by specific service
    public function getByService($serviceId)
    {
        $payments = $this->service->getByService($serviceId);

        if ($payments->isEmpty()) {
            return response()->json(['message' => 'No payments found for this service'], 404);
        }

        return response()->json($payments);
    }
}
