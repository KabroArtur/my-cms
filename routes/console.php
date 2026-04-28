<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Media\Services\MediaVariantManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pages:publish-scheduled', function () {
    $updatedCount = DB::table('pages')
        ->where('status', 'scheduled')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->update([
            'status' => 'published',
            'updated_at' => now(),
        ]);

    $this->info("Опубликовано страниц: {$updatedCount}");
})->purpose('Publishes scheduled pages whose publication date has arrived.');

Artisan::command('media:regenerate-variants {--id=*}', function (MediaVariantManager $variantsManager) {
    $ids = collect((array) $this->option('id'))
        ->filter(fn (mixed $value): bool => is_numeric($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->values();

    $query = MediaFile::query();

    if ($ids->isNotEmpty()) {
        $query->whereKey($ids->all());
    }

    $files = $query->get();

    if ($files->isEmpty()) {
        $this->warn('Файлы для регенерации не найдены.');

        return self::SUCCESS;
    }

    $processedCount = 0;

    foreach ($files as $mediaFile) {
        $newVariants = $variantsManager->generateForMediaFile($mediaFile);

        $mediaFile->forceFill([
            'variants' => $newVariants,
        ])->save();

        $processedCount++;
    }

    $this->info("Регенерировано файлов: {$processedCount}");

    return self::SUCCESS;
})->purpose('Regenerates thumb, medium and large image variants for existing media files.');

Schedule::command('pages:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
