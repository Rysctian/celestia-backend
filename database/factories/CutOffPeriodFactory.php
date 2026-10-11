<?php

namespace Database\Factories;

use App\Models\CutOff;
use App\Models\CutOffPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CutOffPeriod> */
class CutOffPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cut_off_id' => CutOff::factory(),
            'cut_off_no' => 1,
            ...$this->periodDates(CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()),
        ];
    }

    public function forCutOff(CutOff $cutOff, int $number = 1): static
    {
        $start = CarbonImmutable::create($cutOff->year, $cutOff->month, 1)->startOfDay();
        $end = $start->endOfMonth();
        if ($cutOff->schedule_type === 'semi_monthly') {
            if ($number === 1) {
                $end = $start->day(15);
            } else {
                $start = $start->day(16);
            }
        }

        return $this->state([
            'cut_off_id' => $cutOff->id, 'cut_off_no' => $number,
            ...$this->periodDates($start, $end),
        ]);
    }

    public function withoutDtr(): static
    {
        return $this->state([
            'no_dtr' => true, 'dtr_date_from' => null, 'dtr_date_to' => null,
            'confirmation_start_date' => null, 'confirmation_end_date' => null,
            'confirmation_time_from' => null, 'confirmation_time_to' => null,
        ]);
    }

    private function periodDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'no_dtr' => false,
            'dtr_date_from' => $start->toDateString(), 'dtr_date_to' => $end->toDateString(),
            'confirmation_start_date' => $end->addDay()->toDateString(),
            'confirmation_end_date' => $end->addDays(2)->toDateString(),
            'confirmation_time_from' => '08:00:00', 'confirmation_time_to' => '17:00:00',
            'payroll_date_from' => $start->toDateString(), 'payroll_date_to' => $end->toDateString(),
            'release_date' => $end->addDays(5)->toDateString(),
        ];
    }
}
