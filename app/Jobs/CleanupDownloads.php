<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupDownloads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $directories = Storage::disk('public')->directories('downloads');

        foreach ($directories as $dir) {
            $lastModified = Storage::disk('public')->lastModified($dir);

            // Delete if older than 1 hour
            if (Carbon::createFromTimestamp($lastModified)->addHour()->isPast()) {
                Storage::disk('public')->deleteDirectory($dir);
            }
        }
    }
}
