<?php

namespace App\Interfaces;

interface ProductViewerInterface
{
    public function getAllproducts();

    public function viewProductDetails(int $id);
}
