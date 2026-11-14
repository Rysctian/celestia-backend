<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\EmployeeLogImportRequest;
use App\Http\Requests\StoreEmployeeLogRequest;
use App\Imports\EmployeeLogsImport;
use App\Models\EmployeeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = EmployeeLog::with('employee:id,employee_id,first_name,last_name')
            ->filterSearch($request)
            ->get();

        return response()->json([
            'message' => 'success',
            'data' => $logs,
        ]);
    }

    public function store(StoreEmployeeLogRequest $request)
    {
        $data = $request->validated();
        $loggedAt = Carbon::parse($data['logged_at'])->format('Y-m-d H:i:s');

        $alreadyExists = EmployeeLog::query()
            ->where('employee_id', $data['employee_id'])
            ->where('logged_at', $loggedAt)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'logged_at' => 'A log already exists for this employee at the selected date and time.',
            ]);
        }

        $deviceId = trim((string) ($data['device_id'] ?? ''));

        $log = EmployeeLog::create([
            'employee_id' => $data['employee_id'],
            'logged_at' => $loggedAt,
            'device_id' => $deviceId !== '' ? $deviceId : 'MANUAL-ENTRY',
            'source' => 'manual',
        ]);

        $log->load('employee:id,employee_id,first_name,last_name');

        return response()->json([
            'message' => 'Attendance log recorded successfully.',
            'data' => $log,
        ], 201);
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/attendance-in-out-template.xlsx');

        return response()->download(
            $path,
            'attendance-in-out-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function import(EmployeeLogImportRequest $request)
    {
        $importer = new EmployeeLogsImport;
        Excel::import($importer, $request->file('file'));
        $summary = $importer->summary();

        return response()->json([
            'message' => "Imported {$summary['imported_count']} attendance log(s).",
            'data' => $summary,
        ], $summary['imported_count'] > 0 ? 201 : 200);
    }
}
