<?php

namespace App\Services\Setup;

use App\Models\Setup\Department;
use App\Models\Setup\Division;
use App\Models\Setup\Office;
use App\Models\Setup\Position;
use App\Models\Setup\ReportsItem;
use App\Models\Setup\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationalSetupService
{
    private const EMPLOYEE_COLUMNS = 'employee_id,first_name,middle_name,last_name';

    // -------------------------------------------------------------------------
    // Hierarchy Tree
    // -------------------------------------------------------------------------

    public function getHierarchyTree(bool $includeInactive = false)
    {
        $filter = function ($query) use ($includeInactive) {
            if (! $includeInactive) {
                $query->where('is_active', true);
            }
            $query->orderBy('sort_order')->orderBy('id');
        };

        $query = Office::query()
            ->whereNull('parent_id')
            ->with([
                'headEmployee:'.self::EMPLOYEE_COLUMNS,
                'children' => function ($q) use ($filter) {
                    $filter($q);
                    $q->with([
                        'headEmployee:'.self::EMPLOYEE_COLUMNS,
                        'departments' => function ($dq) use ($filter) {
                            $filter($dq);
                            $dq->with([
                                'headEmployee:'.self::EMPLOYEE_COLUMNS,
                                'divisions' => function ($divQ) use ($filter) {
                                    $filter($divQ);
                                    $divQ->with([
                                        'headEmployee:'.self::EMPLOYEE_COLUMNS,
                                        'sections' => function ($secQ) use ($filter) {
                                            $filter($secQ);
                                            $secQ->with('headEmployee:'.self::EMPLOYEE_COLUMNS);
                                        },
                                    ]);
                                },
                                'sections' => function ($secQ) use ($filter) {
                                    $filter($secQ);
                                    $secQ->whereNull('division_id')->with('headEmployee:'.self::EMPLOYEE_COLUMNS);
                                },
                            ]);
                        },
                    ]);
                },
                'departments' => function ($dq) use ($filter) {
                    $filter($dq);
                    $dq->with([
                        'headEmployee:'.self::EMPLOYEE_COLUMNS,
                        'divisions' => function ($divQ) use ($filter) {
                            $filter($divQ);
                            $divQ->with([
                                'headEmployee:'.self::EMPLOYEE_COLUMNS,
                                'sections' => function ($secQ) use ($filter) {
                                    $filter($secQ);
                                    $secQ->with('headEmployee:'.self::EMPLOYEE_COLUMNS);
                                },
                            ]);
                        },
                        'sections' => function ($secQ) use ($filter) {
                            $filter($secQ);
                            $secQ->whereNull('division_id')->with('headEmployee:'.self::EMPLOYEE_COLUMNS);
                        },
                    ]);
                },
            ]);

        $filter($query);

        return $query->get();
    }

    // -------------------------------------------------------------------------
    // Offices / Colleges
    // -------------------------------------------------------------------------

    public function searchOffices(Request $request)
    {
        return Office::query()
            ->with(['parent:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS])
            ->withCount(['children', 'departments', 'reportsItems', 'employees', 'clearanceRequirements', 'clearanceSignatories'])
            ->filterSearch($request)
            ->get()
            ->map(fn (Office $office) => $this->attachOfficeUsage($office));
    }

    public function findOffice(int $id): ?Office
    {
        $office = Office::query()
            ->with(['parent:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS, 'departments:id,code,name,office_id'])
            ->withCount(['children', 'departments', 'reportsItems', 'employees', 'clearanceRequirements', 'clearanceSignatories'])
            ->find($id);

        return $office ? $this->attachOfficeUsage($office) : null;
    }

    public function createOffice(array $data): Office
    {
        return DB::transaction(function () use ($data) {
            $office = Office::create([
                ...$data,
                'type' => $data['type'] ?? 'office',
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $office->load(['parent:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function updateOffice(int $id, array $data): Office
    {
        return DB::transaction(function () use ($id, $data) {
            $office = Office::lockForUpdate()->findOrFail($id);

            if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
                $this->assertNoOfficeCycle($office->id, (int) $data['parent_id']);
            }

            $office->update($data);

            return $office->fresh()->load(['parent:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function deleteOffice(int $id): void
    {
        DB::transaction(function () use ($id) {
            $office = Office::lockForUpdate()->findOrFail($id);

            if ($office->children()->exists()) {
                throw ValidationException::withMessages([
                    'office' => 'Cannot delete office because it has sub-offices/colleges assigned.',
                ]);
            }

            if ($office->departments()->exists()) {
                throw ValidationException::withMessages([
                    'office' => 'Cannot delete office because it has departments assigned.',
                ]);
            }

            if ($office->reportsItems()->exists()) {
                throw ValidationException::withMessages([
                    'office' => 'Cannot delete office because it is referenced by reports items.',
                ]);
            }

            if ($office->employees()->exists()) {
                throw ValidationException::withMessages([
                    'office' => 'Cannot delete office because it is assigned to employees.',
                ]);
            }

            if ($office->clearanceRequirements()->exists() || $office->clearanceSignatories()->exists()) {
                throw ValidationException::withMessages([
                    'office' => 'Cannot delete office because it is referenced in clearance setup.',
                ]);
            }

            $office->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Departments
    // -------------------------------------------------------------------------

    public function searchDepartments(Request $request)
    {
        return Department::query()
            ->with(['office:id,code,name,type', 'headEmployee:'.self::EMPLOYEE_COLUMNS])
            ->withCount(['divisions', 'sections', 'positions', 'reportsItems', 'employees', 'clearanceRequirements', 'clearanceSignatories'])
            ->filterSearch($request)
            ->get()
            ->map(fn (Department $department) => $this->attachDepartmentUsage($department));
    }

    public function findDepartment(int $id): ?Department
    {
        $department = Department::query()
            ->with(['office:id,code,name,type', 'headEmployee:'.self::EMPLOYEE_COLUMNS])
            ->withCount(['divisions', 'sections', 'positions', 'reportsItems', 'employees', 'clearanceRequirements', 'clearanceSignatories'])
            ->find($id);

        return $department ? $this->attachDepartmentUsage($department) : null;
    }

    public function createDepartment(array $data): Department
    {
        return DB::transaction(function () use ($data) {
            $department = Department::create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $department->load(['office:id,code,name,type', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function updateDepartment(int $id, array $data): Department
    {
        return DB::transaction(function () use ($id, $data) {
            $department = Department::lockForUpdate()->findOrFail($id);
            $department->update($data);

            return $department->fresh()->load(['office:id,code,name,type', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function deleteDepartment(int $id): void
    {
        DB::transaction(function () use ($id) {
            $department = Department::lockForUpdate()->findOrFail($id);

            if ($department->divisions()->exists() || $department->sections()->exists() || $department->positions()->exists()) {
                throw ValidationException::withMessages([
                    'department' => 'Cannot delete department while divisions, sections, or positions are assigned to it.',
                ]);
            }

            if ($department->reportsItems()->exists()) {
                throw ValidationException::withMessages([
                    'department' => 'Cannot delete department because it is referenced by reports items.',
                ]);
            }

            if ($department->employees()->exists()) {
                throw ValidationException::withMessages([
                    'department' => 'Cannot delete department because it is assigned to employees.',
                ]);
            }

            if ($department->clearanceRequirements()->exists() || $department->clearanceSignatories()->exists()) {
                throw ValidationException::withMessages([
                    'department' => 'Cannot delete department because it is referenced in clearance setup.',
                ]);
            }

            $department->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Divisions
    // -------------------------------------------------------------------------

    public function searchDivisions(Request $request)
    {
        return Division::query()
            ->with(['department:id,code,name,office_id', 'department.office:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS])
            ->withCount(['sections', 'positions', 'reportsItems', 'employees'])
            ->filterSearch($request)
            ->get()
            ->map(fn (Division $division) => $this->attachDivisionUsage($division));
    }

    public function findDivision(int $id): ?Division
    {
        $division = Division::query()
            ->with(['department:id,code,name,office_id', 'department.office:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS])
            ->withCount(['sections', 'positions', 'reportsItems', 'employees'])
            ->find($id);

        return $division ? $this->attachDivisionUsage($division) : null;
    }

    public function createDivision(array $data): Division
    {
        return DB::transaction(function () use ($data) {
            $division = Division::create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $division->load(['department:id,code,name,office_id', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function updateDivision(int $id, array $data): Division
    {
        return DB::transaction(function () use ($id, $data) {
            $division = Division::lockForUpdate()->findOrFail($id);
            $division->update($data);

            return $division->fresh()->load(['department:id,code,name,office_id', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function deleteDivision(int $id): void
    {
        DB::transaction(function () use ($id) {
            $division = Division::lockForUpdate()->findOrFail($id);

            if ($division->sections()->exists() || $division->positions()->exists() || $division->reportsItems()->exists() || $division->employees()->exists()) {
                throw ValidationException::withMessages([
                    'division' => 'Cannot delete division while sections, positions, reports items, or employees are assigned to it.',
                ]);
            }

            $division->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Sections / Units
    // -------------------------------------------------------------------------

    public function searchSections(Request $request)
    {
        return Section::query()
            ->with([
                'division:id,code,name,department_id',
                'department:id,code,name',
                'headEmployee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->withCount(['positions', 'reportsItems', 'employees'])
            ->filterSearch($request)
            ->get()
            ->map(fn (Section $section) => $this->attachSectionUsage($section));
    }

    public function findSection(int $id): ?Section
    {
        $section = Section::query()
            ->with([
                'division:id,code,name,department_id',
                'department:id,code,name',
                'headEmployee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->withCount(['positions', 'reportsItems', 'employees'])
            ->find($id);

        return $section ? $this->attachSectionUsage($section) : null;
    }

    public function createSection(array $data): Section
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->syncSectionParentDepartment($data);

            $section = Section::create([
                ...$payload,
                'sort_order' => $payload['sort_order'] ?? 0,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]);

            return $section->load(['division:id,code,name,department_id', 'department:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function updateSection(int $id, array $data): Section
    {
        return DB::transaction(function () use ($id, $data) {
            $section = Section::lockForUpdate()->findOrFail($id);

            $merged = [
                'division_id' => array_key_exists('division_id', $data) ? $data['division_id'] : $section->division_id,
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $section->department_id,
            ];
            $synced = $this->syncSectionParentDepartment($merged);

            $section->update([
                ...$data,
                'division_id' => $synced['division_id'],
                'department_id' => $synced['department_id'],
            ]);

            return $section->fresh()->load(['division:id,code,name,department_id', 'department:id,code,name', 'headEmployee:'.self::EMPLOYEE_COLUMNS]);
        });
    }

    public function deleteSection(int $id): void
    {
        DB::transaction(function () use ($id) {
            $section = Section::lockForUpdate()->findOrFail($id);

            if ($section->positions()->exists() || $section->reportsItems()->exists() || $section->employees()->exists()) {
                throw ValidationException::withMessages([
                    'section' => 'Cannot delete section while positions, reports items, or employees are assigned to it.',
                ]);
            }

            $section->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Positions
    // -------------------------------------------------------------------------

    public function searchPositions(Request $request)
    {
        return Position::query()
            ->with([
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'reportsToPosition:id,code,title',
            ])
            ->withCount(['subordinatePositions', 'reportsItems', 'reportingToItems', 'employees', 'clearanceSignatories'])
            ->filterSearch($request)
            ->get()
            ->map(fn (Position $position) => $this->attachPositionUsage($position));
    }

    public function findPosition(int $id): ?Position
    {
        $position = Position::query()
            ->with([
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'reportsToPosition:id,code,title',
            ])
            ->withCount(['subordinatePositions', 'reportsItems', 'reportingToItems', 'employees', 'clearanceSignatories'])
            ->find($id);

        return $position ? $this->attachPositionUsage($position) : null;
    }

    public function createPosition(array $data): Position
    {
        return DB::transaction(function () use ($data) {
            $position = Position::create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $position->load([
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'reportsToPosition:id,code,title',
            ]);
        });
    }

    public function updatePosition(int $id, array $data): Position
    {
        return DB::transaction(function () use ($id, $data) {
            $position = Position::lockForUpdate()->findOrFail($id);

            if (array_key_exists('reports_to_position_id', $data) && $data['reports_to_position_id'] !== null) {
                $this->assertNoPositionCycle($position->id, (int) $data['reports_to_position_id']);
            }

            $position->update($data);

            return $position->fresh()->load([
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'reportsToPosition:id,code,title',
            ]);
        });
    }

    public function deletePosition(int $id): void
    {
        DB::transaction(function () use ($id) {
            $position = Position::lockForUpdate()->findOrFail($id);

            if ($position->subordinatePositions()->exists()) {
                throw ValidationException::withMessages([
                    'position' => 'Cannot delete position because other positions report to it.',
                ]);
            }

            if ($position->reportsItems()->exists() || ReportsItem::where('reports_to_position_id', $position->id)->exists()) {
                throw ValidationException::withMessages([
                    'position' => 'Cannot delete position because it is referenced by reports items.',
                ]);
            }

            if ($position->employees()->exists()) {
                throw ValidationException::withMessages([
                    'position' => 'Cannot delete position because it is assigned to employees.',
                ]);
            }

            if ($position->clearanceSignatories()->exists()) {
                throw ValidationException::withMessages([
                    'position' => 'Cannot delete position because it is referenced by clearance signatories.',
                ]);
            }

            $position->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Reports Items
    // -------------------------------------------------------------------------

    public function searchReportsItems(Request $request)
    {
        return ReportsItem::query()
            ->with([
                'office:id,code,name',
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'position:id,code,title',
                'reportsToPosition:id,code,title',
                'reportsToEmployee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->filterSearch($request)
            ->get();
    }

    public function findReportsItem(int $id): ?ReportsItem
    {
        return ReportsItem::query()
            ->with([
                'office:id,code,name',
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'position:id,code,title',
                'reportsToPosition:id,code,title',
                'reportsToEmployee:'.self::EMPLOYEE_COLUMNS,
            ])
            ->find($id);
    }

    public function createReportsItem(array $data): ReportsItem
    {
        return DB::transaction(function () use ($data) {
            $this->assertReportsItemHierarchy($data['position_id'] ?? null, $data['reports_to_position_id'] ?? null);

            $item = ReportsItem::create([
                ...$data,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            return $item->load([
                'office:id,code,name',
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'position:id,code,title',
                'reportsToPosition:id,code,title',
                'reportsToEmployee:'.self::EMPLOYEE_COLUMNS,
            ]);
        });
    }

    public function updateReportsItem(int $id, array $data): ReportsItem
    {
        return DB::transaction(function () use ($id, $data) {
            $item = ReportsItem::lockForUpdate()->findOrFail($id);

            $positionId = array_key_exists('position_id', $data) ? $data['position_id'] : $item->position_id;
            $reportsToPositionId = array_key_exists('reports_to_position_id', $data)
                ? $data['reports_to_position_id']
                : $item->reports_to_position_id;

            $this->assertReportsItemHierarchy($positionId, $reportsToPositionId);

            $item->update($data);

            return $item->fresh()->load([
                'office:id,code,name',
                'department:id,code,name',
                'division:id,code,name',
                'section:id,code,name',
                'position:id,code,title',
                'reportsToPosition:id,code,title',
                'reportsToEmployee:'.self::EMPLOYEE_COLUMNS,
            ]);
        });
    }

    public function deleteReportsItem(int $id): void
    {
        DB::transaction(function () use ($id) {
            $item = ReportsItem::lockForUpdate()->findOrFail($id);
            $item->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Internal Integrity Helpers
    // -------------------------------------------------------------------------

    private function assertNoOfficeCycle(int $officeId, int $parentId): void
    {
        $cursor = $parentId;
        $visited = [$officeId => true];

        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot assign a parent office that creates a circular hierarchy.',
                ]);
            }

            $visited[$cursor] = true;
            $cursor = Office::where('id', $cursor)->value('parent_id');
        }
    }

    private function assertNoPositionCycle(int $positionId, int $reportsToId): void
    {
        $cursor = $reportsToId;
        $visited = [$positionId => true];

        while ($cursor !== null) {
            if (isset($visited[$cursor])) {
                throw ValidationException::withMessages([
                    'reports_to_position_id' => 'Cannot set reporting position that creates a circular hierarchy.',
                ]);
            }

            $visited[$cursor] = true;
            $cursor = Position::where('id', $cursor)->value('reports_to_position_id');
        }
    }

    private function syncSectionParentDepartment(array $data): array
    {
        $divisionId = $data['division_id'] ?? null;
        $departmentId = $data['department_id'] ?? null;

        if ($divisionId !== null) {
            $divisionDepartmentId = Division::where('id', $divisionId)->value('department_id');

            if ($divisionDepartmentId !== null) {
                if ($departmentId !== null && (int) $departmentId !== (int) $divisionDepartmentId) {
                    throw ValidationException::withMessages([
                        'division_id' => 'The selected division does not belong to the selected department.',
                    ]);
                }

                $data['department_id'] = (int) $divisionDepartmentId;
            }
        }

        return $data;
    }

    private function assertReportsItemHierarchy(?int $positionId, ?int $reportsToPositionId): void
    {
        if ($positionId !== null && $reportsToPositionId !== null && $positionId === $reportsToPositionId) {
            throw ValidationException::withMessages([
                'reports_to_position_id' => 'A position cannot report to itself in a reports item.',
            ]);
        }
    }

    private function attachOfficeUsage(Office $office): Office
    {
        return $this->applyUsageAttributes($office, [
            'sub-office' => (int) ($office->children_count ?? 0),
            'department' => (int) ($office->departments_count ?? 0),
            'employee' => (int) ($office->employees_count ?? 0),
            'reports item' => (int) ($office->reports_items_count ?? 0),
            'clearance requirement' => (int) ($office->clearance_requirements_count ?? 0),
            'clearance signatory' => (int) ($office->clearance_signatories_count ?? 0),
        ]);
    }

    private function attachDepartmentUsage(Department $department): Department
    {
        return $this->applyUsageAttributes($department, [
            'division' => (int) ($department->divisions_count ?? 0),
            'section' => (int) ($department->sections_count ?? 0),
            'position' => (int) ($department->positions_count ?? 0),
            'employee' => (int) ($department->employees_count ?? 0),
            'reports item' => (int) ($department->reports_items_count ?? 0),
            'clearance requirement' => (int) ($department->clearance_requirements_count ?? 0),
            'clearance signatory' => (int) ($department->clearance_signatories_count ?? 0),
        ]);
    }

    private function attachDivisionUsage(Division $division): Division
    {
        return $this->applyUsageAttributes($division, [
            'section' => (int) ($division->sections_count ?? 0),
            'position' => (int) ($division->positions_count ?? 0),
            'employee' => (int) ($division->employees_count ?? 0),
            'reports item' => (int) ($division->reports_items_count ?? 0),
        ]);
    }

    private function attachSectionUsage(Section $section): Section
    {
        return $this->applyUsageAttributes($section, [
            'position' => (int) ($section->positions_count ?? 0),
            'employee' => (int) ($section->employees_count ?? 0),
            'reports item' => (int) ($section->reports_items_count ?? 0),
        ]);
    }

    private function attachPositionUsage(Position $position): Position
    {
        return $this->applyUsageAttributes($position, [
            'subordinate position' => (int) ($position->subordinate_positions_count ?? 0),
            'employee' => (int) ($position->employees_count ?? 0),
            'reports item' => (int) ($position->reports_items_count ?? 0) + (int) ($position->reporting_to_items_count ?? 0),
            'clearance signatory' => (int) ($position->clearance_signatories_count ?? 0),
        ]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @param  array<string, int>  $counts
     * @return TModel
     */
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
