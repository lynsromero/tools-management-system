<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Services\DownloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    public function __construct(private readonly DownloadService $downloads) {}

    public function download(Request $request, Tool $tool): BinaryFileResponse|JsonResponse
    {
        $file = $tool->files()->latest('id')->first();

        if (! $file) {
            return response()->json(['message' => 'No file available for this tool.'], 404);
        }

        $result = $this->downloads->download($request->user(), $file);

        if ($file->file_type === 'zip') {
            $bundle = $this->downloads->bundleZip(
                $file,
                $result['config'],
                $request->query('browser'),
            );

            return response()->download($bundle, basename($file->file_path))
                ->deleteFileAfterSend(true);
        }

        return response()->download(
            Storage::disk('local')->path($file->file_path),
            basename($file->file_path),
        );
    }

    public function config(Request $request, Tool $tool): Response|JsonResponse
    {
        $file = $tool->files()->latest('id')->first();

        if (! $file) {
            return response()->json(['message' => 'No file available for this tool.'], 404);
        }

        $result = $this->downloads->download($request->user(), $file);

        return response()->streamDownload(function () use ($result) {
            echo json_encode($result['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, 'config.json', ['Content-Type' => 'application/json']);
    }

    public function extension(Request $request, Tool $tool, string $browser): BinaryFileResponse|JsonResponse
    {
        if ($tool->type !== Tool::TYPE_EXTENSION) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $file = $tool->files()->latest('id')->first();

        if (! $file) {
            return response()->json(['message' => 'No file available for this tool.'], 404);
        }

        if ($file->file_type !== 'zip') {
            return response()->json(['message' => 'No file available for this tool.'], 404);
        }

        $browsers = $tool->extension_meta['browsers'] ?? [];

        if (! in_array($browser, $browsers, true)) {
            return response()->json([
                'message' => 'The selected browser is invalid.',
                'errors' => ['browser' => ['The selected browser is not supported for this tool.']],
            ], 422);
        }

        $result = $this->downloads->download($request->user(), $file);
        $bundle = $this->downloads->bundleZip($file, $result['config'], $browser);

        return response()->download($bundle, "{$tool->slug}-{$browser}.zip")
            ->deleteFileAfterSend(true);
    }
}
