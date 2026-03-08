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
        $this->ensureCookiesFile();

        $storagePath = storage_path("app/public/downloads/{$this->jobId}");
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $this->updateStatus('downloading', 0);

        $outputFile = $storagePath . '/%(title)s.%(ext)s';

        $actualFormatId = $this->formatId;
        if ($this->type === 'audio' && str_starts_with($this->formatId, 'audio-')) {
            $actualFormatId = 'bestaudio/best';
        } elseif ($this->type === 'video' && str_contains($this->formatId, '[height<=')) {
            // Extract the target height boundary so we can cascade down properly
            preg_match('/\[height<=(\d+)\]/', $this->formatId, $matches);
            $height = $matches[1] ?? '1080';
            // Cascade: Try requested exact height -> Try anything best up to that height -> Just get highest possible -> fallback to single best stream
            $actualFormatId = sprintf('bestvideo[height<=%s]+bestaudio/best/bestvideo+bestaudio/best', $height);
        }

        $command = [
            'yt-dlp',
            '-f',
            $actualFormatId,
            '-o',
            $outputFile,
            '--newline',
            '--no-warnings',
            '--rm-cache-dir',
            $this->url
        ];

        if (file_exists(storage_path('app/cookies.txt'))) {
            $command[] = '--cookies';
            $command[] = storage_path('app/cookies.txt');
        }

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
            $err = $process->getErrorOutput() ?: $process->getOutput() ?: 'Unknown error occurred.';
            Log::error("Download failed for Job {$this->jobId}: " . $err);
            $this->updateStatus('failed', 0, '', $err);
        }
    }

    protected function updateStatus(string $status, float $progress, string $url = '', string $error = ''): void
    {
        $statusData = [
            'status' => $status,
            'progress' => $progress,
            'url' => $url,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put("downloads/{$this->jobId}/status.json", json_encode($statusData));
    }

    protected function ensureCookiesFile(): void
    {
        $cookies = getenv('YOUTUBE_COOKIES');
        $cookiePath = storage_path('app/cookies.txt');

        if (!empty($cookies)) {
            // Fix literal \n and \r
            $formattedCookies = str_replace(['\n', '\r'], ["\n", ""], $cookies);
            $formattedCookies = str_replace("\r", "", $formattedCookies);

            $lines = explode("\n", $formattedCookies);
            $validLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || $line === '# Netscape HTTP Cookie File' || str_starts_with($line, '# http')) {
                    continue;
                }

                // Render often replaces tabs with spaces in environment variables.
                // The first 6 fields of a Netscape cookie never contain spaces, so we can reliably rebuild the tabs.
                $parts = preg_split('/\s+/', $line, 7);
                if (count($parts) === 7) {
                    // Quick validation: column 2 and 4 must be TRUE or FALSE
                    if (in_array(strtoupper($parts[1]), ['TRUE', 'FALSE']) && in_array(strtoupper($parts[3]), ['TRUE', 'FALSE'])) {
                        $validLines[] = implode("\t", $parts);
                    }
                }
            }

            $finalCookies = "# Netscape HTTP Cookie File\n" . implode("\n", $validLines) . "\n";
            file_put_contents($cookiePath, $finalCookies);
        }
    }
}
