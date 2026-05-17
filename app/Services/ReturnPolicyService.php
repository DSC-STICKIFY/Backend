<?php

namespace App\Services;

use App\Models\ReturnPolicy;
use App\Models\ProductsModel;
use App\Models\OrdersModel;
use Carbon\Carbon;

class ReturnPolicyService
{
    /**
     * Resolve the most specific Return Policy for a given product.
     * Priority: Specific Product > Type > Category > Global
     */
    public function resolvePolicyForProduct(ProductsModel $product): ?ReturnPolicy
    {
        // 1. Specific Product
        $policy = ReturnPolicy::where('scope_type', 'product')
            ->where('product_id', $product->product_id)
            ->first();
        if ($policy) return $policy;

        // 2. Type
        if ($product->product_type) {
            $policy = ReturnPolicy::where('scope_type', 'type')
                ->where('type_name', $product->product_type)
                ->first();
            if ($policy) return $policy;
        }

        // 3. Category
        if ($product->product_category) {
            $policy = ReturnPolicy::where('scope_type', 'category')
                ->where('category_name', $product->product_category)
                ->first();
            if ($policy) return $policy;
        }

        // 4. Global
        $policy = ReturnPolicy::where('scope_type', 'all')->first();
        
        return $policy;
    }

    /**
     * Validate if a product in an order is eligible for return.
     */
    public function validateReturnEligibility(OrdersModel $order, ProductsModel $product): array
    {
        // If the order has not been delivered yet, maybe they can't "return" it yet?
        // But usually return window starts after delivery.
        // Assuming 'delivered' status or non-null delivered_at is required,
        // but if it's not implemented, we fallback to created_at or updated_at.
        $deliveredAt = $order->delivered_at ?? $order->updated_at;

        if (!$deliveredAt) {
            return [
                'is_eligible' => false,
                'reason' => 'Order has not been delivered yet.',
                'policy' => null,
            ];
        }

        $policy = $this->resolvePolicyForProduct($product);

        if (!$policy) {
            return [
                'is_eligible' => false, // Default to not returnable as requested by user
                'reason' => 'No applicable return policy found for this product.',
                'policy' => null,
            ];
        }

        if (!$policy->is_returnable) {
            return [
                'is_eligible' => false,
                'reason' => 'This product is marked as non-returnable.',
                'policy' => $policy,
            ];
        }

        $deadline = Carbon::parse($deliveredAt);
        
        switch ($policy->allowed_unit) {
            case 'minutes':
                $deadline->addMinutes($policy->allowed_value);
                break;
            case 'hours':
                $deadline->addHours($policy->allowed_value);
                break;
            case 'days':
                $deadline->addDays($policy->allowed_value);
                break;
        }

        $isEligible = Carbon::now()->isBefore($deadline);

        return [
            'is_eligible' => $isEligible,
            'reason' => $isEligible ? 'Eligible for return.' : 'Return window has expired.',
            'deadline' => $deadline->toIso8601String(),
            'policy' => $policy,
        ];
    }
}
