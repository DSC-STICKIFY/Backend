<?php

namespace App\Services;

use App\Interfaces\ProductManagementInterface;
use App\Interfaces\ProductViewerInterface;
use App\Models\ProductsModel;
use App\Services\PromotionServices;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;

class AdminProductMgmt implements ProductManagementInterface, ProductViewerInterface
{
    protected $promotionService;

    public function __construct(PromotionServices $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    private function transformProduct($product)
    {
        $originalPrice = (float) $product->product_price;
        $userId = auth('sanctum')->id();
        $activePromos = $this->promotionService->getActivePromotions($userId);

        $bestPrice = $originalPrice;
        $appliedPromo = null;

        foreach ($activePromos as $promo) {
            // Check if promo applies to a single unit (quantity 1)
            if (($promo['min_quantity'] ?? 0) <= 1 && ($promo['min_amount'] ?? 0) <= $originalPrice) {
                $discount = $this->promotionService->calculateDiscount($promo, $originalPrice, 1);
                $discountedPrice = $originalPrice - $discount;

                if ($discountedPrice < $bestPrice) {
                    $bestPrice = $discountedPrice;
                    $appliedPromo = $promo['name'];
                }
            }
        }

        $designs = $product->designs ? $product->designs->map(function ($d) {
            return [
                'id' => $d->id,
                'design_name' => $d->design_name,
                'design_image' => $d->design_image ? asset('storage/' . $d->design_image) : null,
                'additional_price' => (float) $d->additional_price,
            ];
        }) : [];

        $qualities = $product->qualities ? $product->qualities->map(function ($q) {
            return [
                'id' => $q->id,
                'quality_name' => $q->quality_name,
                'description' => $q->description,
                'additional_price' => (float) $q->additional_price,
            ];
        }) : [];

        $sizes = $product->sizes ? $product->sizes->map(function ($s) {
            return [
                'id' => $s->id,
                'size_name' => $s->size_name,
                'additional_price' => (float) $s->additional_price,
            ];
        }) : [];

        return [
            'product_id' => $product->product_id,
            'uuid' => $product->uuid,
            'product_name' => $product->product_name,
            'product_price' => $originalPrice,
            'discounted_price' => $bestPrice < $originalPrice ? $bestPrice : null,
            'applied_promo' => $appliedPromo,
            'product_image' => $product->product_image,
            'product_category' => $product->product_category,
            'product_type' => $product->product_type,
            'price_map_image' => $product->price_map_image,
            'wrap_price' => $product->wrap_price,
            'glossy_price' => $product->glossy_price,
            'hologram_price' => $product->hologram_price,
            'product_description' => $product->product_description,
            'is_car_service' => (bool) $product->is_car_service,
            'is_motor_service' => (bool) $product->is_motor_service,
            'is_customizable' => (bool) $product->is_customizable,
            'product_quantity' => (int) $product->product_quantity,
            'shelf_location' => $product->shelf_location,
            'created_at' => $product->created_at,
            'designs' => $designs,
            'qualities' => $qualities,
            'sizes' => $sizes,
        ];
    }

    public function addProduct(array $data)
    {
        \Log::info('Adding product', $data);
        if (isset($data['product_image'])) {
            $file = $data['product_image'];
            $filename = uniqid('prod_') . '.webp';
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file);
            $image->scaleDown(width: 800);
            $encoded = $image->encode(new WebpEncoder(80));
            
            Storage::disk('public')->put('products/' . $filename, (string) $encoded);
            $data['product_image'] = 'products/' . $filename;
        }

        if (isset($data['price_map_image'])) {
            $file = $data['price_map_image'];
            $filename = uniqid('map_') . '.webp';
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file);
            $image->scaleDown(width: 800);
            $encoded = $image->encode(new WebpEncoder(80));
            
            Storage::disk('public')->put('products/' . $filename, (string) $encoded);
            $data['price_map_image'] = 'products/' . $filename;
        }

