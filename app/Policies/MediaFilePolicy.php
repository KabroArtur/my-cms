<?php

namespace App\Policies;

use App\Core\Media\Models\MediaFile;
use App\Models\User;

class MediaFilePolicy
{
    public function viewAny(?User $user): bool
    {
        return $this->allows($user, 'media.access');
    }

    public function create(?User $user): bool
    {
        return $this->allows($user, 'media.upload');
    }

    public function update(?User $user, MediaFile $file): bool
    {
        return $this->allows($user, 'media.upload');
    }

    public function delete(?User $user, MediaFile $file): bool
    {
        return $this->allows($user, 'media.delete');
    }

    protected function allows(?User $user, string $permission): bool
    {
        return $user?->hasPermission($permission) ?? false;
    }
}