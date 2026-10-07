<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleService
{
    public function search($request)
    {
        return Schedule::with('details')->filterSearch($request)->get();
    }

    public function findById(string $id)
    {
        return Schedule::with('details')->find($id);
    }

    public function createSchedule(array $data)
    {
        return DB::transaction(function () use ($data) {
            $schedule = Schedule::create($data);
            $schedule->details()->createMany($data['details']);

            return $schedule->load('details');
        });
    }

    public function updateSchedule(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $schedule = Schedule::lockForUpdate()->findOrFail($id);

            if ($schedule->employeeSchedules()->exists() && (isset($data['details']) || (isset($data['timezone']) && $data['timezone'] !== $schedule->timezone))) {
                throw ValidationException::withMessages(['schedule' => 'This schedule has assignments. Create a new template to change its working hours or timezone.']);
            }

            $schedule->update($data);
            if (isset($data['details'])) {
                $schedule->details()->delete();
                $schedule->details()->createMany($data['details']);
            }

            return $schedule->load('details');
        });
    }
}
