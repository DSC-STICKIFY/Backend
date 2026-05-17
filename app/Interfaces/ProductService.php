<?php

namespace App\Interfaces;

interface ProductService
{
    public function addItem(array $data);

    public function updateItem(int $id, array $data);

    public function deleteItem(int $id);
}
