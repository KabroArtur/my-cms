<?php

namespace App\Http\Requests\Admin\Pages;

use App\Core\Pages\Models\AdditionalFieldGroup;
use App\Core\Pages\Services\AdditionalFieldsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $fields = app(AdditionalFieldsService::class);

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', Rule::unique('additional_field_groups', 'key')->ignore($groupId)],
            'description' => ['nullable', 'string'],
            'location_rules' => ['nullable', 'array'],
            'location_rules.mode' => ['nullable', 'string', Rule::in(['all', 'any'])],
            'location_rules.rules' => ['nullable', 'array'],
            'location_rules.rules.*.field' => ['required', 'string', Rule::in(app(\App\Core\Pages\Services\FieldLocationResolver::class)->supportedFields())],
            'location_rules.rules.*.operator' => ['required', 'string', Rule::in(['=', '!=', 'in', 'not_in'])],
            'location_rules.rules.*.value' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
            'fields.*.type' => ['required', 'string', Rule::in($fields->supportedFieldTypes())],
            'fields.*.settings' => ['nullable', 'array'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $group = $this->route('group');

            $errors = app(AdditionalFieldsService::class)->validateFieldDefinitions(
                (array) $this->input('fields', []),
                $group instanceof AdditionalFieldGroup ? $group : null,
            );

            foreach ($errors as $path => $messages) {
                foreach ((array) $messages as $message) {
                    $validator->errors()->add($path, $message);
                }
            }
        });
    }
}
