<?php

namespace App\Jobs;

use App\Models\Download;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessVideoDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $url,
        protected string $formatId,
        protected string $type,
        protected string $jobId
    ) {
    }

    public function handle(): void
    {
        $storagePath = storage_path("app/public/downloads/{$this->jobId}");
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $this->updateStatus('downloading', 0);

        $outputFile = $storagePath . '/%(title)s.%(ext)s';

        $actualFormatId = $this->formatId;
        if ($this->type === 'audio' && str_starts_with($this->formatId, 'audio-')) {
            $actualFormatId = 'bestaudio/best';
        }

        $command = [
            'yt-dlp',
            '-f',
            $actualFormatId,
            '-o',
            $outputFile,
            '--newline',
            '--no-warnings',
            $this->url
        ];

        // If high quality video, we might need to merge audio
        if ($this->type === 'video' && str_contains($this->formatId, '+')) {
            $command[] = '--merge-output-format';
            $command[] = 'mp4';
        }

        if ($this->type === 'audio') {
            $command[] = '-x';
            $command[] = '--audio-format';
            $command[] = 'mp3';
            if (str_starts_with($this->formatId, 'audio-')) {
                $bitrate = str_replace('audio-', '', $this->formatId);
                $command[] = '--audio-quality';
                $command[] = $bitrate . 'K';
            } else {
                $command[] = '--audio-quality';
                $command[] = '0'; // Best
            }
        }

        $ffmpegDocker = '/usr/bin/ffmpeg';
        $ffmpegLinux = base_path('bin/ffmpeg');
        $ffmpegWin = base_path('bin/ffmpeg.exe');
        if (file_exists($ffmpegDocker)) {
            $command[] = '--ffmpeg-location';
            $command[] = $ffmpegDocker;
        } elseif (file_exists($ffmpegLinux)) {
            $command[] = '--ffmpeg-location';
            $command[] = $ffmpegLinux;
        } elseif (file_exists($ffmpegWin)) {
            $command[] = '--ffmpeg-location';
            $command[] = $ffmpegWin;
        }

        $ytDlpDocker = '/usr/local/bin/yt-dlp';
        $ytDlpLinux = base_path('bin/yt-dlp');
        $ytDlpWin = base_path('bin/yt-dlp.exe');
        if (file_exists($ytDlpDocker)) {
            $ytDlpArgs = [$ytDlpDocker];
        } elseif (file_exists($ytDlpLinux)) {
            $ytDlpArgs = [$ytDlpLinux];
        } elseif (file_exists($ytDlpWin)) {
            $ytDlpArgs = [$ytDlpWin];
        } else {
            $binaryPath = 'C:\Python313\python.exe';
            $ytDlpArgs = ['-m', 'yt_dlp'];
        }

        $process = new Process(array_merge($ytDlpArgs, array_slice($command, 1)), null, array_merge(getenv(), [
            'TEMP' => storage_path('app/tmp'),
            'TMP' => storage_path('app/tmp'),
            'YTDLP_NO_CURL_CFFI' => '1',
        ]));
        $process->setTimeout(3600); // 1 hour timeout

        $process->run(function ($type, $buffer) {
            if ($type === Process::OUT) {
                // Parse progress from yt-dlp output
                if (preg_match('/\[download\]\s+(\d+\.\d+)%/', $buffer, $matches)) {
                    $this->updateStatus('downloading', (float) $matches[1]);
                }
            }
        });

        if ($process->isSuccessful()) {
            $files = glob($storagePath . '/*');
            $downloadUrl = '';
            foreach ($files as $file) {
                if (basename($file) !== 'status.json') {
                    $downloadUrl = asset('storage/downloads/' . $this->jobId . '/' . basename($file));
                    break;
                }
            }
            $this->updateStatus('completed', 100, $downloadUrl);

            try {
                Download::create([
                    'title' => basename($downloadUrl) ?? 'Video',
                    'url' => $this->url,
                    'format' => $this->formatId,
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to record download in history: " . $e->getMessage());
            }
        } else {
            Log::error("Download failed for Job {$this->jobId}: " . $process->getErrorOutput());
            $this->updateStatus('failed', 0);
        }
    }

    protected function updateStatus(string $status, float $progress, string $url = ''): void
    {
        $statusData = [
            'status' => $status,
            'progress' => $progress,
            'url' => $url,
            'updated_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put("downloads/{$this->jobId}/status.json", json_encode($statusData));
    }
}
