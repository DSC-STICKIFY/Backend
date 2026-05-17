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

    public function getAllproducts()
    {
        return $this->service->getAllproducts();
    }

    public function viewProductDetails($id)
    {
        return $this->service->viewProductDetails($id);
    }

    public function getProductsByCategory($category)
    {
        return $this->service->getProductsByCategory($category);
    }
}
