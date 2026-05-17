<?php

namespace App\Interfaces;

use Illuminate\Http\JsonResponse;

interface AdminOrderServices
{
    public function getOrderList(): JsonResponse;

    public function getRecentOrders(): JsonResponse;

    public function confirmPayment(array $payment, int $orderId): JsonResponse;

    public function cancelOrder(int $orderId): JsonResponse;

    public function updateOrderStatus(int $orderId, string $status): JsonResponse;

    public function changeStatus(int $orderId, string $from, string $to, string $successMessage): JsonResponse;
}
