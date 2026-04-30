<?php

namespace App\Http\Requests\Admin\Pages;

use App\Core\Pages\Models\AdditionalFieldGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdditionalFieldGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('pages.additional_fields.manage') ?? false;
    }

    public function rules(): array
    {
        $group = $this->route('group');
        $groupId = $group instanceof AdditionalFieldGroup ? $group->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('additional_field_groups', 'key')->ignore($groupId)],
            'description' => ['nullable', 'string'],
            'location_rules' => ['nullable', 'array'],
            'location_rules.mode' => ['nullable', 'string', Rule::in(['all', 'any'])],
            'location_rules.rules' => ['nullable', 'array'],
            'location_rules.rules.*.field' => ['required', 'string', Rule::in(['entity_type', 'template', 'page_id', 'page_slug', 'page_path', 'is_home'])],
            'location_rules.rules.*.operator' => ['required', 'string', Rule::in(['=', '!=', 'in', 'not_in'])],
            'location_rules.rules.*.value' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'textarea', 'editor', 'image', 'url', 'number', 'toggle', 'select', 'group', 'repeater'])],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
