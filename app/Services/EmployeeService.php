<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

class EmployeeService
{
    public function __construct() {}

    public function search($request)
    {
        $employees = Employee::filterSearch($request)->get();

        return $employees;
    }

    public function findById(string $empid)
    {
        return Employee::where('employee_id', $empid)->first();
    }

    public function createEmployee(array $data)
    {
        $data['employee_id'] = $this->generateEmployeId();
        $employee = Employee::create($data);

        if ($employee) {
            return $this->createUser($employee);
        }
    }

    public function updateUserAndEmployee(array $data, string $empid)
    {
        $employee = Employee::where('employee_id', $empid)->firstOrFail();

        $employee->update($data);
        $user = $this->findById($empid);
        if ($user) {
            $user->update([
                'name' => $employee->first_name.' '.$employee->last_name,
                'email' => $employee->personal_email,
            ]);
        }

        return $employee;
    }

    private function generateEmployeId()
    {
        $latest_id = Employee::orderByDesc('id')->pluck('employee_id')->first();
        $year = now()->year;
        if (! $latest_id) {
            return "EMP-{$year}0001";
        }

        $sequence = (int) substr($latest_id, -4);
        $sequence++;

        return sprintf('EMP-%d%04d', $year, $sequence);
    }

    private function createUser(Employee $employee)
    {
        return User::create([
            'name' => $employee->first_name.' '.$employee->last_name,
            'employee_id' => $employee->employee_id,
            'email' => $employee->personal_email,
            'password' => strtoupper($employee->last_name),
        ]);
    }
}
