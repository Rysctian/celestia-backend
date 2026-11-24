<?php

namespace App\Services\Setup;

use App\Models\Setup\ClearanceRequirement;
use App\Models\Setup\ClearanceSignatory;
use App\Models\Setup\ClearanceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClearanceSetupService
{
    private const EMPLOYEE_COLUMNS = 'employee_id,first_name,middle_name,last_name';

    // -------------------------------------------------------------------------
    // Clearance Types
    // -------------------------------------------------------------------------

    public function searchTypes(Request $request)
    {
        return ClearanceType::query()
            ->withCount(['requirements', 'signatories'])
            ->filterSearch($request)
            ->get()
            ->map(fn (ClearanceType $type) => $this->attachTypeUsage($type));
    }

    public function findType(int $id): ?ClearanceType
    {
        $type = ClearanceType::query()
            ->with([
                'requirements.office:id,code,name',
                'requirements.department:id,code,name',
                'signatories.clearanceRequirement:id,code,name',
                'signatories.office:id,code,name',
                'signatories.department:id,code,name',
                'signatories.position:id,code,title',
                'signatories.employee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->withCount(['requirements', 'signatories'])
            ->find($id);

        return $type ? $this->attachTypeUsage($type) : null;
    }

    public function createType(array $data): ClearanceType
    {
        return DB::transaction(function () use ($data) {
            return ClearanceType::create([
                ...$data,
                'applies_to' => $data['applies_to'] ?? 'separation',
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->fresh();
        });
    }

    public function updateType(int $id, array $data): ClearanceType
    {
        return DB::transaction(function () use ($id, $data) {
            $type = ClearanceType::lockForUpdate()->findOrFail($id);
            $type->update($data);

            return $type->fresh();
        });
    }

    public function deleteType(int $id): void
    {
        DB::transaction(function () use ($id) {
            $type = ClearanceType::lockForUpdate()->findOrFail($id);

            if ($type->requirements()->exists() || $type->signatories()->exists()) {
                throw ValidationException::withMessages([
                    'clearance_type' => 'Cannot delete clearance type while requirements or signatories are still assigned to it.',
                ]);
            }

            $type->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Clearance Requirements
    // -------------------------------------------------------------------------

    public function searchRequirements(Request $request)
    {
        return ClearanceRequirement::query()
            ->with([
                'clearanceType:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
            ])
            ->withCount(['signatories'])
            ->filterSearch($request)
            ->get()
            ->map(fn (ClearanceRequirement $requirement) => $this->attachRequirementUsage($requirement));
    }

    public function findRequirement(int $id): ?ClearanceRequirement
    {
        $requirement = ClearanceRequirement::query()
            ->with([
                'clearanceType:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
                'signatories.position:id,code,title',
                'signatories.employee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->withCount(['signatories'])
            ->find($id);

        return $requirement ? $this->attachRequirementUsage($requirement) : null;
    }

    public function createRequirement(array $data): ClearanceRequirement
    {
        return DB::transaction(function () use ($data) {
            $requirement = ClearanceRequirement::create([
                ...$data,
                'is_mandatory' => (bool) ($data['is_mandatory'] ?? true),
                'requires_attachment' => (bool) ($data['requires_attachment'] ?? false),
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $requirement->load(['clearanceType:id,code,name', 'office:id,code,name', 'department:id,code,name']);
        });
    }

    public function updateRequirement(int $id, array $data): ClearanceRequirement
    {
        return DB::transaction(function () use ($id, $data) {
            $requirement = ClearanceRequirement::lockForUpdate()->findOrFail($id);

            if (
                array_key_exists('clearance_type_id', $data)
                && (int) $data['clearance_type_id'] !== (int) $requirement->clearance_type_id
                && $requirement->signatories()->exists()
            ) {
                throw ValidationException::withMessages([
                    'clearance_type_id' => 'Cannot move requirement to another clearance type while signatories are linked to it.',
                ]);
            }

            $requirement->update($data);

            return $requirement->fresh()->load(['clearanceType:id,code,name', 'office:id,code,name', 'department:id,code,name']);
        });
    }

    public function deleteRequirement(int $id): void
    {
        DB::transaction(function () use ($id) {
            $requirement = ClearanceRequirement::lockForUpdate()->findOrFail($id);

            if ($requirement->signatories()->exists()) {
                throw ValidationException::withMessages([
                    'clearance_requirement' => 'Cannot delete clearance requirement while signatories are linked to it.',
                ]);
            }

            $requirement->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Clearance Signatories
    // -------------------------------------------------------------------------

    public function searchSignatories(Request $request)
    {
        return ClearanceSignatory::query()
            ->with([
                'clearanceType:id,code,name',
                'clearanceRequirement:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
                'position:id,code,title',
                'employee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->filterSearch($request)
            ->get();
    }

    public function findSignatory(int $id): ?ClearanceSignatory
    {
        return ClearanceSignatory::query()
            ->with([
                'clearanceType:id,code,name',
                'clearanceRequirement:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
                'position:id,code,title',
                'employee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->find($id);
    }

    public function createSignatory(array $data): ClearanceSignatory
    {
        return DB::transaction(function () use ($data) {
            $this->assertRequirementMatchesType(
                (int) $data['clearance_type_id'],
                isset($data['clearance_requirement_id']) ? (int) $data['clearance_requirement_id'] : null
            );

            $signatory = ClearanceSignatory::create([
                ...$data,
                'sign_order' => $data['sign_order'] ?? 1,
                'is_required' => (bool) ($data['is_required'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $signatory->load([
                'clearanceType:id,code,name',
                'clearanceRequirement:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
                'position:id,code,title',
                'employee:'.self::EMPLOYEE_COLUMNS,
            ]);
        });
    }

    public function updateSignatory(int $id, array $data): ClearanceSignatory
    {
        return DB::transaction(function () use ($id, $data) {
            $signatory = ClearanceSignatory::lockForUpdate()->findOrFail($id);

            $typeId = (int) ($data['clearance_type_id'] ?? $signatory->clearance_type_id);
            $requirementId = array_key_exists('clearance_requirement_id', $data)
                ? ($data['clearance_requirement_id'] !== null ? (int) $data['clearance_requirement_id'] : null)
                : $signatory->clearance_requirement_id;

            $this->assertRequirementMatchesType($typeId, $requirementId);

            $signatory->update($data);

            return $signatory->fresh()->load([
                'clearanceType:id,code,name',
                'clearanceRequirement:id,code,name',
                'office:id,code,name',
                'department:id,code,name',
                'position:id,code,title',
                'employee:'.self::EMPLOYEE_COLUMNS,
            ]);
        });
    }

    public function deleteSignatory(int $id): void
    {
        DB::transaction(function () use ($id) {
            $signatory = ClearanceSignatory::lockForUpdate()->findOrFail($id);
            $signatory->delete();
        });
    }

    private function assertRequirementMatchesType(int $clearanceTypeId, ?int $requirementId): void
    {
        if ($requirementId === null) {
            return;
        }

        $requirementTypeId = ClearanceRequirement::where('id', $requirementId)->value('clearance_type_id');
        if ($requirementTypeId === null || (int) $requirementTypeId !== $clearanceTypeId) {
            throw ValidationException::withMessages([
                'clearance_requirement_id' => 'The selected clearance requirement does not belong to the selected clearance type.',
            ]);
        }
    }

    private function attachTypeUsage(ClearanceType $type): ClearanceType
    {
        return $this->applyUsageAttributes($type, [
            'requirement' => (int) ($type->requirements_count ?? 0),
            'signatory' => (int) ($type->signatories_count ?? 0),
        ]);
    }

    private function attachRequirementUsage(ClearanceRequirement $requirement): ClearanceRequirement
    {
        return $this->applyUsageAttributes($requirement, [
            'signatory' => (int) ($requirement->signatories_count ?? 0),
        ]);
    }

    private function applyUsageAttributes($model, array $counts)
    {
        $total = 0;
        $parts = [];

        foreach ($counts as $singular => $count) {
            if ($count <= 0) {
                continue;
            }
            $total += $count;
            $label = $count === 1 ? $singular : (str_ends_with($singular, 'y') ? substr($singular, 0, -1).'ies' : $singular.'s');
            $parts[] = "{$count} {$label}";
        }

        $model->setAttribute('usage_count', $total);
        $model->setAttribute('is_in_use', $total > 0);
        $model->setAttribute('usage_reason', $total > 0 ? 'In use by '.implode(', ', $parts) : null);

        return $model;
    }
}
