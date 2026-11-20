<?php

namespace App\Http\Controllers\Setup;

use App\Http\Requests\Setup\DepartmentRequest;
use App\Http\Requests\Setup\DivisionRequest;
use App\Http\Requests\Setup\OfficeRequest;
use App\Http\Requests\Setup\PositionRequest;
use App\Http\Requests\Setup\ReportsItemRequest;
use App\Http\Requests\Setup\SectionRequest;
use App\Services\Setup\OrganizationalSetupService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrganizationalSetupController extends Controller
{
    public function __construct(private OrganizationalSetupService $orgService) {}

    public function tree(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->getHierarchyTree($request->boolean('include_inactive')),
        ]);
    }

    // -------------------------------------------------------------------------
    // Offices / Colleges
    // -------------------------------------------------------------------------

    public function indexOffices(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchOffices($request),
        ]);
    }

    public function findOffice(int $office_id)
    {
        $office = $this->orgService->findOffice($office_id);
        if (! $office) {
            return response()->json(['message' => 'Office not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $office]);
    }

    public function storeOffice(OfficeRequest $request)
    {
        $office = $this->orgService->createOffice($request->validated());

        return response()->json([
            'message' => 'Office created successfully.',
            'data' => $office,
        ], 201);
    }

    public function updateOffice(OfficeRequest $request, int $office_id)
    {
        $office = $this->orgService->updateOffice($office_id, $request->validated());

        return response()->json([
            'message' => 'Office updated successfully.',
            'data' => $office,
        ]);
    }

    public function destroyOffice(int $office_id)
    {
        $this->orgService->deleteOffice($office_id);

        return response()->json(['message' => 'Office deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Departments
    // -------------------------------------------------------------------------

    public function indexDepartments(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchDepartments($request),
        ]);
    }

    public function findDepartment(int $department_id)
    {
        $department = $this->orgService->findDepartment($department_id);
        if (! $department) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $department]);
    }

    public function storeDepartment(DepartmentRequest $request)
    {
        $department = $this->orgService->createDepartment($request->validated());

        return response()->json([
            'message' => 'Department created successfully.',
            'data' => $department,
        ], 201);
    }

    public function updateDepartment(DepartmentRequest $request, int $department_id)
    {
        $department = $this->orgService->updateDepartment($department_id, $request->validated());

        return response()->json([
            'message' => 'Department updated successfully.',
            'data' => $department,
        ]);
    }

    public function destroyDepartment(int $department_id)
    {
        $this->orgService->deleteDepartment($department_id);

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Divisions
    // -------------------------------------------------------------------------

    public function indexDivisions(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchDivisions($request),
        ]);
    }

    public function findDivision(int $division_id)
    {
        $division = $this->orgService->findDivision($division_id);
        if (! $division) {
            return response()->json(['message' => 'Division not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $division]);
    }

    public function storeDivision(DivisionRequest $request)
    {
        $division = $this->orgService->createDivision($request->validated());

        return response()->json([
            'message' => 'Division created successfully.',
            'data' => $division,
        ], 201);
    }

    public function updateDivision(DivisionRequest $request, int $division_id)
    {
        $division = $this->orgService->updateDivision($division_id, $request->validated());

        return response()->json([
            'message' => 'Division updated successfully.',
            'data' => $division,
        ]);
    }

    public function destroyDivision(int $division_id)
    {
        $this->orgService->deleteDivision($division_id);

        return response()->json(['message' => 'Division deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Sections / Units
    // -------------------------------------------------------------------------

    public function indexSections(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchSections($request),
        ]);
    }

    public function findSection(int $section_id)
    {
        $section = $this->orgService->findSection($section_id);
        if (! $section) {
            return response()->json(['message' => 'Section not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $section]);
    }

    public function storeSection(SectionRequest $request)
    {
        $section = $this->orgService->createSection($request->validated());

        return response()->json([
            'message' => 'Section created successfully.',
            'data' => $section,
        ], 201);
    }

    public function updateSection(SectionRequest $request, int $section_id)
    {
        $section = $this->orgService->updateSection($section_id, $request->validated());

        return response()->json([
            'message' => 'Section updated successfully.',
            'data' => $section,
        ]);
    }

    public function destroySection(int $section_id)
    {
        $this->orgService->deleteSection($section_id);

        return response()->json(['message' => 'Section deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Positions
    // -------------------------------------------------------------------------

    public function indexPositions(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchPositions($request),
        ]);
    }

    public function findPosition(int $position_id)
    {
        $position = $this->orgService->findPosition($position_id);
        if (! $position) {
            return response()->json(['message' => 'Position not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $position]);
    }

    public function storePosition(PositionRequest $request)
    {
        $position = $this->orgService->createPosition($request->validated());

        return response()->json([
            'message' => 'Position created successfully.',
            'data' => $position,
        ], 201);
    }

    public function updatePosition(PositionRequest $request, int $position_id)
    {
        $position = $this->orgService->updatePosition($position_id, $request->validated());

        return response()->json([
            'message' => 'Position updated successfully.',
            'data' => $position,
        ]);
    }

    public function destroyPosition(int $position_id)
    {
        $this->orgService->deletePosition($position_id);

        return response()->json(['message' => 'Position deleted successfully.']);
    }

    // -------------------------------------------------------------------------
    // Reports Items
    // -------------------------------------------------------------------------

    public function indexReportsItems(Request $request)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->orgService->searchReportsItems($request),
        ]);
    }

    public function findReportsItem(int $reports_item_id)
    {
        $item = $this->orgService->findReportsItem($reports_item_id);
        if (! $item) {
            return response()->json(['message' => 'Reports item not found.'], 404);
        }

        return response()->json(['message' => 'success', 'data' => $item]);
    }

    public function storeReportsItem(ReportsItemRequest $request)
    {
        $item = $this->orgService->createReportsItem($request->validated());

        return response()->json([
            'message' => 'Reports item created successfully.',
            'data' => $item,
        ], 201);
    }

    public function updateReportsItem(ReportsItemRequest $request, int $reports_item_id)
    {
        $item = $this->orgService->updateReportsItem($reports_item_id, $request->validated());

        return response()->json([
            'message' => 'Reports item updated successfully.',
            'data' => $item,
        ]);
    }

    public function destroyReportsItem(int $reports_item_id)
    {
        $this->orgService->deleteReportsItem($reports_item_id);

        return response()->json(['message' => 'Reports item deleted successfully.']);
    }
}
