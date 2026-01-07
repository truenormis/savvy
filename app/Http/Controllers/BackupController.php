<?php

namespace App\Http\Controllers;

use App\Http\Resources\BackupResource;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backupService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return BackupResource::collection($this->backupService->list());
    }

    public function store(Request $request): BackupResource
    {
        $request->validate([
            'note' => 'nullable|string|max:255',
        ]);

        $backup = $this->backupService->create($request->input('note'));

        return new BackupResource($backup);
    }

    public function upload(Request $request): BackupResource
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'note' => 'nullable|string|max:255',
        ]);

        $backup = $this->backupService->upload(
            $request->file('file'),
            $request->input('note')
        );

        return new BackupResource($backup);
    }

    public function download(Backup $backup): StreamedResponse
    {
        return $this->backupService->download($backup);
    }

    public function restore(Backup $backup): JsonResponse
    {
        $this->backupService->restore($backup);

        return response()->json(['message' => 'Database restored successfully']);
    }

    public function destroy(Backup $backup): JsonResponse
    {
        $this->backupService->delete($backup);

        return response()->json(null, 204);
    }
}
