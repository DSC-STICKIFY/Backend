<?php

namespace App\Http\Controllers;

use App\Services\AdminOrderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtistOrderController extends Controller
{
    protected AdminOrderManager $orderManager;

    public function __construct(AdminOrderManager $orderManager)
    {
        $this->orderManager = $orderManager;
    }

    public function markInProgress(Request $request, int $id): JsonResponse
    {
        return $this->orderManager->markInProgress($request, $id);
    }

    public function uploadFinalDesign(Request $request, int $id): JsonResponse
    {
        return $this->orderManager->uploadFinalDesign($request, $id);
    }

    public function requestShipment(Request $request, int $id): JsonResponse
    {
        return $this->orderManager->requestShipment($request, $id);
    }
}
