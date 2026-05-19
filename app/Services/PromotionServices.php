<?php

namespace App\Services;

use App\Models\PromotionModel;
use App\Models\OrdersModel;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendPromotionEmail;

class PromotionServices
{
    private const TYPES_BY_CATEGORY = [
        "Stickers"         => ["Hologram","Glossy","Matte","Transparent","Glitter","Scratch","Cut out","Visor","Assorted","Hologram Sticker","Glossy Sticker","Matte Sticker","Die-Cut Sticker","Transparent Sticker"],
        "Decals & Wrap"    => ["Car Service Layout","Motor Service Layout","Car Wrap","Motorbike Decal","Full Wrap","Partial Wrap","Window Decal"],
        "Signage"          => ["Acrylic Signage","Neon Lights Signage","Panaflex Signage"],
        "Giveaways"        => ["Keychain","ID Lace","T-Shirt","Calling Cards","Caps","Mugs","Tarpulin","Sintra Board"],
        "Printing"         => ["Flyers","Brochures","Business Cards","Posters","Banners"],
        "Graphic Services" => ["Business Logo","Moto Vlog Logo"],
    ];

    private static function getAllTypes(): array
    {
        return array_merge(...array_values(self::TYPES_BY_CATEGORY));
    }

    private const CATEGORIES = [
        "Stickers",
        "Decals & Wrap",
        "Signage",
        "Giveaways",
        "Printing",
        "Graphic Services",
    ];

    // ── Transform ────────────────────────────────────────────────────────
    public function transform($promo): array
    {
        if (!$promo->relationLoaded('categories')) $promo->load('categories');
        if (!$promo->relationLoaded('products'))   $promo->load('products');
        if (!$promo->relationLoaded('types'))      $promo->load('types');

        return [
            'id'             => $promo->promotion_id,
            'promotion_id'   => $promo->promotion_id,
            'name'           => $promo->name,
            'description'    => $promo->description,
            'display_type'   => $promo->display_type ?? 'product',
            'discount_type'  => $promo->discount_type,
            'discount_value' => (float) $promo->discount_value,
            'min_quantity'   => $promo->min_quantity,
            'min_amount'     => (float) $promo->min_amount,
            'max_discount'   => $promo->max_discount ? (float) $promo->max_discount : null,
            'start_date'     => $promo->start_date?->toDateString(),
            'end_date'       => $promo->end_date?->toDateString(),
            'usage_limit'    => $promo->usage_limit,
            'usage_count'    => OrdersModel::where('promotion_id', $promo->promotion_id)->count(),
            'status'         => $promo->status,
            'created_at'     => $promo->created_at,
            'applicable_to'  => $this->getApplicableType($promo),
            'applicable_ids' => $this->getApplicableIds($promo),
            'product_uuids'  => $this->getPromoProductUuids($promo),
            'promo_image'    => $this->resolvePromoImage($promo),
        ];
    }

    private function getPromoProductUuids($promo): array
    {
        if ($promo->products->isNotEmpty()) {
            return $promo->products->pluck('uuid')->toArray();
        }

        if ($promo->types->isNotEmpty()) {
            $typeNames = $promo->types->pluck('type_name')->toArray();
            return \App\Models\ProductsModel::whereIn('product_type', $typeNames)->pluck('uuid')->toArray();
        }

        if ($promo->categories->isNotEmpty()) {
            $categoryNames = $promo->categories->pluck('category_name')->toArray();
            return \App\Models\ProductsModel::whereIn('product_category', $categoryNames)->pluck('uuid')->toArray();
        }

        return [];
    }

    private function resolvePromoImage($promo): ?string
    {
        if ($promo->products->isNotEmpty()) {
            return $promo->products->first()->product_image;
        }

        if ($promo->types->isNotEmpty()) {
            $typeName = $promo->types->first()->type_name;
            $product = \App\Models\ProductsModel::where('product_type', $typeName)->whereNotNull('product_image')->first();
            return $product ? $product->product_image : null;
        }

        if ($promo->categories->isNotEmpty()) {
            $categoryName = $promo->categories->first()->category_name;
            $product = \App\Models\ProductsModel::where('product_category', $categoryName)->whereNotNull('product_image')->first();
            return $product ? $product->product_image : null;
        }

        // Global promo - get any random product image or a popular product
        $product = \App\Models\ProductsModel::whereNotNull('product_image')->inRandomOrder()->first();
        return $product ? $product->product_image : null;
    }

    private function getApplicableType($promo): string
    {
        if ($promo->products->isNotEmpty())   return 'products';
        if ($promo->categories->isNotEmpty()) return 'categories';
        if ($promo->types->isNotEmpty())      return 'types';
        return 'all';
    }

