<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserManagementService;

class UserManagementController extends Controller
{
    protected $userManagementService;

    public function __construct(UserManagementService $userManagementService)
    {
        $this->userManagementService = $userManagementService;
    }

    public function updateEmployee(Request $request, $id)
    {
        try {
            $employee = $this->userManagementService->updateEmployee($request->all(), $id);

            return response()->json([
                'message'  => 'Employee updated successfully',
                'employee' => $employee
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function updateCustomer(Request $request, $id)
    {
        try {
            $user = $this->userManagementService->updateCustomer($request->all(), $id);

            return response()->json([
                'message' => 'Customer updated successfully',
                'user'    => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}