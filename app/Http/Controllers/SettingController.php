<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settingService) {}

    /**
     * PUBLIC — customer-facing, no auth needed.
     * GET /api/settings/refund-policy
     *
     * Used by ReturnRefundModal to show the refund % to customers.
     */
    public function refundPolicy(): JsonResponse
    {
        return response()->json($this->settingService->getRefundPolicy());
    }

    /**
     * ADMIN — get all settings.
     * GET /api/admin/settings
     */
    public function index(): JsonResponse
    {
        return response()->json($this->settingService->getAll());
    }

    /**
     * ADMIN — update settings.
     * POST /api/admin/settings
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'refund_percentage' => 'required|numeric|min:1|max:100',
        ]);

        $updated = $this->settingService->update($request->only('refund_percentage'));

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data'    => $updated,
        ]);
    }
}