    private function getApplicableIds($promo): array
    {
        if ($promo->products->isNotEmpty())
            return $promo->products->pluck('product_id')->toArray();
        if ($promo->categories->isNotEmpty())
            return $promo->categories->pluck('category_name')->toArray();
        if ($promo->types->isNotEmpty())
            return $promo->types->pluck('type_name')->toArray();
        return [];
    }

    // ── Validation ───────────────────────────────────────────────────────
    private function validateStore(array $data): array
    {
        $rules = [
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'display_type'   => 'required|in:product,checkout',
            'discount_type'  => 'required|in:fixed,percentage,free_shipping',
            'discount_value' => 'required_if:discount_type,!=,free_shipping|nullable|numeric|min:0',
            'min_quantity'   => 'nullable|integer|min:1',
            'min_amount'     => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'usage_limit'    => 'nullable|integer|min:1',
            'status'         => 'required|in:active,inactive',
            'applicable_to'  => 'required|in:all,categories,types,products',
            'applicable_ids' => 'required_if:applicable_to,categories,products|array',
        ];

        if (($data['applicable_to'] ?? '') === 'categories') {
            $rules['applicable_ids.*'] = 'string|in:' . implode(',', self::CATEGORIES);
        }
        if (($data['applicable_to'] ?? '') === 'types') {
            $rules['applicable_ids.*'] = 'string|in:' . implode(',', self::getAllTypes());
        }
        if (($data['discount_type'] ?? '') === 'free_shipping') {
            $rules['discount_value'] = 'nullable';
        }

        return Validator::make($data, $rules)->validate();
    }

    private function validateUpdate(array $data): array
    {
        $rules = [
            'name'           => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'display_type'   => 'sometimes|in:product,checkout',
            'discount_type'  => 'sometimes|in:fixed,percentage,free_shipping',
            'discount_value' => 'sometimes|numeric|min:0',
            'min_quantity'   => 'nullable|integer|min:1',
            'min_amount'     => 'nullable|numeric|min:0',
            'max_discount'   => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date',
            'usage_limit'    => 'nullable|integer|min:1',
            'status'         => 'sometimes|in:active,inactive',
            'applicable_to'  => 'sometimes|in:all,categories,types,products',
            'applicable_ids' => 'sometimes|array',
        ];

        if (isset($data['applicable_to']) && $data['applicable_to'] === 'categories') {
            $rules['applicable_ids.*'] = 'string|in:' . implode(',', self::CATEGORIES);
        }
        if (isset($data['applicable_to']) && $data['applicable_to'] === 'types') {
            $rules['applicable_ids.*'] = 'string|in:' . implode(',', self::getAllTypes());
        }

        return Validator::make($data, $rules)->validate();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────
    public function getAllPromotions()
    {
        return PromotionModel::with(['categories', 'products', 'types'])
            ->latest()->get()
            ->map(fn($p) => $this->transform($p));
    }

    public function getActivePromotions(?int $userId = null, ?string $displayType = null)
    {
        $now = Carbon::now();

        $query = PromotionModel::with(['categories', 'products', 'types'])
            ->where('status', 'active')
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $now->toDateString()));

        if ($displayType) {
            $query->where('display_type', $displayType);
        }

        $promos = $query->latest()->get();

        if ($userId) {
            $promos = $promos->filter(fn($p) =>
                !$p->usage_limit ||
                OrdersModel::where('user_id', $userId)
                    ->where('promotion_id', $p->promotion_id)
                    ->count() < $p->usage_limit
            );
        }

