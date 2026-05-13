<?php

namespace App\Http\Requests\Admin\Languages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.general.manage') ?? false;
    }

    public function rules(): array
    {
        $languageId = $this->route('language')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'native_name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:12', 'regex:/^[a-z]{2,8}(?:-[a-z0-9]{2,8})?$/', Rule::unique('languages', 'code')->ignore($languageId)],
            'locale' => ['required', 'string', 'max:20'],
            'direction' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}