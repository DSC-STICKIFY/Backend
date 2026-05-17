<?php

namespace App\Services;

use App\Models\ServiceModel;

class ServiceService
{
    public function getAll()
    {
        return ServiceModel::all();
    }

    public function getById($id)
    {
        return ServiceModel::findOrFail($id);
    }

    public function create($request)
    {
        $validated = $request->validate([
            'service_name' => 'required|string|max:255',
            'service_description' => 'nullable|string',
            'services_category' => 'required|string|max:255',
        ]);

        return ServiceModel::create($validated);
    }

    public function update($request, $id)
    {
        $validated = $request->validate([
            'service_name' => 'sometimes|required|string|max:255',
            'service_description' => 'nullable|string',
            'services_category' => 'sometimes|required|string|max:255',
        ]);

        $service = ServiceModel::findOrFail($id);
        $service->update($validated);

        return $service;
    }

    public function delete($id)
    {
        $service = ServiceModel::findOrFail($id);
        $service->delete();

        return true;
    }
}
