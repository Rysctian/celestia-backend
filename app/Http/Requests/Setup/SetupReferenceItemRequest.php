<?php

namespace App\Http\Requests\Setup;

use App\Models\Setup\SetupReferenceItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SetupReferenceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Group routes enforce employee_info_setup, employment_setup, or administrative_setup access.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => $this->storeRules(),
            'PUT' => $this->updateRules(),
            default => [],
        };
    }

    private function storeRules(): array
    {
        $group = (string) $this->route('setup_group');
        $allowedCategories = SetupReferenceItem::categoryKeysForGroup($group);
        $category = (string) $this->input('category');

        return [
            'category' => ['required', 'string', Rule::in($allowedCategories)],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('setup_reference_items', 'code')->where(
                    fn ($query) => $query->where('category', $category)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:setup_reference_items,id'],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function updateRules(): array
    {
        $itemId = $this->route('item_id');
        $existingCategory = SetupReferenceItem::where('id', $itemId)->value('category');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('setup_reference_items', 'code')
                    ->where(fn ($query) => $query->where('category', $existingCategory))
                    ->ignore($itemId),
            ],
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:setup_reference_items,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('is_default') && $this->has('is_active') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'Default setup item must be active.');
            }
        }];
    }
}
