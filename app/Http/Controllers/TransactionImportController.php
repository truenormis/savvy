<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\ExecuteImportRequest;
use App\Http\Requests\Import\ParseCsvRequest;
use App\Http\Requests\Import\PreviewImportRequest;
use App\Jobs\ParseImportJob;
use App\Jobs\RunImportJob;
use App\Models\TransactionImport;
use App\Models\Upload;
use App\Services\Import\CsvImportService;
use Illuminate\Http\JsonResponse;

class TransactionImportController extends Controller
{
    public function __construct(
        private CsvImportService $importService
    ) {}

    /**
     * Start parsing a completed upload (asynchronous)
     */
    public function parse(ParseCsvRequest $request): JsonResponse
    {
        $upload = Upload::findOrFail($request->input('upload_id'));

        abort_unless($upload->user_id === auth()->id(), 403);

        if ($upload->bucket !== 'transaction-imports' || ! $upload->isCompleted()) {
            return response()->json(['message' => 'The uploaded file is not ready for import.'], 422);
        }

        $import = TransactionImport::create([
            'user_id' => auth()->id(),
            'upload_id' => $upload->id,
            'status' => TransactionImport::STATUS_PARSING,
        ]);

        ParseImportJob::dispatch($import->id, $upload->id);

        return response()->json([
            'data' => $this->serialize($import),
        ]);
    }

    /**
     * Poll the state of an import (parse progress, preview metadata, import progress)
     */
    public function show(TransactionImport $import): JsonResponse
    {
        abort_unless($import->user_id === auth()->id(), 403);

        return response()->json([
            'data' => $this->serialize($import),
        ]);
    }

    /**
     * Preview import results (synchronous dry-run over the cached rows)
     */
    public function preview(PreviewImportRequest $request): JsonResponse
    {
        $import = $this->authorizeImport($request->input('import_id'));

        $upload = $import->upload_id ? Upload::find($import->upload_id) : null;

        if (! $upload || ! $upload->isCompleted()) {
            return response()->json(['message' => 'The uploaded file is no longer available. Please re-upload.'], 422);
        }

        $result = $this->importService->preview(
            $upload,
            $import->meta['parse_meta'] ?? [],
            $request->input('mapping'),
            $request->input('options')
        );

        $sampled = $result['summary']['will_create'] + $result['summary']['will_skip'] + $result['summary']['has_errors'];
        $result['summary']['total_rows'] = $import->total_rows;
        $result['summary']['sampled'] = $sampled;

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Execute the import (asynchronous)
     */
    public function execute(ExecuteImportRequest $request): JsonResponse
    {
        $import = $this->authorizeImport($request->input('import_id'));

        $import->update([
            'status' => TransactionImport::STATUS_IMPORTING,
            'mapping' => $request->input('mapping'),
            'options' => $request->input('options'),
            'processed_rows' => 0,
            'created_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'errors' => null,
            'message' => null,
        ]);

        RunImportJob::dispatch($import->id);

        return response()->json([
            'data' => $this->serialize($import),
        ]);
    }

    private function authorizeImport(string $importId): TransactionImport
    {
        $import = TransactionImport::findOrFail($importId);

        abort_unless($import->user_id === auth()->id(), 403);

        return $import;
    }

    private function serialize(TransactionImport $import): array
    {
        $parsed = in_array($import->status, [
            TransactionImport::STATUS_PARSED,
            TransactionImport::STATUS_IMPORTING,
            TransactionImport::STATUS_COMPLETED,
        ], true);

        return [
            'import_id' => $import->id,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'created' => $import->created_count,
            'skipped' => $import->skipped_count,
            'errors' => $import->error_count,
            'message' => $import->message,
            'parse' => $parsed && $import->meta ? [
                'headers' => $import->meta['headers'] ?? [],
                'preview_rows' => $import->meta['preview_rows'] ?? [],
                'total_rows' => $import->total_rows,
                'detected_formats' => $import->meta['detected_formats'] ?? null,
                'suggested_mapping' => $import->meta['suggested_mapping'] ?? [],
            ] : null,
            'result' => $import->status === TransactionImport::STATUS_COMPLETED ? [
                'created' => $import->created_count,
                'skipped_duplicates' => $import->skipped_count,
                'errors' => $import->errors ?? [],
                'created_currencies' => $import->meta['created_currencies'] ?? [],
                'created_tags' => $import->meta['created_tags'] ?? [],
                'created_categories' => $import->meta['created_categories'] ?? [],
            ] : null,
        ];
    }
}
