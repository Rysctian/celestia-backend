<?php

namespace App\Http\Controllers\Setup;

use App\Http\Requests\Setup\SetupReferenceItemRequest;
use App\Services\Setup\SetupReferenceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SetupReferenceController extends Controller
{
    public function __construct(private SetupReferenceService $referenceService) {}

    public function categories(string $setup_group)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->referenceService->listCategories($setup_group),
        ]);
    }

    public function index(Request $request, string $setup_group)
    {
        return response()->json([
            'message' => 'success',
            'data' => $this->referenceService->search($setup_group, $request),
        ]);
    }

    public function find(int $item_id, string $setup_group)
    {
        $item = $this->referenceService->findById($setup_group, $item_id);
        if (! $item) {
            return response()->json(['message' => 'Setup item not found.'], 404);
        }

        return response()->json([
            'message' => 'success',
            'data' => $item,
        ]);
    }

    public function store(SetupReferenceItemRequest $request, string $setup_group)
    {
        $item = $this->referenceService->createItem($setup_group, $request->validated());

        return response()->json([
            'message' => 'Setup item created successfully.',
            'data' => $item,
        ], 201);
    }

    public function update(SetupReferenceItemRequest $request, int $item_id, string $setup_group)
    {
        $item = $this->referenceService->updateItem($setup_group, $item_id, $request->validated());

        return response()->json([
            'message' => 'Setup item updated successfully.',
            'data' => $item,
        ]);
    }

    public function destroy(int $item_id, string $setup_group)
    {
        $this->referenceService->deleteItem($setup_group, $item_id);

        return response()->json([
            'message' => 'Setup item deleted successfully.',
        ]);
    }

    /**
     * Read-only endpoint for authenticated users to load active dropdown options across HRIS forms.
     */
    public function options(Request $request)
    {
        $categoriesParam = $request->query('categories');
        $categories = null;

        if (is_string($categoriesParam) && trim($categoriesParam) !== '') {
            $categories = array_values(array_filter(array_map('trim', explode(',', $categoriesParam))));
        } elseif (is_array($categoriesParam)) {
            $categories = array_values(array_filter(array_map('trim', $categoriesParam)));
        }

        $group = $request->query('group');

        return response()->json([
            'message' => 'success',
            'data' => $this->referenceService->getActiveOptions($categories, is_string($group) ? $group : null),
        ]);
    }
}
