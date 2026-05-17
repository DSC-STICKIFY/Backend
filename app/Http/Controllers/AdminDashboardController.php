<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddEmployeeRequest;
use App\Services\AdminDashboardServices;

class AdminDashboardController extends Controller
{
    protected $adminDashboard;

    public function __construct(AdminDashboardServices $adminDashboard)
    {
        $this->adminDashboard = $adminDashboard;
    }

    public function getUsers()
    {
        return $this->adminDashboard->getAllRegisteredUser();
    }

    public function getEmployees()
    {
        return $this->adminDashboard->getAllEmployees();
    }

    public function getArtists()
    {
        return $this->adminDashboard->getAllArtists();
    }

    public function getSubAdmins()
    {
        return $this->adminDashboard->getAllSubAdmins();
    }

    public function createEmployee(AddEmployeeRequest $request)
    {
        return $this->adminDashboard->addEmployee($request->validated());
    }

    public function deleteEmployee($id)
    {
        $this->adminDashboard->deleteEmployee($id);
        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function deleteSubAdmin($id)
    {
        $this->adminDashboard->deleteSubAdmin($id);
        return response()->json(['message' => 'SubAdmin deleted successfully']);
    }
}
