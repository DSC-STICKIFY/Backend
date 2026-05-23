<?php

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\OrdersModel;
use App\Models\UserModel;
use App\Models\SubAdminModel;
use Illuminate\Support\Facades\DB;
use App\Models\ServicePaymentModel;

class AdminDashboardServices
{
    public function getAllRegisteredUser()
    {
        $users = UserModel::all();

        return $users;
    }

    public function getAllEmployees()
    {
        $employees = EmployeeModel::all();

        return $employees;
    }

    public function getAllArtists()
    {
        // Explicitly filter for 'artist' role in the employees table
        $artists = EmployeeModel::where('role', 'artist')->get();

        return $artists;
    }

    public function getAllSubAdmins()
    {
        $subadmins = SubAdminModel::all();

        return $subadmins;
    }

    public function getRecentOrders()
    {
        $orders = OrdersModel::with([]);

        return $orders;
    }

    public function addEmployee(array $employeeData)
    {
        if (isset($employeeData['password'])) {
            $employeeData['password'] = \Illuminate\Support\Facades\Hash::make($employeeData['password']);
        }
        if (isset($employeeData['role'])) {
            $employeeData['role'] = strtolower(str_replace(' ', '_', $employeeData['role']));
        }
        $employee = EmployeeModel::create($employeeData);

        return $employee;
    }

    public function deleteEmployee($id)
    {
        return DB::transaction(function () use ($id) {
            $employee = EmployeeModel::findOrFail($id);
            
            // Delete related service payments first to satisfy foreign key constraint
            ServicePaymentModel::where('employee_id', $id)->delete();
            
            return $employee->delete();
        });
    }

    public function deleteSubAdmin($id)
    {
        $subadmin = SubAdminModel::findOrFail($id);
        return $subadmin->delete();
    }
}
