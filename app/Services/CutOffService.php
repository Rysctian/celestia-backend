<?php

namespace App\Services;

use App\Models\CutOff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CutOffService
{
    public function search($request)
    {
        return CutOff::with('periods')->filterSearch($request)->get();
    }

    public function findById(string $id)
    {
        return CutOff::with('periods')->find($id);
    }

    public function createCutOff(array $data)
    {
        return DB::transaction(function () use ($data) {
            $periods = $this->preparePeriods($data);
            unset($data['periods']);
            $cutOff = CutOff::create($data);
            $cutOff->periods()->createMany($periods);

            return $cutOff->load('periods');
        });
    }

    public function updateCutOff(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $cutOff = CutOff::lockForUpdate()->findOrFail($id);
            $periods = $this->preparePeriods($data);
            unset($data['periods']);
            $cutOff->update($data);
            $cutOff->periods()->delete();
            $cutOff->periods()->createMany($periods);

            return $cutOff->load('periods');
        });
    }

    public function deleteCutOff(string $id): void
    {
        DB::transaction(fn () => CutOff::lockForUpdate()->findOrFail($id)->delete());
    }

    private function preparePeriods(array $data): array
    {
        $expectedNumbers = $data['schedule_type'] === 'monthly' ? [1] : [1, 2];
        $periodNumbers = array_map('intval', array_column($data['periods'], 'cut_off_no'));
        sort($periodNumbers);
        if ($periodNumbers !== $expectedNumbers) {
            throw ValidationException::withMessages([
                'periods' => 'Monthly requires the 1st Cut-Off only. Semi-Monthly requires the 1st and 2nd Cut-Off.',
            ]);
        }

        return array_map(function (array $period) {
            if ($period['no_dtr']) {
                // A payroll-only period must never retain an attendance/confirmation window.
                foreach (['dtr_date_from', 'dtr_date_to', 'confirmation_start_date', 'confirmation_end_date', 'confirmation_time_from', 'confirmation_time_to'] as $field) {
                    $period[$field] = null;
                }
            } else {
                $period['confirmation_time_from'] .= ':00';
                $period['confirmation_time_to'] .= ':00';
            }

            return $period;
        }, $data['periods']);
    }
}
