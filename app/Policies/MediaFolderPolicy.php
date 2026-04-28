<?php

namespace App\Policies;

use App\Core\Media\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
{
    public function viewAny(?User $user): bool
    {
        return $this->allows($user, 'media.access');
    }

    public function create(?User $user): bool
    {
        return $this->allows($user, 'media.manage_folders');
    }

    public function update(?User $user, MediaFolder $folder): bool
    {
        return $this->allows($user, 'media.manage_folders');
    }

    public function delete(?User $user, MediaFolder $folder): bool
    {
        return $this->allows($user, 'media.manage_folders');
    }

    protected function allows(?User $user, string $permission): bool
    {
        return $user?->hasPermission($permission) ?? false;
    }
}