<?php

namespace App\Services;

use App\Jobs\ProcessVideoDownload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadService
{
    public function dispatchDownload(string $url, string $formatId, string $type = 'video'): string
    {
        $jobId = Str::uuid()->toString();

        ProcessVideoDownload::dispatch($url, $formatId, $type, $jobId);

        return $jobId;
    }

    public function getDownloadStatus(string $jobId): array
    {
        // Simple file-based status tracking for now
        // In a real app, use Redis or Database
        $statusPath = "downloads/{$jobId}/status.json";

        if (!Storage::disk('public')->exists($statusPath)) {
            return ['status' => 'pending'];
        }

        return json_decode(Storage::disk('public')->get($statusPath), true);
    }
}
