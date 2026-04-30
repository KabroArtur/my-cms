<?php

namespace App\Core\Pages\Observers;

use App\Core\Pages\Models\Page;
use App\Core\Support\Services\CmsCacheService;

class PageCacheObserver
{
    public function saved(Page $page): void
    {
        app(CmsCacheService::class)->invalidateAll(reason: 'page-model-saved');
    }

    public function deleted(Page $page): void
    {
        app(CmsCacheService::class)->invalidateAll(reason: 'page-model-deleted');
    }

    public function restored(Page $page): void
    {
        app(CmsCacheService::class)->invalidateAll(reason: 'page-model-restored');
    }

    public function forceDeleted(Page $page): void
    {
        app(CmsCacheService::class)->invalidateAll(reason: 'page-model-force-deleted');
    }
}
