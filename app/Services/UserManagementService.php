<?php

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function updateEmployee(array $data, $id)
    {
        $validator = Validator::make($data, [
            'first_name'     => 'sometimes|string|max:255',
            'last_name'      => 'sometimes|string|max:255',
            'middle_name'    => 'sometimes|string|max:255',
            'email'          => 'sometimes|email|unique:employees,email,' . $id . ',employee_id',
            'password'       => 'sometimes|string|min:6',
            'address'        => 'sometimes|string',
            'contact_number' => 'sometimes|string',
            'role'           => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        if (isset($validated['password'])) {
            $validated['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        if (isset($validated['role'])) {
            $validated['role'] = strtolower(str_replace(' ', '_', $validated['role']));
        }

        $employee = EmployeeModel::findOrFail($id);
        $employee->update($validated);

        return $employee;
    }

    public function updateCustomer(array $data, $id)
    {
        $validator = Validator::make($data, [
            'first_name'     => 'sometimes|string|max:255',
            'middle_name'    => 'sometimes|string|max:255',
            'last_name'      => 'sometimes|string|max:255',
            'address'        => 'sometimes|string',
            'contact_number' => 'sometimes|string',
            'email'          => 'sometimes|email|unique:users_table,email,' . $id . ',user_id',
            'date_of_birth'  => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $user = UserModel::findOrFail($id);
        $user->update($validator->validated());

        return $user;
    }
}