        return $promos->map(fn($p) => $this->transform($p))->values();
    }

    public function getPromotionsForProduct($productId)
    {
        $now = Carbon::now();
        $allPromos = PromotionModel::with(['categories', 'products', 'types'])
            ->where('status', 'active')
            ->where('display_type', 'product')
            ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $now->toDateString()))
            ->get();

        $applicable = $allPromos->filter(fn($promo) => $promo->appliesToProduct($productId));
        return $applicable->map(fn($p) => $this->transform($p))->values();
    }

    public function findPromotion($id): ?array
    {
        $promo = PromotionModel::with(['categories', 'products', 'types'])->find($id);
        return $promo ? $this->transform($promo) : null;
    }

    public function createPromotion(array $data): array
    {
        $validated     = $this->validateStore($data);
        $applicableTo  = $validated['applicable_to'];
        $applicableIds = $validated['applicable_ids'] ?? [];

        unset($validated['applicable_to'], $validated['applicable_ids']);

        if (($validated['discount_type'] ?? '') === 'free_shipping') {
            $validated['discount_value'] = 0;
        }

        $promo = null;

        DB::transaction(function () use ($validated, $applicableTo, $applicableIds, &$promo) {
            $promo = PromotionModel::create($validated);

            if ($applicableTo === 'categories' && !empty($applicableIds)) {
                DB::table('promotion_categories')->insert(
                    array_map(fn($name) => [
                        'promotion_id'  => $promo->promotion_id,
                        'category_name' => $name,
                    ], $applicableIds)
                );
            } elseif ($applicableTo === 'products' && !empty($applicableIds)) {
                DB::table('promotion_products')->insert(
                    array_map(fn($id) => [
                        'promotion_id' => $promo->promotion_id,
                        'product_id'   => (int) $id,
                    ], $applicableIds)
                );
            } elseif ($applicableTo === 'types' && !empty($applicableIds)) {
                DB::table('promotion_types')->insert(
                    array_map(fn($name) => [
                        'promotion_id' => $promo->promotion_id,
                        'type_name'    => $name,
                    ], $applicableIds)
                );
            }
        });

        $promo->load(['categories', 'products', 'types']);
        $transformed = $this->transform($promo);

        if ($transformed['status'] === 'active') {
            $this->notifyUsersOfPromotion($transformed);
        }

        return $transformed;
    }

    private function notifyUsersOfPromotion(array $promoData): void
    {
        $users = \App\Models\UserModel::whereNotNull('email_verified_at')
            ->where('receive_promotional_emails', true)
            ->get();

        \Log::info("Dispatching promotional emails for '{$promoData['name']}' to {$users->count()} users.");

        foreach ($users as $user) {
            SendPromotionEmail::dispatch($user, $promoData);
        }
    }

    public function notifyUsersOfExistingPromotion(int $id): array
    {
        $promo = PromotionModel::with(['categories', 'products', 'types'])->find($id);
        if (!$promo) {
            return ['success' => false, 'status' => 404, 'message' => 'Promotion not found'];
        }

        if ($promo->status !== 'active') {
            return ['success' => false, 'status' => 400, 'message' => 'Cannot notify users of an inactive promotion.'];
        }

        $this->notifyUsersOfPromotion($this->transform($promo));

        return ['success' => true, 'message' => "Notification emails for '{$promo->name}' have been queued."];
    }

    public function updatePromotion($id, array $data): ?array
    {
        $promo = PromotionModel::find($id);
        if (!$promo) return null;

        $validated     = $this->validateUpdate($data);
        $applicableTo  = $validated['applicable_to'] ?? null;
        $applicableIds = $validated['applicable_ids'] ?? [];

        unset($validated['applicable_to'], $validated['applicable_ids']);

        DB::transaction(function () use ($promo, $validated, $applicableTo, $applicableIds) {
            $promo->update($validated);

            if ($applicableTo !== null) {
                DB::table('promotion_categories')->where('promotion_id', $promo->promotion_id)->delete();
                DB::table('promotion_products')->where('promotion_id', $promo->promotion_id)->delete();
                DB::table('promotion_types')->where('promotion_id', $promo->promotion_id)->delete();

                if ($applicableTo === 'categories' && !empty($applicableIds)) {
                    DB::table('promotion_categories')->insert(
                        array_map(fn($name) => [
                            'promotion_id'  => $promo->promotion_id,
                            'category_name' => $name,
                        ], $applicableIds)
                    );
                } elseif ($applicableTo === 'products' && !empty($applicableIds)) {
                    DB::table('promotion_products')->insert(
                        array_map(fn($id) => [
                            'promotion_id' => $promo->promotion_id,
                            'product_id'   => (int) $id,
                        ], $applicableIds)
                    );
                } elseif ($applicableTo === 'types' && !empty($applicableIds)) {
                    DB::table('promotion_types')->insert(
                        array_map(fn($name) => [
                            'promotion_id' => $promo->promotion_id,
                            'type_name'    => $name,
                        ], $applicableIds)
                    );
                }
            }

            if (isset($validated['status']) && $validated['status'] === 'active' && $promo->getOriginal('status') !== 'active') {
                $this->notifyUsersOfPromotion($this->transform($promo));
            }
        });

        $promo->load(['categories', 'products', 'types']);
        return $this->transform($promo);
    }

    public function deletePromotion($id): bool
    {
        return PromotionModel::destroy($id) > 0;
    }

    public function calculateDiscount(array $promo, float $subtotal, int $quantity = 1, $productId = null): float
    {
        if ($productId) {
            $promoModel = PromotionModel::with(['categories', 'products'])->find($promo['id']);
            if (!$promoModel || !$promoModel->appliesToProduct($productId)) return 0;
        }

        if (!empty($promo['min_amount']) && $subtotal < $promo['min_amount']) return 0;
        if (!empty($promo['min_quantity']) && $quantity < $promo['min_quantity']) return 0;

        switch ($promo['discount_type']) {
            case 'fixed':
                return min((float) $promo['discount_value'], $subtotal);
            case 'percentage':
                $discount = $subtotal * ((float) $promo['discount_value'] / 100);
                if (!empty($promo['max_discount'])) {
                    $discount = min($discount, (float) $promo['max_discount']);
                }
                return round($discount, 2);
            case 'free_shipping':
                return 100.00;
            default:
                return 0;
        }
    }
}