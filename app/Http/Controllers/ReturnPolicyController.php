<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReturnPolicy;
use App\Services\ReturnPolicyService;
use App\Models\OrdersModel;
use App\Models\ProductsModel;

class ReturnPolicyController extends Controller
{
    protected $policyService;

    public function __construct(ReturnPolicyService $policyService)
    {
        $this->policyService = $policyService;
    }

    public function index()
    {
        // Load with product relationship if exists
        $policies = ReturnPolicy::with('product')->orderBy('created_at', 'desc')->get();
        return response()->json($policies);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'allowed_value' => 'required|integer|min:0',
            'allowed_unit' => 'required|in:minutes,hours,days',
            'scope_type' => 'required|in:all,category,type,product',
            'category_name' => 'nullable|string',
            'type_name' => 'nullable|string',
            'product_id' => 'nullable|exists:products_table,product_id',
            'is_returnable' => 'boolean',
        ]);

        // Validate combination
        if ($validated['scope_type'] === 'category' && empty($validated['category_name'])) {
            return response()->json(['message' => 'Category name is required for category scope.'], 422);
        }
        if ($validated['scope_type'] === 'type' && empty($validated['type_name'])) {
            return response()->json(['message' => 'Type name is required for type scope.'], 422);
        }
        if ($validated['scope_type'] === 'product' && empty($validated['product_id'])) {
            return response()->json(['message' => 'Product is required for product scope.'], 422);
        }

        // Ensure only one global policy? We can just let priority take care of it, or prevent duplicates
        if ($validated['scope_type'] === 'all') {
            $existing = ReturnPolicy::where('scope_type', 'all')->first();
            if ($existing) {
                return response()->json(['message' => 'A global policy already exists.'], 422);
            }
        }

        $policy = ReturnPolicy::create($validated);
        
        return response()->json($policy->load('product'), 201);
    }

    public function update(Request $request, $id)
    {
        $policy = ReturnPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'allowed_value' => 'sometimes|integer|min:0',
            'allowed_unit' => 'sometimes|in:minutes,hours,days',
            'scope_type' => 'sometimes|in:all,category,type,product',
            'category_name' => 'nullable|string',
            'type_name' => 'nullable|string',
            'product_id' => 'nullable|exists:products_table,product_id',
            'is_returnable' => 'boolean',
        ]);

        $policy->update($validated);

        return response()->json($policy->load('product'));
    }

    public function destroy($id)
    {
        $policy = ReturnPolicy::findOrFail($id);
        $policy->delete();

        return response()->json(['message' => 'Policy deleted successfully']);
    }

    public function checkEligibility($orderId, $productId)
    {
        $order = OrdersModel::where('order_id', $orderId)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $product = ProductsModel::where('product_id', $productId)->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $eligibility = $this->policyService->validateReturnEligibility($order, $product);

        return response()->json($eligibility);
    }
}
