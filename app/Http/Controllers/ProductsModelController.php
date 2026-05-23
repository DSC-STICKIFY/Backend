<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\AdminProductMgmt;

class ProductsModelController extends Controller
{
    protected $service;

    public function __construct(AdminProductMgmt $service)
    {
        $this->service = $service;
    }

    public function addProduct(AddProductRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('product_image')) {
            $data['product_image'] = $request->file('product_image');
        }

        return $this->service->addProduct($data);
    }

    public function updateProduct(UpdateProductRequest $request, $id)
    {
        $data = $request->validated();

        if ($request->hasFile('product_image')) {
            $data['product_image'] = $request->file('product_image');
        }

        return $this->service->updateProduct($id, $data);
    }

    public function deleteProduct($id)
    {
        return $this->service->deleteProduct($id);
    }

    public function getAllProducts()
    {
        return $this->service->getAllProducts();
    }

    public function viewProductDetails($id)
    {
        return $this->service->viewProductDetails($id);
    }

    public function getProductsByCategory($category)
    {
        return $this->service->getProductsByCategory($category);
    }

    public function addDesign(\Illuminate\Http\Request $request, $id)
    {
        $data = $request->validate([
            'design_name' => 'required|string',
            'design_image' => 'nullable|image',
            'additional_price' => 'nullable|numeric'
        ]);
        return $this->service->addDesign((int) $id, $data);
    }

    public function removeDesign($id)
    {
        return $this->service->removeDesign((int) $id);
    }

    public function addQuality(\Illuminate\Http\Request $request, $id)
    {
        $data = $request->validate([
            'quality_name' => 'required|string',
            'description' => 'nullable|string',
            'additional_price' => 'nullable|numeric'
        ]);
        return $this->service->addQuality((int) $id, $data);
    }

    public function removeQuality($id)
    {
        return $this->service->removeQuality((int) $id);
    }

    public function addSize(\Illuminate\Http\Request $request, $id)
    {
        $data = $request->validate([
            'size_name' => 'required|string',
            'additional_price' => 'nullable|numeric'
        ]);
        return $this->service->addSize((int) $id, $data);
    }

    public function removeSize($id)
    {
        return $this->service->removeSize((int) $id);
    }
}
