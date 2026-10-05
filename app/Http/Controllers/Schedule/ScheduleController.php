<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Requests\ScheduleRequest;
use App\Services\ScheduleService;
use Illuminate\Routing\Controller;

class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedService) {}

    public function index()
    {
        $schedules = $this->schedService->findAll();

        return response()->json(['message' => 'success', 'data' => $schedules]);
    }

    public function find(string $schedule_id)
    {
        $schedule = $this->schedService->findById($schedule_id);
        if (! $schedule) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $schedule]);
    }

    public function store(ScheduleRequest $request)
    {

        $schedule = $this->schedService->createSchedule($request->validated());

        return response()->json(['message' => 'Schedule created successfully.', 'data' => $schedule], 201);
    }

    public function update(ScheduleRequest $request, string $schedule_id)
    {
        $schedule = $this->schedService->updateSchedule($request->validated(), $schedule_id);

        return response()->json(['message' => 'Schedule updated successfully.', 'data' => $schedule]);
    }
}
