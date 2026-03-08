<?php

namespace App\Http\Controllers;

use App\Services\YtDlpService;
use App\Services\DownloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    public function __construct(
        protected YtDlpService $ytDlp,
        protected DownloadService $downloadService
    ) {
    }

    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'url', 'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/i']
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        $metadata = $this->ytDlp->getFormattedMetadata($request->url);

        if (!$metadata) {
            return response()->json([
                'error' => 'Could not fetch video information. Check logs for details.',
                'debug_info' => 'Try removing parameters from URL'
            ], 400);
        }

        return response()->json($metadata);
    }

    public function download(Request $request)
    {
        $request->validate([
            'url' => ['required', 'url', 'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/i'],
            'format_id' => 'required',
            'type' => 'required|in:video,audio'
        ]);

        $jobId = $this->downloadService->dispatchDownload(
            $request->url,
            $request->format_id,
            $request->type
        );

        return response()->json(['job_id' => $jobId]);
    }

    public function status(string $jobId)
    {
        return response()->json($this->downloadService->getDownloadStatus($jobId));
    }
}
