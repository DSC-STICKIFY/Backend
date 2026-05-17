<?php

namespace App\Interfaces;

interface ProductManagementInterface
{
    public function addProduct(array $data);

    public function updateProduct(int $id, array $data);

    public function deleteProduct(int $id);
}
