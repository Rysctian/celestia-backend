<?php

namespace App\Services;

use App\Models\PayrollCutoff;
use Illuminate\Support\Facades\DB;

class PayrollCutoffService
{
    public function search($request)
    {
        return PayrollCutoff::with('releases')->filterSearch($request)->get();
    }

    public function findById(string $id)
    {
        return PayrollCutoff::with('releases')->find($id);
    }

    public function createPayrollCutoff(array $data)
    {
        return DB::transaction(function () use ($data) {
            $releases = $data['releases'];
            unset($data['releases']);

            $payrollCutoff = PayrollCutoff::create($data);
            $payrollCutoff->releases()->createMany($releases);

            return $payrollCutoff->load('releases');
        });
    }

    public function updatePayrollCutoff(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $payrollCutoff = PayrollCutoff::lockForUpdate()->findOrFail($id);
            $releases = $data['releases'];
            unset($data['releases']);

            $payrollCutoff->update($data);
            $payrollCutoff->releases()->delete();
            $payrollCutoff->releases()->createMany($releases);

            return $payrollCutoff->load('releases');
        });
    }

    public function deletePayrollCutoff(string $id): void
    {
        PayrollCutoff::findOrFail($id)->delete();
    }
}
