<?php

namespace App\Http\Controllers;

use App\Http\Requests\Upload\CompleteMultipartUploadRequest;
use App\Http\Requests\Upload\CreateMultipartUploadRequest;
use App\Models\Upload;
use App\Services\Upload\UploadManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private UploadManager $manager
    ) {}

    public function create(CreateMultipartUploadRequest $request): JsonResponse
    {
        $upload = $this->manager->createMultipartUpload(
            auth()->id(),
            $request->string('bucket')->toString(),
            $request->string('filename')->toString(),
            $request->filled('size') ? (int) $request->integer('size') : null,
            $request->input('type'),
        );

        return response()->json([
            'uploadId' => $upload->id,
            'key' => $upload->object_key,
            'bucket' => $upload->bucket,
            'partSize' => $upload->part_size,
            'expiresAt' => $upload->expires_at?->toIso8601String(),
        ]);
    }

    public function signPart(Request $request, Upload $upload, int $partNumber): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        return response()->json($this->manager->signPart($upload, $partNumber));
    }

    public function listParts(Request $request, Upload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        return response()->json($this->manager->listParts($upload));
    }

    public function complete(CompleteMultipartUploadRequest $request, Upload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        $parts = array_map(fn ($part) => [
            'number' => $part['PartNumber'],
            'etag' => $part['ETag'] ?? null,
        ], $request->input('parts'));

        $upload = $this->manager->completeMultipartUpload($upload, $parts);

        return response()->json([
            'location' => $this->manager->location($upload),
            'key' => $upload->object_key,
            'uploadId' => $upload->id,
        ]);
    }

    public function abort(Request $request, Upload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        $this->manager->abortMultipartUpload($upload);

        return response()->json(new \stdClass);
    }

    public function uploadPart(Request $request, Upload $upload, int $partNumber): JsonResponse
    {
        $result = $this->manager->storePart($upload, $partNumber, $request->getContent(true));

        return response()
            ->json(['part_number' => $partNumber, 'etag' => $result['etag'], 'size' => $result['size']])
            ->header('ETag', '"'.$result['etag'].'"');
    }

    private function authorizeUpload(Request $request, Upload $upload): void
    {
        abort_unless($upload->user_id === auth()->id(), 403);

        $key = $request->query('key');

        abort_if($key !== null && $key !== $upload->object_key, 404);
    }
}
