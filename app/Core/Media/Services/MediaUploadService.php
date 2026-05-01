<?php

namespace App\Core\Media\Services;

use App\Core\Media\Models\MediaFile;
use App\Core\Media\Models\MediaFolder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MediaUploadService
{
    public function __construct(
        protected MediaVariantManager $variants,
    ) {
    }

    public function storeUploadedFile(UploadedFile $file, ?MediaFolder $folder, ?int $userId = null, ?string $desiredName = null): MediaFile
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $directory = $this->directoryForFolder($folder);
        $names = $this->resolveNames(
            folder: $folder,
            desiredName: $desiredName,
            fallbackName: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            extension: $extension,
        );

        $storedPath = null;

        try {
            $storedPath = $file->storeAs($directory, $names['filename'], 'public');
            [$width, $height] = array_pad((array) @getimagesize($file->getRealPath()), 2, null);
            $variants = $this->variants->generateForUpload($file, 'public', $directory, $names['filename']);

            return MediaFile::query()->create([
                'folder_id' => $folder?->id,
                'created_by' => $userId,
                'disk' => 'public',
                'directory' => $directory,
                'filename' => $names['filename'],
                'original_name' => $names['original_name'],
                'title' => $names['title'],
                'alt_text' => $names['title'],
                'extension' => $extension !== '' ? $extension : null,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'width' => is_int($width) ? $width : null,
                'height' => is_int($height) ? $height : null,
                'path' => $storedPath,
                'variants' => $variants,
            ]);
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function renameMediaFile(MediaFile $mediaFile, string $desiredName): MediaFile
    {
        $extension = strtolower((string) ($mediaFile->extension ?? pathinfo($mediaFile->filename, PATHINFO_EXTENSION)));
        $names = $this->resolveNames(
            folder: $mediaFile->folder,
            desiredName: $desiredName,
            fallbackName: pathinfo($mediaFile->original_name ?: $mediaFile->filename, PATHINFO_FILENAME),
            extension: $extension,
            ignoreFileId: $mediaFile->id,
        );

        if ($names['filename'] === $mediaFile->filename && $names['original_name'] === $mediaFile->original_name) {
            return $mediaFile;
        }

        $targetPath = $mediaFile->directory.'/'.$names['filename'];
        Storage::disk($mediaFile->disk)->move($mediaFile->path, $targetPath);

        $mediaFile->forceFill([
            'filename' => $names['filename'],
            'original_name' => $names['original_name'],
            'path' => $targetPath,
        ])->save();

        $variants = $this->variants->generateForMediaFile($mediaFile);

        $mediaFile->forceFill([
            'variants' => $variants,
        ])->save();

        return $mediaFile;
    }

    protected function resolveNames(?MediaFolder $folder, ?string $desiredName, string $fallbackName, string $extension, ?int $ignoreFileId = null): array
    {
        $displayBase = $this->resolveDisplayBaseName($desiredName, $fallbackName);
        $storageBase = Str::slug(Str::ascii($displayBase));
        $storageBase = $storageBase !== '' ? $storageBase : 'file';
        $suffix = 0;

        do {
            $candidateDisplay = $suffix === 0 ? $displayBase : $displayBase.'-'.$suffix;
            $candidateStorage = $suffix === 0 ? $storageBase : $storageBase.'-'.$suffix;
            $candidateFilename = $this->buildFilename($candidateStorage, $extension);
            $suffix++;
        } while ($this->filenameExists($folder, $candidateFilename, $ignoreFileId));

        return [
            'filename' => $candidateFilename,
            'original_name' => $this->buildFilename($candidateDisplay, $extension),
            'title' => $candidateDisplay,
        ];
    }

    protected function resolveDisplayBaseName(?string $desiredName, string $fallbackName): string
    {
        if ($desiredName !== null) {
            $sanitized = $this->sanitizeDisplayBaseName($desiredName);

            if ($sanitized === '') {
                throw ValidationException::withMessages([
                    'name' => 'Укажите корректное имя файла без опасных символов.',
                ]);
            }

            return $sanitized;
        }

        $fallback = $this->sanitizeDisplayBaseName($fallbackName);

        return $fallback !== '' ? $fallback : 'file';
    }

    protected function sanitizeDisplayBaseName(string $value): string
    {
        $value = trim($value);
        $value = str_replace('\\', '/', $value);
        $value = basename($value);
        $value = pathinfo($value, PATHINFO_FILENAME) ?: $value;
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = str_replace(['../', '..\\', '..', '/', '\\'], ' ', $value);
        $value = preg_replace('/[<>:"|?*]+/u', ' ', $value) ?? '';
        $value = preg_replace('/[^\pL\pN\s._()\-]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value, " .-_\t\n\r\0\x0B");

        return $value;
    }

    protected function filenameExists(?MediaFolder $folder, string $filename, ?int $ignoreFileId = null): bool
    {
        $query = MediaFile::query()->where('filename', $filename);

        if ($folder === null) {
            $query->whereNull('folder_id');
        } else {
            $query->where('folder_id', $folder->id);
        }

        if ($ignoreFileId !== null) {
            $query->whereKeyNot($ignoreFileId);
        }

        return $query->exists();
    }

    protected function buildFilename(string $baseName, string $extension): string
    {
        return $extension !== '' ? $baseName.'.'.$extension : $baseName;
    }

    protected function directoryForFolder(?MediaFolder $folder): string
    {
        return 'media'.($folder ? '/'.$folder->path : '');
    }
}