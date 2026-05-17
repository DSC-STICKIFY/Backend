<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Services\ServiceService;

class ServiceController extends Controller
{
    protected $service;

    public function __construct(ServiceService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return response()->json($this->service->getAll());
    }

    public function show($id)
    {
        return response()->json($this->service->getById($id));
    }

    public function store(AddServiceRequest $request)
    {
        return response()->json(
            $this->service->create($request),
            201
        );
    }

    public function update(UpdateServiceRequest $request, $id)
    {
        return response()->json(
            $this->service->update($request, $id)
        );
    }

    public function destroy($id)
    {
        $this->service->delete($id);

        return response()->json(['message' => 'Service deleted successfully']);
    }
}
