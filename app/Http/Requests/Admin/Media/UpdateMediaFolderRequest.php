<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFolder;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MediaFolder $folder */
        $folder = $this->route('folder');

        return $this->user()?->can('update', $folder) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}