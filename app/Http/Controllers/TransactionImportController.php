<?php

namespace App\Http\Controllers;

use App\Http\Requests\Import\ExecuteImportRequest;
use App\Http\Requests\Import\ParseCsvRequest;
use App\Http\Requests\Import\PreviewImportRequest;
use App\Services\Import\CsvImportService;
use Illuminate\Http\JsonResponse;

class TransactionImportController extends Controller
{
    public function __construct(
        private CsvImportService $importService
    ) {}

    /**
     * Parse CSV file and return preview data
     */
    public function parse(ParseCsvRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->parse($request->file('file'));

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to parse CSV file: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview import results
     */
    public function preview(PreviewImportRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->preview(
                $request->input('import_id'),
                $request->input('mapping'),
                $request->input('options')
            );

            return response()->json([
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to preview import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute the import
     */
    public function import(ExecuteImportRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->import(
                $request->input('import_id'),
                $request->input('mapping'),
                $request->input('options')
            );

            return response()->json([
                'data' => $result,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to execute import: ' . $e->getMessage(),
            ], 500);
        }
    }
}
