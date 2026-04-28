<?php

namespace App\Http\Requests\Admin\Pages;

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Запрос валидирует создание страницы в административной зоне.
 * Он держит HTTP-правила отдельно от доменной логики.
 */
class StorePageRequest extends FormRequest
{
    /**
     * Запрос проверяет базовое право на создание страницы.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Page::class);
    }

    /**
     * Запрос описывает минимальный набор правил для страницы.
     *
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:pages,slug'],
            'status' => ['nullable', Rule::enum(PageStatus::class)],
            'visibility' => ['nullable', Rule::enum(PageVisibility::class)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'template' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'featured_media_id' => ['nullable', 'integer', 'exists:'.(new MediaFile())->getTable().',id'],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_home' => ['nullable', 'boolean'],
        ];
    }
}