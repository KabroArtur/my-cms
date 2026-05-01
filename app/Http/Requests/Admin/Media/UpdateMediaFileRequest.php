<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MediaFile $mediaFile */
        $mediaFile = $this->route('mediaFile');

        return $this->user()?->can('update', $mediaFile) ?? false;
    }

    public function rules(): array
    {
        return [
            'original_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
        ];
    }
}