<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    private const ORG_RELATIONS = [
        'office:id,code,name,type',
        'department:id,code,name,office_id',
        'division:id,code,name,department_id',
        'section:id,code,name,division_id,department_id',
        'position:id,code,title,salary_grade,job_level',
        'reportsTo:employee_id,first_name,middle_name,last_name',
    ];

    public function __construct() {}

    public function search($request)
    {
        return Employee::with([
            'office:id,code,name',
            'department:id,code,name',
            'division:id,code,name',
            'section:id,code,name',
            'position:id,code,title',
        ])
            ->filterSearch($request)
            ->get();
    }

    public function findById(string $empid): ?Employee
    {
        return Employee::with(['user.role', ...self::ORG_RELATIONS])
            ->where('employee_id', $empid)
            ->first();
    }

    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $data['employee_id'] = $this->generateEmployeId();
            $data['employment_status'] ??= 'ACTIVE';

            $employee = Employee::create($data);
            $this->createUser($employee);

            return $employee->fresh(['user.role', ...self::ORG_RELATIONS]);
        });
    }

    public function updateUserAndEmployee(array $data, string $empid): ?Employee
    {
        return DB::transaction(function () use ($data, $empid) {
            $employee = Employee::where('employee_id', $empid)->lockForUpdate()->first();
            if (! $employee) {
                return null;
            }

            $employee->update($data);
            $user = $employee->user;

            if ($user) {
                $userUpdates = [
                    'name' => trim($employee->first_name.' '.$employee->last_name),
                ];

                if ($employee->personal_email) {
                    $userUpdates['email'] = $employee->personal_email;
                }

                $user->update($userUpdates);
            }

            return $employee->fresh(['user.role', ...self::ORG_RELATIONS]);
        });
    }

    private function generateEmployeId(): string
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

    private function createUser(Employee $employee): User
    {
        return User::create([
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'employee_id' => $employee->employee_id,
            'email' => $employee->personal_email,
            'password' => strtoupper($employee->last_name),
        ]);
    }
}
