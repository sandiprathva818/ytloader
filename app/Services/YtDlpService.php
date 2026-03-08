<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class YtDlpService
{
    protected string $binaryPath = 'C:\Python313\python.exe';

    public function __construct()
    {
        // Use system python with yt_dlp module
    }

    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
    ];

    public function getVideoInfo(string $url): ?array
    {
        $this->ensureCookiesFile();
        $userAgent = $this->userAgents[array_rand($this->userAgents)];

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
            $ytDlpArgs = [$binaryPath, '-m', 'yt_dlp'];
        }

        $args = array_merge($ytDlpArgs, [
            '--dump-json',
            '--flat-playlist',
            '--no-warnings',
            '--rm-cache-dir',
            '--extractor-args',
            'youtube:player-client=tvembedded,android_music,ios_music,ios,android',
            $url
        ]);

        if (file_exists(storage_path('app/cookies.txt'))) {
            $args[] = '--cookies';
            $args[] = storage_path('app/cookies.txt');
        }

        Log::info('Running yt-dlp command: ' . implode(' ', $args));

        $env = array_merge(getenv(), [
            'TEMP' => storage_path('app/tmp'),
            'TMP' => storage_path('app/tmp'),
            'YTDLP_NO_CURL_CFFI' => '1',
        ]);

        try {
            $process = new Process($args, null, $env);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('yt-dlp failed: ' . $process->getErrorOutput());
                // Don't just return null, throw an exception so the controller catches it and prints the exact yt-dlp error to the UI
                throw new \Exception('Video Info Failed: ' . $process->getErrorOutput());
            }

            return json_decode($process->getOutput(), true);
        } catch (\Throwable $e) {
            Log::error('yt-dlp crash: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            throw new \Exception('yt-dlp error: ' . $e->getMessage());
        }
    }

    public function getFormattedMetadata(string $url): ?array
    {
        // Always fetch yt-dlp info to know the maximum available resolution
        $info = $this->getVideoInfo($url);

        // Optionally fetch API info for better title/thumbnail correlation
        $apiInfo = $this->getMetadataFromApi($url);

        if (!$info && !$apiInfo) {
            return null;
        }

        $formats = $this->getStandardFormats();
        if ($info && isset($info['formats'])) {
            $maxHeight = 0;
            foreach ($info['formats'] as $f) {
                if (isset($f['height']) && $f['height'] > $maxHeight) {
                    $maxHeight = $f['height'];
                }
            }

            if ($maxHeight > 0) {
                $filteredVideo = [];
                foreach ($formats['video'] as $v) {
                    $h = (int) str_replace('p', '', $v['resolution']);
                    if ($h <= $maxHeight) {
                        $filteredVideo[] = $v;
                    }
                }
                $formats['video'] = $filteredVideo;
            }
        }

        return [
            'id' => $info['id'] ?? $apiInfo['id'] ?? '',
            'title' => $apiInfo['snippet']['title'] ?? $info['title'] ?? 'Unknown Title',
            'thumbnail' => $apiInfo['snippet']['thumbnails']['maxres']['url'] ?? $apiInfo['snippet']['thumbnails']['high']['url'] ?? $info['thumbnail'] ?? '',
            'duration' => isset($apiInfo['duration']) ? $this->formatDuration($apiInfo['duration']) : (is_string($info['duration'] ?? null) ? $info['duration'] : $this->formatDuration($info['duration'] ?? 0)),
            'channel' => $apiInfo['snippet']['channelTitle'] ?? $info['uploader'] ?? $info['channel'] ?? 'Unknown Channel',
            'formats' => $formats,
        ];
    }

    protected function getMetadataFromApi(string $url): ?array
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey)
            return null;

        $videoId = $this->extractVideoId($url);
        if (!$videoId)
            return null;

        try {
            $client = new Client();
            $response = $client->get("https://www.googleapis.com/youtube/v3/videos", [
                'query' => [
                    'part' => 'snippet,contentDetails',
                    'id' => $videoId,
                    'key' => $apiKey
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (empty($data['items']))
                return null;

            $item = $data['items'][0];
            $item['id'] = $videoId;

            // Convert ISO8601 duration to seconds
            $duration = $item['contentDetails']['duration'];
            $item['duration'] = $this->iso8601ToSeconds($duration);

            return $item;
        } catch (\Throwable $e) {
            Log::warning('YouTube API fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function extractVideoId(string $url): ?string
    {
        // Supports: watch?v=, v/, e/, embed/, shorts/, and youtu.be/
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function iso8601ToSeconds(string $iso): int
    {
        $interval = new \DateInterval($iso);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    protected function getStandardFormats(): array
    {
        return [
            'video' => [
                ['format_id' => 'bestvideo[height<=4320]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '4320p', 'filesize' => 'Dynamic', 'vcodec' => '8K UHD'],
                ['format_id' => 'bestvideo[height<=2160]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '2160p', 'filesize' => 'Dynamic', 'vcodec' => '4K UHD'],
                ['format_id' => 'bestvideo[height<=1440]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '1440p', 'filesize' => 'Dynamic', 'vcodec' => '2K QHD'],
                ['format_id' => 'bestvideo[height<=1080]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '1080p', 'filesize' => 'Dynamic', 'vcodec' => 'Full HD'],
                ['format_id' => 'bestvideo[height<=720]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '720p', 'filesize' => 'Dynamic', 'vcodec' => 'HD'],
                ['format_id' => 'bestvideo[height<=480]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '480p', 'filesize' => 'Dynamic', 'vcodec' => 'SD'],
                ['format_id' => 'bestvideo[height<=360]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '360p', 'filesize' => 'Dynamic', 'vcodec' => 'Std mobile'],
                ['format_id' => 'bestvideo[height<=240]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '240p', 'filesize' => 'Dynamic', 'vcodec' => 'Low qual'],
                ['format_id' => 'bestvideo[height<=144]+bestaudio/best', 'ext' => 'mp4', 'resolution' => '144p', 'filesize' => 'Dynamic', 'vcodec' => 'Very low'],
            ],
            'audio' => [
                ['format_id' => 'audio-360', 'ext' => 'mp3', 'filesize' => 'Dynamic', 'bitrate' => '360kbps', 'acodec' => 'mp3'],
                ['format_id' => 'audio-320', 'ext' => 'mp3', 'filesize' => 'Dynamic', 'bitrate' => '320kbps', 'acodec' => 'mp3'],
                ['format_id' => 'audio-256', 'ext' => 'mp3', 'filesize' => 'Dynamic', 'bitrate' => '256kbps', 'acodec' => 'mp3'],
                ['format_id' => 'audio-192', 'ext' => 'mp3', 'filesize' => 'Dynamic', 'bitrate' => '192kbps', 'acodec' => 'mp3'],
                ['format_id' => 'audio-128', 'ext' => 'mp3', 'filesize' => 'Dynamic', 'bitrate' => '128kbps', 'acodec' => 'mp3'],
            ]
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 1)
            return '0:00';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%d:%02d', $m, $s);
    }

    protected function parseFormats(array $formats): array
    {
        $parsed = [
            'video' => [],
            'audio' => [],
        ];

        $bestByHeight = [];
        $audioOptions = [];

        foreach ($formats as $f) {
            if (isset($f['vcodec']) && $f['vcodec'] !== 'none') {
                $height = $f['height'] ?? 0;

                // Only care about standard heights roughly
                if ($height < 144)
                    continue;

                $resolutionLabel = $height . 'p';
                if ($height >= 4320)
                    $resolutionLabel = '8K (' . $height . 'p)';
                elseif ($height >= 2160)
                    $resolutionLabel = '4K (' . $height . 'p)';
                elseif ($height >= 1440)
                    $resolutionLabel = '2K (' . $height . 'p)';

                $formatId = $f['format_id'];

                // If it's a video-only stream, we MUST merge best audio, so we modify the ID
                if (!isset($f['acodec']) || $f['acodec'] === 'none') {
                    $formatId .= '+bestaudio';
                }

                $tbr = $f['tbr'] ?? 0;
                $fps = $f['fps'] ?? 0;
                $vcodec = $f['vcodec'] ?? '';
                $filesizeStr = $this->formatSize($f['filesize'] ?? $f['filesize_approx'] ?? 0);
                $isMerged = str_contains($formatId, '+bestaudio');
                $ext = $isMerged ? 'mp4' : ($f['ext'] ?? 'mp4');

                // Keep the one with the highest bitrate for each height
                if (!isset($bestByHeight[$height]) || $tbr > ($bestByHeight[$height]['tbr'] ?? 0)) {
                    $bestByHeight[$height] = [
                        'format_id' => $formatId,
                        'ext' => $ext,
                        'resolution' => $resolutionLabel,
                        'filesize' => $filesizeStr,
                        'note' => $f['format_note'] ?? '',
                        'vcodec' => $vcodec,
                        'fps' => $fps,
                        'tbr' => $tbr,
                    ];
                }
            }

            if ((isset($f['acodec']) && $f['acodec'] !== 'none' && (!isset($f['vcodec']) || $f['vcodec'] === 'none')) || ($f['ext'] === 'm4a' || $f['ext'] === 'mp3' || $f['ext'] === 'webm' && isset($f['acodec']))) {
                $bitrateInt = (int) ($f['abr'] ?? $f['tbr'] ?? 0);
                if ($bitrateInt > 0) {
                    $audioOptions[] = [
                        'format_id' => $f['format_id'],
                        'ext' => 'mp3',
                        'filesize' => $this->formatSize($f['filesize'] ?? $f['filesize_approx'] ?? 0),
                        'bitrate' => $bitrateInt . 'kbps',
                        'acodec' => $f['acodec'] ?? '',
                        'abr' => $bitrateInt
                    ];
                }
            }
        }

        // Sort videos highest resolution first
        krsort($bestByHeight);
        foreach ($bestByHeight as $v) {
            unset($v['tbr']);
            $parsed['video'][] = $v;
        }

        // Add best audio option generically
        $parsed['audio'][] = [
            'format_id' => 'bestaudio',
            'ext' => 'mp3',
            'filesize' => 'Dynamic',
            'bitrate' => 'Best (up to 320kbps)',
            'acodec' => 'mp3'
        ];

        // Sort audio by bitrate and add unique ones
        usort($audioOptions, fn($a, $b) => $b['abr'] <=> $a['abr']);
        $uniqueAudio = [];
        foreach ($audioOptions as $a) {
            $key = $a['abr'] . $a['ext'];
            if (!isset($uniqueAudio[$key])) {
                $uniqueAudio[$key] = true;
                unset($a['abr']);
                $parsed['audio'][] = $a;
            }
        }

        return $parsed;

    }

    protected function formatSize(int $bytes): string
    {
        if ($bytes === 0)
            return 'Unknown';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes > 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
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
