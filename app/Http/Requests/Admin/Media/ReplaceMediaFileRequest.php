<?php

namespace App\Http\Requests\Admin\Media;

use App\Core\Media\Models\MediaFile;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceMediaFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MediaFile $mediaFile */
        $mediaFile = $this->route('mediaFile');

        return $this->user()?->can('update', $mediaFile) ?? false;
    }

    public function rules(): array
    {
        $maxUploadKb = (int) config('settings.defaults.media_upload_max_kb', 20480);
        $acceptedMimeTypes = config('media.uploads.accepted_mime_types', []);

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxUploadKb,
                'mimetypes:'.implode(',', $acceptedMimeTypes),
            ],
        ];
    }
}