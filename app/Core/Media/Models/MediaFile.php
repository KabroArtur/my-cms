<?php

namespace App\Core\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    protected $fillable = [
        'folder_id',
        'created_by',
        'disk',
        'directory',
        'filename',
        'original_name',
        'title',
        'alt_text',
        'caption',
        'extension',
        'mime_type',
        'size',
        'width',
        'height',
        'path',
        'variants',
    ];

    protected $casts = [
        'variants' => 'array',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function variantUrl(string $name): ?string
    {
        $path = $this->variants[$name]['path'] ?? null;

        return is_string($path) && $path !== ''
            ? Storage::disk($this->disk)->url($path)
            : null;
    }
}