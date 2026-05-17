<?php

namespace App\Services;

use App\Models\SettingModel;

class SettingService
{
    /**
     * Get all exposed settings (add more keys here as needed).
     */
    public function getAll(): array
    {
        return [
            'refund_percentage' => (float) SettingModel::get('refund_percentage', 70),
        ];
    }

    /**
     * Get only the public-facing refund policy (no auth required).
     */
    public function getRefundPolicy(): array
    {
        return [
            'refund_percentage' => (float) SettingModel::get('refund_percentage', 70),
        ];
    }

    /**
     * Update settings. Only whitelisted keys are allowed.
     */
    public function update(array $data): array
    {
        $allowed = ['refund_percentage'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                SettingModel::set($key, $data[$key]);
            }
        }

        return $this->getAll();
    }
}