        $product = ProductsModel::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $this->transformProduct($product),
        ], 201);
    }

    public function updateProduct(int $id, array $data)
    {
        \Log::info('Updating product ' . $id, $data);
        $product = ProductsModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if (isset($data['product_image'])) {
            if ($product->product_image) {
                Storage::disk('public')->delete($product->product_image);
            }
            $file = $data['product_image'];
            $filename = uniqid('prod_') . '.webp';
            
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file);
            $image->scaleDown(width: 800);
            $encoded = $image->encode(new WebpEncoder(80));
            
            Storage::disk('public')->put('products/' . $filename, (string) $encoded);
            $data['product_image'] = 'products/' . $filename;
        }

        if (isset($data['price_map_image'])) {
            if ($product->price_map_image) {
                Storage::disk('public')->delete($product->price_map_image);
            }
            $file = $data['price_map_image'];
            $filename = uniqid('map_') . '.webp';
            
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file);
            $image->scaleDown(width: 800);
            $encoded = $image->encode(new WebpEncoder(80));
            
            Storage::disk('public')->put('products/' . $filename, (string) $encoded);
            $data['price_map_image'] = 'products/' . $filename;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $this->transformProduct($product),
        ]);
    }

    public function deleteProduct(int $id)
    {
        $product = ProductsModel::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($product->product_image) {
            Storage::disk('public')->delete($product->product_image);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function getAllproducts()
    {
        $products = ProductsModel::with(['designs', 'qualities', 'sizes'])->get();

        $formatted = collect($products)->map(fn($p) => $this->transformProduct($p));

        return response()->json(['data' => $formatted]);
    }

    public function viewProductDetails(int $id)
    {
        $product = ProductsModel::with(['designs', 'qualities', 'sizes'])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'data' => $this->transformProduct($product),
        ]);
    }

    public function getProductsByCategory(string $category)
    {
        $products = ProductsModel::with(['designs', 'qualities', 'sizes'])->where('product_category', $category)->get();

        $formatted = $products->map(fn($p) => $this->transformProduct($p));

        return response()->json(['data' => $formatted]);
    }
    public function addDesign(int $productId, array $data)
    {
        $product = ProductsModel::find($productId);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        if (isset($data['design_image'])) {
            $file = $data['design_image'];
            $filename = uniqid('design_') . '.webp';
            $manager = new ImageManager(new Driver());
            $image = $manager->decode($file);
            $image->scaleDown(width: 600);
            $encoded = $image->encode(new WebpEncoder(80));
            Storage::disk('public')->put('products/designs/' . $filename, (string) $encoded);
            $data['design_image'] = 'products/designs/' . $filename;
        }

        $design = $product->designs()->create($data);
        return response()->json(['message' => 'Design added', 'data' => [
            'id' => $design->id,
            'design_name' => $design->design_name,
            'design_image' => $design->design_image ? asset('storage/' . $design->design_image) : null,
            'additional_price' => (float) $design->additional_price,
        ]]);
    }

    public function removeDesign(int $id)
    {
        $design = \App\Models\ProductDesign::find($id);
        if ($design) {
            if ($design->design_image) Storage::disk('public')->delete($design->design_image);
            $design->delete();
        }
        return response()->json(['message' => 'Design removed']);
    }

    public function addQuality(int $productId, array $data)
    {
        $product = ProductsModel::find($productId);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        $quality = $product->qualities()->create($data);
        return response()->json(['message' => 'Quality added', 'data' => [
            'id' => $quality->id,
            'quality_name' => $quality->quality_name,
            'description' => $quality->description,
            'additional_price' => (float) $quality->additional_price,
        ]]);
    }

    public function removeQuality(int $id)
    {
        \App\Models\ProductQuality::where('id', $id)->delete();
        return response()->json(['message' => 'Quality removed']);
    }

    public function addSize(int $productId, array $data)
    {
        $product = ProductsModel::find($productId);
        if (!$product) return response()->json(['message' => 'Product not found'], 404);

        $size = $product->sizes()->create($data);
        return response()->json(['message' => 'Size added', 'data' => [
            'id' => $size->id,
            'size_name' => $size->size_name,
            'additional_price' => (float) $size->additional_price,
        ]]);
    }

    public function removeSize(int $id)
    {
        \App\Models\ProductSize::where('id', $id)->delete();
        return response()->json(['message' => 'Size removed']);
    }
}
