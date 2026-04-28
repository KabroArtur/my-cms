<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'date_format' => ['required', 'string', 'max:30'],
            'time_format' => ['required', 'string', 'max:30'],
            'home_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'site_theme' => ['required', 'string', 'max:120'],
            'site_featured_media_variant' => ['required', 'string', 'max:20'],
            'media_default_insert_variant' => ['required', 'string', 'max:20'],
            'cms_palette' => ['required', 'string', 'max:50'],
        ];
    }
}