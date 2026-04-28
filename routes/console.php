<?php

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

Schedule::command('pages:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
