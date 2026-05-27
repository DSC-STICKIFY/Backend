<?php

namespace App\Http\Controllers;

use App\Services\CustomOrderValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomOrderValidationController extends Controller
{
    protected CustomOrderValidationService $validationService;

    public function __construct(CustomOrderValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    // ── CS Actions ────────────────────────────────────────────────────────────

    /**
     * CS sends a custom order to Staff for manual feasibility check.
     */
    public function sendToStaff(Request $request, int $orderId): JsonResponse
    {
        return $this->validationService->sendToStaffValidation($orderId);
    }

    /**
     * CS rejects the order at the initial review stage (e.g. bad image quality).
     */
    public function csReject(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);
        return $this->validationService->csRejectOrder($orderId, $data['reason']);
    }

    /**
     * CS confirms customer's acceptance of partial accommodation.
     * Unlocks artist assignment stage.
     */
    public function acceptPartial(int $orderId): JsonResponse
    {
        return $this->validationService->customerAcceptsPartial($orderId);
    }

    /**
     * CS processes customer declining partial accommodation → cancel.
     */
    public function declinePartial(int $orderId): JsonResponse
    {
        return $this->validationService->customerDeclinesPartial($orderId);
    }

    /**
     * Fetch orders in the CS queue (pending review, partial response, artist assignment).
     */
    public function getCsQueue(): JsonResponse
    {
        return $this->validationService->getCSQueue();
    }

    // ── Staff Actions ─────────────────────────────────────────────────────────

    /**
     * Staff submits their manual validation result.
     * Accepts: can_accommodate | partially_accommodate | cannot_accommodate
     */
    public function staffValidate(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'validation_status'  => 'required|string|in:can_accommodate,partially_accommodate,cannot_accommodate',
            'staff_note'         => 'nullable|string|max:1000',
            'approved_quantity'  => 'nullable|integer|min:1',
            'rejection_reason'   => 'nullable|string|max:1000',
        ]);

        return $this->validationService->staffSubmitValidation(
            $orderId,
            $data['validation_status'],
            $data['staff_note'] ?? null,
            isset($data['approved_quantity']) ? (int) $data['approved_quantity'] : null,
            $data['rejection_reason'] ?? null,
        );
    }

    /**
     * Fetch all orders pending Staff manual validation.
     */
    public function getPendingValidation(): JsonResponse
    {
        return $this->validationService->getPendingStaffValidation();
    }

    /**
     * Staff completes production and requests shipment approval.
     */
    public function completeProduction(Request $request, int $orderId): JsonResponse
    {
        return $this->validationService->completeProduction($orderId);
    }
}
