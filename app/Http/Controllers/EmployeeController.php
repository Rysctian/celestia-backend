<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employeeService) {}

    public function index()
    { 
       $employees = $this->employeeService->findAll();
       return response()->json(['message' => 'success','data' => $employees,]);
    }

    public function find(Request $request, string $employee_id)
    {
      $employee = $this->employeeService->findById($employee_id);
      if (!$employee) return response()->json(['message' => 'Employee not found.'], 404);
      
      return response()->json(['message' => 'success','data' => $employee]);
    }

    public function store(EmployeeRequest $request)
    {
      $employee = $this->employeeService->createEmployee($request->validated());

      return response()->json(['message' => 'Employee created successfully.','data' => $employee], 201);
    }

    public function update(EmployeeRequest $request, string $employee_id)
    {
      $updated_employee = $this->employeeService->updateUserAndEmployee($request->validated(), $employee_id);

      if(!$updated_employee) return response()->json(['message' => 'Employee Update Failed', 401]);

      
      return response()->json(['message' => 'Employee updated successfully.','data' => $updated_employee], 201);

    }
}