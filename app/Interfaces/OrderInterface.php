<?php

namespace App\Interfaces;

interface OrderInterface
{
    public function placeOrder(array $orderDetails);

    public function cancelOrder(int $orderId);

    public function getOrderHistory();
}
