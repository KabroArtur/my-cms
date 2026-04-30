<?php

namespace App\Http\Requests\Admin\Pages;

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Запрос валидирует обновление страницы в административной зоне.
 * Он учитывает текущую запись при проверке уникальности slug.
 */
class UpdatePageRequest extends FormRequest
{
    /**
     * Запрос проверяет базовое право на изменение страницы.
     */
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page && Gate::allows('update', $page);
    }

    /**
     * Запрос описывает правила обновления страницы.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $pageId = $this->route('page');
        $templateValues = collect(app(SettingsManager::class)->pageTemplateOptions())
            ->pluck('value')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($pageId)],
            'status' => ['nullable', Rule::enum(PageStatus::class)],
            'visibility' => ['nullable', Rule::enum(PageVisibility::class)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'template' => ['nullable', 'string', 'max:255', Rule::in($templateValues)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'featured_media_id' => ['nullable', 'integer', 'exists:'.(new MediaFile())->getTable().',id'],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_home' => ['nullable', 'boolean'],
            'additional_fields' => ['nullable', 'array'],
        ];
    }
}