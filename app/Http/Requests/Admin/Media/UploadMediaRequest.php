<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MediaFile::class);
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp',
            ],
        ];
    }
}