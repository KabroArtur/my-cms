<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MediaFolder::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}