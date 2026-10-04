<?php

namespace App\Actions\Tickets;

use App\Models\TicketRequest;
use App\Services\YouTrackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Receives an email image in small base64 chunks (so requests stay under PHP's post_max_size),
 * reassembles it and uploads it to a YouTrack issue created by this app.
 */
class StoreTicketAttachmentChunkAction
{
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
    public const CHUNK_BYTES = 1024 * 1024;
    public const UPLOAD_WINDOW_MINUTES = 60;

    private const CHUNK_DIRECTORY = 'attachment-chunks';
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp'];

    public function handle(Request $request, string $issueId, YouTrackService $youTrackService): JsonResponse
    {
        $payload = $request->all();
        $maxChunks = (int) ceil(self::MAX_IMAGE_BYTES / self::CHUNK_BYTES);

        $validator = Validator::make($payload, [
            'uploadId' => 'required|uuid',
            'name' => 'required|string|max:200',
            'index' => 'required|integer|min:0',
            'total' => "required|integer|min:1|max:{$maxChunks}",
            'data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $index = (int) $payload['index'];
        $total = (int) $payload['total'];
        if ($index >= $total) {
            return response()->json(['error' => 'Chunk index is out of range.'], 400);
        }

        if (!$this->wasCreatedRecently($issueId)) {
            return response()->json(['error' => 'Attachments can only be added to tickets just created by this app.'], 403);
        }

        $chunk = base64_decode($payload['data'], true);
        if ($chunk === false || strlen($chunk) > self::CHUNK_BYTES) {
            return response()->json(['error' => 'Chunk data is invalid or too large.'], 400);
        }

        $this->pruneAbandonedUploads();

        $disk = Storage::disk('local');
        $directory = self::CHUNK_DIRECTORY . '/' . $payload['uploadId'];
        $disk->put("{$directory}/{$index}", $chunk);

        if (count($disk->files($directory)) < $total) {
            return response()->json(['done' => false]);
        }

        $contents = '';
        $missingChunk = false;
        for ($i = 0; $i < $total; $i++) {
            $part = $disk->get("{$directory}/{$i}");
            if ($part === null) {
                $missingChunk = true;
                break;
            }
            $contents .= $part;
        }
        $disk->deleteDirectory($directory);

        if ($missingChunk) {
            return response()->json(['error' => 'Image upload is missing a chunk.'], 400);
        }

        if (strlen($contents) > self::MAX_IMAGE_BYTES) {
            return response()->json(['error' => 'Image is larger than 10 MB.'], 400);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => 'Only PNG, JPEG, GIF, WebP and BMP images can be attached.'], 400);
        }

        $name = $this->sanitizeName($payload['name']);

        try {
            $youTrackService->uploadAttachment($issueId, $name, $contents);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json(['done' => true, 'name' => $name]);
    }

    private function wasCreatedRecently(string $issueId): bool
    {
        return TicketRequest::query()
            ->where('youtrack_issue_id', $issueId)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subMinutes(self::UPLOAD_WINDOW_MINUTES))
            ->exists();
    }

    private function sanitizeName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($name)) ?? '';
        $name = trim($name, '._');

        return $name === '' ? 'image' : $name;
    }

    private function pruneAbandonedUploads(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->directories(self::CHUNK_DIRECTORY) as $directory) {
            $files = $disk->files($directory);
            if ($files === [] || $disk->lastModified($files[0]) < $cutoff) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
