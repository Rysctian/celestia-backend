<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Requests\AttendanceCutoffRequest;
use App\Http\Requests\AttendanceReadRequest;
use App\Models\AttendanceCutoffSummary;
use App\Services\Attendance\AttendanceCutoffService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceCutoffController extends Controller
{
    public function __construct(private AttendanceCutoffService $cutoffs) {}

    public function index(AttendanceReadRequest $request)
    {
        $summaries = AttendanceCutoffSummary::where('employee_id', $request->employee_id)
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('date_to', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('date_from', '<=', $date))
            ->orderByDesc('date_from')->limit($request->integer('limit', 15))->offset($request->integer('offset'))->get();

        return response()->json(['message' => 'success', 'data' => $summaries]);
    }

    public function find(Request $request, int $cutoff_id)
    {
        $summary = AttendanceCutoffSummary::findOrFail($cutoff_id);
        abort_unless($request->user()->hasMenuAccess('attendance', 'view')
            || ($request->user()->employee_id === $summary->employee_id && $request->user()->hasMenuAccess('employee_201', 'view')), 403);

        return response()->json(['message' => 'success', 'data' => $summary]);
    }

    public function store(AttendanceCutoffRequest $request)
    {
        return response()->json(['message' => 'Attendance cutoff computed.', 'data' => $this->cutoffs->build($request->validated())], 201);
    }

    public function confirm(AttendanceCutoffRequest $request, int $cutoff_id)
    {
        return response()->json(['message' => 'Attendance cutoff confirmed and locked.', 'data' => $this->cutoffs->confirm($cutoff_id, $request->user()->id)]);
    }

    public function reopen(AttendanceCutoffRequest $request, int $cutoff_id)
    {
        return response()->json(['message' => 'Attendance cutoff reopened.', 'data' => $this->cutoffs->reopen($cutoff_id, $request->user()->id, $request->validated('reason'))]);
    }
}
