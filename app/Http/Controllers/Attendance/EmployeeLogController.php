<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\EmployeeLogImportRequest;
use App\Http\Requests\StoreEmployeeLogRequest;
use App\Imports\EmployeeLogsImport;
use App\Models\EmployeeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
        $deviceId = trim((string) ($data['device_id'] ?? ''));
        $resolvedDevice = $deviceId !== '' ? $deviceId : 'MANUAL-ENTRY';

        $timestamps = [];
        if (! empty($data['logged_at'])) {
            $timestamps[] = Carbon::parse($data['logged_at'])->format('Y-m-d H:i:s');
        } else {
            $date = Carbon::parse($data['date'])->format('Y-m-d');
            foreach (['am_in', 'am_out', 'pm_in', 'pm_out'] as $slot) {
                if (! empty($data[$slot])) {
                    $timestamps[] = Carbon::parse("{$date} {$data[$slot]}")->format('Y-m-d H:i:s');
                }
            }
        }

        $timestamps = array_values(array_unique($timestamps));
        if (empty($timestamps)) {
            throw ValidationException::withMessages([
                'am_in' => 'Please enter at least one AM or PM log time.',
            ]);
        }

        $existingTimestamps = EmployeeLog::query()
            ->where('employee_id', $data['employee_id'])
            ->whereIn('logged_at', $timestamps)
            ->pluck('logged_at')
            ->map(fn ($dt) => Carbon::parse($dt)->format('Y-m-d H:i:s'))
            ->flip();

        $newTimestamps = array_values(array_filter(
            $timestamps,
            fn ($ts) => ! $existingTimestamps->has($ts)
        ));

        if (empty($newTimestamps)) {
            throw ValidationException::withMessages([
                'logged_at' => 'A log already exists for this employee at the selected date and time.',
            ]);
        }

        $createdLogs = DB::transaction(function () use ($data, $newTimestamps, $resolvedDevice) {
            $created = [];
            foreach ($newTimestamps as $ts) {
                $log = EmployeeLog::create([
                    'employee_id' => $data['employee_id'],
                    'logged_at' => $ts,
                    'device_id' => $resolvedDevice,
                    'source' => 'manual',
                ]);
                $log->load('employee:id,employee_id,first_name,last_name');
                $created[] = $log;
            }

            return $created;
        });

        $skippedCount = count($timestamps) - count($createdLogs);

        return response()->json([
            'message' => count($createdLogs).' attendance log(s) recorded successfully.',
            'data' => count($createdLogs) === 1 && ! empty($data['logged_at']) ? $createdLogs[0] : $createdLogs,
            'summary' => [
                'imported_count' => count($createdLogs),
                'skipped_count' => $skippedCount,
            ],
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
