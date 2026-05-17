<?php

namespace App\Http\Controllers;

use App\Services\PromotionServices;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    protected $service;

    public function __construct(PromotionServices $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return response()->json([
            'data' => $this->service->getAllPromotions(),
        ]);
    }

    public function active(Request $request)
    {
        $userId      = auth()->id();
        $displayType = $request->query('display_type');

        return response()->json([
            'data' => $this->service->getActivePromotions($userId, $displayType),
        ]);
    }

    public function productPromotions($productId)
    {
        return response()->json([
            'data' => $this->service->getPromotionsForProduct($productId),
        ]);
    }

    public function show($id)
    {
        $promo = $this->service->findPromotion($id);
        if (!$promo) {
            return response()->json(['message' => 'Promotion not found'], 404);
        }
        return response()->json(['data' => $promo]);
    }

    public function store(Request $request)
    {
        $promo = $this->service->createPromotion($request->all());
        return response()->json([
            'message' => 'Promotion created successfully',
            'data'    => $promo,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $promo = $this->service->updatePromotion($id, $request->all());
        if (!$promo) {
            return response()->json(['message' => 'Promotion not found'], 404);
        }
        return response()->json([
            'message' => 'Promotion updated successfully',
            'data'    => $promo,
        ]);
    }

    public function destroy($id)
    {
        $deleted = $this->service->deletePromotion($id);
        if (!$deleted) {
            return response()->json(['message' => 'Promotion not found'], 404);
        }
        return response()->json(['message' => 'Promotion deleted successfully']);
    }

    public function notify($id)
    {
        $result = $this->service->notifyUsersOfExistingPromotion($id);
        if (!$result['success']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']]);
    }
}