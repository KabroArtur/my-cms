<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadMediaBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MediaFile::class);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.folder_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}