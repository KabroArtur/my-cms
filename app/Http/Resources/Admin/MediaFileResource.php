<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'folder_name' => $this->folder?->name,
            'original_name' => $this->original_name,
            'title' => $this->title,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'description' => $this->description,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->humanReadableSize(),
            'width' => $this->width,
            'height' => $this->height,
            'directory' => $this->directory,
            'path' => $this->path,
            'url' => $this->url(),
            'preview_url' => $this->variantUrl((string) config('media.preview_variant', 'thumbnail'))
                ?? $this->variantUrl('thumb')
                ?? $this->url(),
            'variants' => $this->variantPayload(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function variantPayload(): array
    {
        $variants = $this->variants ?? [];

        if (! is_array($variants)) {
            return [];
        }

        $payload = [];

        foreach ($variants as $name => $variant) {
            $path = $variant['path'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $payload[$name] = [
                'path' => $path,
                'url' => Storage::disk($this->disk)->url($path),
                'width' => $variant['width'] ?? null,
                'height' => $variant['height'] ?? null,
                'size' => $variant['size'] ?? null,
            ];
        }

        return $payload;
    }

    protected function humanReadableSize(): string
    {
        $size = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1, '.', ' ').' '.$units[$index];
    }
}