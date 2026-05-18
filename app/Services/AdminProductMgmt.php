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

        return [
            'product_id' => $product->product_id,
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
            'created_at' => $product->created_at,
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
        $products = ProductsModel::all();

        $formatted = collect($products)->map(fn($p) => $this->transformProduct($p));

        return response()->json(['data' => $formatted]);
    }

    public function viewProductDetails(int $id)
    {
        $product = ProductsModel::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'data' => $this->transformProduct($product),
        ]);
    }

    public function getProductsByCategory(string $category)
    {
        $products = ProductsModel::where('product_category', $category)->get();

        $formatted = $products->map(fn($p) => $this->transformProduct($p));

        return response()->json(['data' => $formatted]);
    }
}
