<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransformMediaFileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $numericFields = [
            'resize_width',
            'resize_height',
            'quality',
            'crop_x',
            'crop_y',
            'crop_width',
            'crop_height',
        ];

        $merge = [];

        foreach ($numericFields as $field) {
            $value = $this->input($field);

            if ($value === '' || $value === null) {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function authorize(): bool
    {
        /** @var MediaFile $mediaFile */
        $mediaFile = $this->route('mediaFile');

        return $this->user()?->can('update', $mediaFile) ?? false;
    }

    public function rules(): array
    {
        return [
            'crop_enabled' => ['nullable', 'boolean'],
            'crop_x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'crop_y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'crop_width' => ['nullable', 'numeric', 'gt:0', 'max:1'],
            'crop_height' => ['nullable', 'numeric', 'gt:0', 'max:1'],
            'resize_width' => ['nullable', 'integer', 'min:1', 'max:12000'],
            'resize_height' => ['nullable', 'integer', 'min:1', 'max:12000'],
            'maintain_aspect_ratio' => ['nullable', 'boolean'],
            'quality' => ['nullable', 'integer', 'min:30', 'max:100'],
            'format' => ['nullable', 'string', Rule::in(['original', 'jpg', 'png', 'webp'])],
        ];
    }
}